(function () {
    const roots = Array.from(document.querySelectorAll('[data-call-root]'));
    const root = roots.find(item => Number(item.dataset.selectedUserId || 0) > 0) || roots[0];
    if (!root) {
        return;
    }

    const apiUrl = root.dataset.callApiUrl || '/php/api/calls.php';
    const currentUserId = Number(root.dataset.currentUserId || 0);
    const selectedUserId = Number(root.dataset.selectedUserId || 0);
    const selectedUserName = root.dataset.selectedUserName || 'this user';
    const supportsCalling = Boolean(navigator.mediaDevices?.getUserMedia && window.RTCPeerConnection);

    const modal = document.getElementById('call-modal');
    const dialog = document.getElementById('call-dialog');
    const title = document.getElementById('call-title');
    const statusText = document.getElementById('call-status');
    const debugText = document.getElementById('call-debug');
    const audioAvatar = document.getElementById('call-audio-avatar');
    const localVideo = document.getElementById('local-video');
    const remoteVideo = document.getElementById('remote-video');
    const remoteAudio = document.getElementById('remote-audio');
    const acceptBtn = document.getElementById('accept-call');
    const declineBtn = document.getElementById('decline-call');
    const endBtn = document.getElementById('end-call');
    const muteBtn = document.getElementById('mute-call');
    const cameraBtn = document.getElementById('camera-call');
    const switchCameraBtn = document.getElementById('switch-camera-call');

    let peer = null;
    let localStream = null;
    let currentCall = null;
    let lastSignalId = 0;
    let pollTimer = null;
    let pendingIncoming = null;
    let pendingIceCandidates = [];
    let localOfferSent = false;
    let localAnswerSent = false;
    let isMuted = false;
    let isCameraOff = false;
    let activeVideoDeviceId = null;
    let preferredFacingMode = 'user';
    let ringTimer = null;
    let titleTimer = null;
    const originalTitle = document.title;
    const callButtons = root.querySelectorAll('[data-call-type]');

    const peerConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };

    async function callApi(action, data = null, params = {}) {
        const query = new URLSearchParams({ action, ...params });
        const options = data ? {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        } : {};

        const response = await fetch(`${apiUrl}?${query.toString()}`, options);
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            throw new Error('Call service is not available right now.');
        }

        return response.json();
    }

    function setModalVisible(visible) {
        modal.hidden = !visible;
        modal.classList.toggle('is-open', visible);
    }

    function setButtonsDisabled(disabled) {
        callButtons.forEach(button => {
            button.disabled = disabled;
        });
    }

    function getInitials(name) {
        return name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part.charAt(0).toUpperCase())
            .join('') || 'CN';
    }

    function prepareCallUi(callType, heading, message, otherName = selectedUserName) {
        title.textContent = heading;
        setStatus(message);
        audioAvatar.textContent = getInitials(otherName);
        dialog.classList.toggle('audio-mode', callType !== 'video');
        localVideo.hidden = callType !== 'video';
        remoteVideo.hidden = callType !== 'video';
        setModalVisible(true);
    }

    function setControls(mode) {
        acceptBtn.hidden = mode !== 'incoming';
        declineBtn.hidden = mode !== 'incoming';
        endBtn.hidden = mode === 'incoming';
        muteBtn.hidden = mode === 'incoming';
        cameraBtn.hidden = mode === 'incoming' || currentCall?.call_type !== 'video';
        switchCameraBtn.hidden = mode === 'incoming' || currentCall?.call_type !== 'video';
        cameraBtn.textContent = 'Camera Off';
        cameraBtn.disabled = false;
        switchCameraBtn.disabled = false;
    }

    function setStatus(message) {
        statusText.textContent = message;
    }

    function setDebug(message) {
        if (debugText) {
            debugText.textContent = message;
        }
        console.log(`[CampusNest call] ${message}`);
    }

    function startRingingCue(callType) {
        stopRingingCue();

        titleTimer = setInterval(() => {
            document.title = document.title === originalTitle ? `Incoming ${callType} call` : originalTitle;
        }, 900);

        ringTimer = setInterval(() => {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                const audioContext = new AudioContext();
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.value = 740;
                gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.08, audioContext.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.28);

                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.3);

                setTimeout(() => audioContext.close(), 500);
            } catch (error) {
                // Some browsers block audio until the user interacts with the page.
            }
        }, 1600);
    }

    function stopRingingCue() {
        if (ringTimer) {
            clearInterval(ringTimer);
            ringTimer = null;
        }

        if (titleTimer) {
            clearInterval(titleTimer);
            titleTimer = null;
        }

        document.title = originalTitle;
    }

    async function getMedia(callType) {
        if (!supportsCalling) {
            throw new Error('Audio and video calls require a modern browser on localhost or HTTPS.');
        }

        try {
            localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: callType === 'video' ? {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: preferredFacingMode
                } : false
            });
        } catch (error) {
            if (callType !== 'video') {
                throw error;
            }

            setStatus('Camera is unavailable. Continuing with audio.');
            setDebug(`Camera unavailable: ${error.message}`);
            localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false
            });
        }

        const videoTracks = localStream.getVideoTracks();
        isCameraOff = false;

        if (callType === 'video' && videoTracks.length === 0) {
            cameraBtn.textContent = 'No Camera';
            cameraBtn.disabled = true;
            switchCameraBtn.disabled = true;
            setStatus('Camera is unavailable. Audio is connected.');
        } else if (callType === 'video') {
            videoTracks.forEach(track => {
                track.enabled = true;
                track.onended = () => {
                    cameraBtn.textContent = 'No Camera';
                    cameraBtn.disabled = true;
                    setStatus('Camera stopped. Audio is still connected.');
                    setDebug('Local camera track ended');
                };
                track.onmute = () => {
                    setDebug('Local camera track muted by browser or device');
                };
                track.onunmute = () => {
                    setDebug('Local camera track active');
                };
            });
            activeVideoDeviceId = videoTracks[0].getSettings().deviceId || null;
            cameraBtn.textContent = 'Camera Off';
            cameraBtn.disabled = false;
            updateSwitchCameraAvailability();
        }

        setDebug(`Local ${callType} media ready`);
        localVideo.srcObject = localStream;
        localVideo.muted = true;
        localVideo.play().catch(() => {
            setDebug('Local camera preview is ready, but playback was blocked.');
        });
        localVideo.hidden = callType !== 'video';
        remoteVideo.hidden = callType !== 'video';
    }

    async function getVideoDevices() {
        if (!navigator.mediaDevices?.enumerateDevices) {
            return [];
        }

        const devices = await navigator.mediaDevices.enumerateDevices();
        return devices.filter(device => device.kind === 'videoinput');
    }

    async function updateSwitchCameraAvailability() {
        if (!switchCameraBtn || currentCall?.call_type !== 'video') return;

        try {
            const devices = await getVideoDevices();
            switchCameraBtn.disabled = devices.length < 2;
            switchCameraBtn.textContent = devices.length < 2 ? 'One Camera' : 'Switch Camera';
        } catch (error) {
            switchCameraBtn.disabled = true;
            switchCameraBtn.textContent = 'One Camera';
        }
    }

    async function switchCamera() {
        if (!localStream || currentCall?.call_type !== 'video') return;

        const devices = await getVideoDevices();
        if (devices.length < 2) {
            switchCameraBtn.disabled = true;
            switchCameraBtn.textContent = 'One Camera';
            return;
        }

        const currentIndex = devices.findIndex(device => device.deviceId === activeVideoDeviceId);
        const nextDevice = devices[(currentIndex + 1 + devices.length) % devices.length];

        switchCameraBtn.disabled = true;
        switchCameraBtn.textContent = 'Switching...';
        setDebug(`Switching camera to ${nextDevice.label || 'next camera'}`);

        try {
            const replacementStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    deviceId: { exact: nextDevice.deviceId },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            });
            const [newVideoTrack] = replacementStream.getVideoTracks();

            if (!newVideoTrack) {
                throw new Error('No video track returned from selected camera');
            }

            const oldVideoTrack = localStream.getVideoTracks()[0];
            const sender = peer?.getSenders().find(item => item.track && item.track.kind === 'video');

            if (sender) {
                await sender.replaceTrack(newVideoTrack);
            }

            if (oldVideoTrack) {
                localStream.removeTrack(oldVideoTrack);
                oldVideoTrack.stop();
            }

            localStream.addTrack(newVideoTrack);
            activeVideoDeviceId = newVideoTrack.getSettings().deviceId || nextDevice.deviceId;
            preferredFacingMode = preferredFacingMode === 'user' ? 'environment' : 'user';
            isCameraOff = false;
            cameraBtn.textContent = 'Camera Off';
            cameraBtn.disabled = false;
            localVideo.srcObject = localStream;
            await localVideo.play().catch(() => {});
            setDebug('Camera switched');
        } catch (error) {
            setStatus('Unable to switch camera.');
            setDebug(`Unable to switch camera: ${error.message}`);
        } finally {
            await updateSwitchCameraAvailability();
        }
    }

    function createPeer() {
        if (peer) return;

        peer = new RTCPeerConnection(peerConfig);
        setDebug('Peer connection created');

        localStream.getTracks().forEach(track => peer.addTrack(track, localStream));

        peer.ontrack = (event) => {
            const [remoteStream] = event.streams;
            if (!remoteStream) return;

            remoteVideo.srcObject = remoteStream;
            remoteAudio.srcObject = remoteStream;
            remoteAudio.play().catch(() => {
                setDebug('Remote audio is ready, but the browser blocked autoplay. Click inside the page.');
            });
            setStatus('Connected');
            setDebug('Remote media stream received');
        };

        peer.onicecandidate = (event) => {
            if (event.candidate && currentCall) {
                setDebug('Sending network candidate');
                callApi('signal', {
                    call_id: currentCall.id,
                    signal_type: 'ice',
                    payload: event.candidate.toJSON()
                }).catch(console.error);
            }
        };

        peer.oniceconnectionstatechange = () => {
            setDebug(`ICE state: ${peer.iceConnectionState}`);
        };

        peer.onconnectionstatechange = () => {
            setDebug(`Peer state: ${peer.connectionState}`);
            if (['connected', 'completed'].includes(peer.connectionState)) {
                setStatus('Connected');
            }

            if (['failed', 'disconnected', 'closed'].includes(peer.connectionState)) {
                setStatus('Call connection ended');
            }
        };
    }

    async function sendSignal(type, payload) {
        if (!currentCall) return;

        await callApi('signal', {
            call_id: currentCall.id,
            signal_type: type,
            payload
        });
    }

    async function flushPendingIceCandidates() {
        if (!peer || !peer.remoteDescription) return;

        const candidates = pendingIceCandidates;
        pendingIceCandidates = [];

        for (const candidate of candidates) {
            try {
                await peer.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                console.warn('Could not add queued ICE candidate', error);
            }
        }
    }

    async function handleSignal(signal) {
        lastSignalId = Math.max(lastSignalId, signal.id);

        if (!peer && localStream) {
            createPeer();
        }

        if (!peer) return;

        if (signal.signal_type === 'offer') {
            if (localAnswerSent) return;

            await peer.setRemoteDescription(new RTCSessionDescription(signal.payload));
            setDebug('Offer received');
            await flushPendingIceCandidates();
            const answer = await peer.createAnswer();
            await peer.setLocalDescription(answer);
            await sendSignal('answer', answer);
            localAnswerSent = true;
            setDebug('Answer sent');
            setStatus('Connecting...');
        }

        if (signal.signal_type === 'answer') {
            if (peer.remoteDescription) return;

            await peer.setRemoteDescription(new RTCSessionDescription(signal.payload));
            setDebug('Answer received');
            await flushPendingIceCandidates();
            setStatus('Connecting...');
        }

        if (signal.signal_type === 'ice') {
            if (!peer.remoteDescription) {
                pendingIceCandidates.push(signal.payload);
                return;
            }

            try {
                await peer.addIceCandidate(new RTCIceCandidate(signal.payload));
                setDebug('Network candidate added');
            } catch (error) {
                console.warn('Could not add ICE candidate', error);
                setDebug('Could not add network candidate');
            }
        }
    }

    async function pollCalls() {
        try {
            const params = currentCall ? { call_id: currentCall.id, since_id: lastSignalId } : {};
            const result = await callApi('poll', null, params);

            if (!result.success) return;

            if (result.data?.incoming_call && !currentCall && !pendingIncoming) {
                showIncoming(result.data.incoming_call);
            }

            if (result.data?.call) {
                currentCall = { ...currentCall, ...result.data.call };
                if (['declined', 'ended', 'missed'].includes(currentCall.status)) {
                    setStatus(`Call ${currentCall.status}`);
                    setTimeout(cleanupCall, 900);
                    return;
                }

                if (
                    currentCall.status === 'accepted' &&
                    currentCall.caller_id === currentUserId &&
                    !localOfferSent
                ) {
                    setDebug('Recipient accepted call');
                    await startCallerConnection();
                }
            }

            for (const signal of result.data?.signals || []) {
                await handleSignal(signal);
            }
        } catch (error) {
            console.error('Call polling failed', error);
        }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollCalls, 1500);
        pollCalls();
    }

    function showIncoming(call) {
        pendingIncoming = call;
        currentCall = call;
        prepareCallUi(
            call.call_type,
            `${call.call_type === 'video' ? 'Video' : 'Audio'} call`,
            `${call.other_name} is calling`,
            call.other_name
        );
        setControls('incoming');
        setButtonsDisabled(true);
        setDebug(`Incoming ${call.call_type} call ${call.id}`);
        startRingingCue(call.call_type);
    }

    async function startOutgoing(callType) {
        if (!selectedUserId || currentCall) return;

        try {
            prepareCallUi(
                callType,
                `${callType === 'video' ? 'Video' : 'Audio'} call`,
                `Calling ${selectedUserName}...`
            );
            setControls('active');
            setButtonsDisabled(true);

            const start = await callApi('start', { recipient_id: selectedUserId, call_type: callType });

            if (!start.success) {
                throw new Error(start.message || 'Unable to start call');
            }

            currentCall = {
                id: start.data.call_id,
                caller_id: currentUserId,
                recipient_id: selectedUserId,
                call_type: callType,
                status: 'ringing'
            };
            setControls('active');
            setStatus(`Ringing ${selectedUserName}...`);
            setDebug(`Call ${currentCall.id} created, waiting for recipient`);
            startPolling();
        } catch (error) {
            setStatus(error.message || 'Unable to start call');
            setDebug(error.message || 'Unable to start call');
            setTimeout(cleanupCall, 2200);
        }
    }

    async function startCallerConnection() {
        try {
            localOfferSent = true;
            setStatus('Call accepted. Connecting...');
            await getMedia(currentCall.call_type);
            createPeer();
            const offer = await peer.createOffer();
            await peer.setLocalDescription(offer);
            await sendSignal('offer', offer);
            setDebug('Offer sent');
        } catch (error) {
            localOfferSent = false;
            setStatus(error.message || 'Unable to connect call');
            setDebug(error.message || 'Unable to connect call');
            await endCurrentCall('end');
        }
    }

    async function acceptIncoming() {
        if (!pendingIncoming) return;

        try {
            setControls('active');
            setStatus('Starting call...');
            stopRingingCue();
            await getMedia(pendingIncoming.call_type);
            createPeer();

            await callApi('accept', { call_id: pendingIncoming.id });
            setStatus('Call accepted. Waiting for connection...');
            setDebug(`Call ${pendingIncoming.id} accepted`);
            pendingIncoming = null;
            startPolling();
        } catch (error) {
            setStatus(error.message || 'Unable to answer call');
            setDebug(error.message || 'Unable to answer call');
            await endCurrentCall('end');
        }
    }

    async function endCurrentCall(action = 'end') {
        if (currentCall) {
            await callApi(action, { call_id: currentCall.id }).catch(console.error);
        }
        cleanupCall();
    }

    function cleanupCall() {
        if (peer) {
            peer.close();
        }

        stopRingingCue();

        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
        }

        peer = null;
        localStream = null;
        currentCall = null;
        pendingIncoming = null;
        pendingIceCandidates = [];
        localOfferSent = false;
        localAnswerSent = false;
        lastSignalId = 0;
        isMuted = false;
        isCameraOff = false;
        localVideo.srcObject = null;
        remoteVideo.srcObject = null;
        remoteAudio.srcObject = null;
        muteBtn.textContent = 'Mute';
        cameraBtn.textContent = 'Camera Off';
        cameraBtn.disabled = false;
        switchCameraBtn.textContent = 'Switch Camera';
        switchCameraBtn.disabled = false;
        endBtn.textContent = 'End Call';
        dialog.classList.remove('audio-mode');
        setButtonsDisabled(false);
        setModalVisible(false);
    }

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-call-type]');
        if (!button) return;

        if (!supportsCalling) {
            prepareCallUi(
                button.dataset.callType,
                'Calling unavailable',
                'Audio and video calls require a modern browser on localhost or HTTPS.'
            );
            setControls('active');
            muteBtn.hidden = true;
            cameraBtn.hidden = true;
            switchCameraBtn.hidden = true;
            endBtn.textContent = 'Close';
            return;
        }

        startOutgoing(button.dataset.callType);
    });

    acceptBtn.addEventListener('click', acceptIncoming);
    declineBtn.addEventListener('click', () => endCurrentCall('decline'));
    endBtn.addEventListener('click', () => {
        endBtn.textContent = 'End Call';
        endCurrentCall('end');
    });

    muteBtn.addEventListener('click', () => {
        if (!localStream) return;
        isMuted = !isMuted;
        localStream.getAudioTracks().forEach(track => {
            track.enabled = !isMuted;
        });
        muteBtn.textContent = isMuted ? 'Unmute' : 'Mute';
    });

    cameraBtn.addEventListener('click', () => {
        if (!localStream) return;
        const videoTracks = localStream.getVideoTracks();
        if (videoTracks.length === 0) {
            cameraBtn.textContent = 'No Camera';
            cameraBtn.disabled = true;
            return;
        }

        isCameraOff = !isCameraOff;
        videoTracks.forEach(track => {
            track.enabled = !isCameraOff;
        });
        cameraBtn.textContent = isCameraOff ? 'Camera On' : 'Camera Off';
    });

    switchCameraBtn.addEventListener('click', () => {
        switchCamera().catch(error => {
            setStatus('Unable to switch camera.');
            setDebug(`Unable to switch camera: ${error.message}`);
        });
    });

    if (currentUserId) {
        startPolling();
    }
})();
