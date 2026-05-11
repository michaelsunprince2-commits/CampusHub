        </div>
        </main>

        <footer>
            <div class="container">
                <div class="footer-content">
                    <div class="footer-brand">
                        <h2>CampusNest</h2>
                        <p>CampusNest helps students find suitable housing near campus while giving verified landlords a trusted place to list available rooms and apartments.</p>
                        <p>Built for simple property discovery, secure booking steps, landlord verification, messaging, and community reviews.</p>
                    </div>

                    <div class="footer-column">
                        <h3>Explore</h3>
                        <ul>
                            <li><a href="<?php echo pageUrl('index.php'); ?>">Home</a></li>
                            <li><a href="<?php echo pageUrl('properties.php'); ?>">Browse Properties</a></li>
                            <li><a href="<?php echo pageUrl('reviews.php'); ?>">Platform Reviews</a></li>
                            <li><a href="<?php echo pageUrl('platform-review.php'); ?>">Share Feedback</a></li>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h3>For Users</h3>
                        <ul>
                            <?php if (isAuthenticated()): ?>
                                <li><a href="<?php echo pageUrl('profile.php'); ?>">My Profile</a></li>
                                <li><a href="<?php echo pageUrl('messages.php'); ?>">Messages</a></li>
                                <?php if (getCurrentUserRole() === 'landlord'): ?>
                                    <li><a href="<?php echo pageUrl('landlord-dashboard.php'); ?>">Manage Listings</a></li>
                                <?php elseif (getCurrentUserRole() === 'student'): ?>
                                    <li><a href="<?php echo pageUrl('bookings.php'); ?>">My Bookings</a></li>
                                <?php endif; ?>
                            <?php else: ?>
                                <li><a href="<?php echo pageUrl('register.php'); ?>">Create Account</a></li>
                                <li><a href="<?php echo pageUrl('login.php'); ?>">Student Login</a></li>
                                <li><a href="<?php echo pageUrl('register.php'); ?>">Landlord Sign Up</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h3>Contact Us</h3>
                        <p>Need help with a listing, booking, verification, or account issue?</p>
                        <ul>
                            <li><a href="mailto:support@campusnest.local">support@campusnest.local</a></li>
                            <li><a href="<?php echo pageUrl('terms.php'); ?>">Terms of Service</a></li>
                            <li><a href="<?php echo pageUrl('security-policy.php'); ?>">Security Policy</a></li>
                            <li>Campus housing support for students and landlords</li>
                        </ul>
                    </div>
                </div>

                <div class="footer-bottom">
                    <span>&copy; <?php echo date('Y'); ?> CampusNest. All rights reserved.</span>
                    <span>Student housing made simpler, clearer, and more trustworthy.</span>
                </div>
            </div>
        </footer>

        <?php if (isAuthenticated()): ?>
            <style>
                .call-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 10000;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    padding: 1rem;
                    background: rgba(18, 28, 38, 0.72);
                }

                .call-modal.is-open {
                    display: flex;
                }

                .call-dialog {
                    width: min(760px, 100%);
                    border-radius: 8px;
                    background: #ffffff;
                    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
                    overflow: hidden;
                }

                .call-dialog-header {
                    padding: 1.15rem 1.25rem;
                    border-bottom: 1px solid #e8eef2;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
                }

                .call-dialog-header h3 {
                    margin-bottom: 0.2rem;
                }

                .call-dialog-header p {
                    margin-bottom: 0;
                    color: #657786;
                }

                .call-debug {
                    margin-top: 0.45rem;
                    color: #7a8b96;
                    font-size: 0.82rem;
                }

                .call-video-grid {
                    display: grid;
                    grid-template-columns: 1fr 180px;
                    gap: 0.75rem;
                    padding: 1rem;
                    background: #17212b;
                }

                .call-video-grid video {
                    width: 100%;
                    min-height: 220px;
                    max-height: 420px;
                    border-radius: 8px;
                    background: #0b1117;
                    object-fit: cover;
                }

                .call-video-grid #local-video {
                    min-height: 120px;
                }

                .call-audio-panel {
                    display: none;
                    padding: 2.5rem 1rem;
                    background: #17212b;
                    color: #ffffff;
                    text-align: center;
                }

                .call-audio-avatar {
                    width: 92px;
                    height: 92px;
                    border-radius: 50%;
                    display: grid;
                    place-items: center;
                    margin: 0 auto 1rem;
                    background: #1f6f78;
                    color: #ffffff;
                    font-size: 1.7rem;
                    font-weight: 800;
                    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.24);
                }

                .call-audio-panel p {
                    margin-bottom: 0;
                    color: #cbd8df;
                }

                .call-dialog.audio-mode .call-video-grid {
                    display: none;
                }

                .call-dialog.audio-mode .call-audio-panel {
                    display: block;
                }

                .call-actions {
                    display: flex;
                    gap: 0.65rem;
                    flex-wrap: wrap;
                    justify-content: flex-end;
                    padding: 1rem;
                    border-top: 1px solid #e8eef2;
                }

                .call-actions button {
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 800;
                    padding: 0.75rem 1rem;
                    transition: filter 0.2s ease, transform 0.2s ease;
                }

                .call-actions button:hover {
                    filter: brightness(0.97);
                    transform: translateY(-1px);
                }

                .call-actions .call-accept {
                    background: #27ae60;
                    color: #ffffff;
                }

                .call-actions .call-secondary {
                    background: #eef3f6;
                    color: #243342;
                }

                .call-actions .call-end {
                    background: #e74c3c;
                    color: #ffffff;
                }

                @media (max-width: 768px) {
                    .call-video-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <div data-call-root
                data-call-api-url="<?php echo getBaseUrl(); ?>/php/api/calls.php"
                data-current-user-id="<?php echo (int)getCurrentUserId(); ?>"
                data-selected-user-id="0"
                data-selected-user-name="this user"
                hidden></div>

            <div class="call-modal" id="call-modal" hidden>
                <div class="call-dialog" id="call-dialog" role="dialog" aria-modal="true" aria-labelledby="call-title">
                    <div class="call-dialog-header">
                        <h3 id="call-title">Call</h3>
                        <p id="call-status">Preparing call...</p>
                        <div class="call-debug" id="call-debug">Waiting for call activity</div>
                    </div>
                    <div class="call-audio-panel">
                        <div class="call-audio-avatar" id="call-audio-avatar">CN</div>
                        <p>Audio call in progress</p>
                    </div>
                    <div class="call-video-grid">
                        <video id="remote-video" autoplay playsinline></video>
                        <video id="local-video" autoplay muted playsinline></video>
                    </div>
                    <audio id="remote-audio" autoplay playsinline></audio>
                    <div class="call-actions">
                        <button type="button" class="call-accept" id="accept-call">Accept</button>
                        <button type="button" class="call-end" id="decline-call">Decline</button>
                        <button type="button" class="call-secondary" id="mute-call">Mute</button>
                        <button type="button" class="call-secondary" id="camera-call">Camera Off</button>
                        <button type="button" class="call-secondary" id="switch-camera-call">Switch Camera</button>
                        <button type="button" class="call-end" id="end-call">End Call</button>
                    </div>
                </div>
            </div>

            <script src="<?php echo getBaseUrl(); ?>/php/public/js/calls.js"></script>
        <?php endif; ?>

        <script src="<?php echo getBaseUrl(); ?>/php/public/js/main.js"></script>
        </body>

        </html>
