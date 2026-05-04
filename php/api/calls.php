<?php

/**
 * Calls API Endpoint
 * Lightweight WebRTC signaling over MySQL polling.
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUserId = getCurrentUserId();

function ensureCallTables($conn)
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS calls (
            id INT PRIMARY KEY AUTO_INCREMENT,
            caller_id INT NOT NULL,
            recipient_id INT NOT NULL,
            call_type ENUM('audio', 'video') NOT NULL DEFAULT 'audio',
            status ENUM('ringing', 'accepted', 'declined', 'ended', 'missed') NOT NULL DEFAULT 'ringing',
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            answered_at TIMESTAMP NULL,
            ended_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_caller (caller_id),
            INDEX idx_recipient (recipient_id),
            INDEX idx_status (status),
            INDEX idx_updated_at (updated_at)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS call_signals (
            id INT PRIMARY KEY AUTO_INCREMENT,
            call_id INT NOT NULL,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            signal_type ENUM('offer', 'answer', 'ice') NOT NULL,
            payload LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_call_recipient (call_id, recipient_id),
            INDEX idx_created_at (created_at)
        )
    ");
}

function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function getCall($conn, $callId, $userId)
{
    $stmt = $conn->prepare("
        SELECT c.*, 
               caller.first_name AS caller_first_name,
               caller.last_name AS caller_last_name,
               recipient.first_name AS recipient_first_name,
               recipient.last_name AS recipient_last_name
        FROM calls c
        JOIN users caller ON caller.id = c.caller_id
        JOIN users recipient ON recipient.id = c.recipient_id
        WHERE c.id = ? AND (c.caller_id = ? OR c.recipient_id = ?)
        LIMIT 1
    ");
    $stmt->bind_param("iii", $callId, $userId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function formatCall($call, $currentUserId)
{
    $otherName = ((int)$call['caller_id'] === (int)$currentUserId)
        ? trim(($call['recipient_first_name'] ?? '') . ' ' . ($call['recipient_last_name'] ?? ''))
        : trim(($call['caller_first_name'] ?? '') . ' ' . ($call['caller_last_name'] ?? ''));

    return [
        'id' => (int)$call['id'],
        'caller_id' => (int)$call['caller_id'],
        'recipient_id' => (int)$call['recipient_id'],
        'call_type' => $call['call_type'],
        'status' => $call['status'],
        'other_name' => $otherName ?: 'CampusNest user',
        'started_at' => $call['started_at'],
    ];
}

ensureCallTables($conn);
$conn->query("UPDATE calls SET status = 'missed', ended_at = NOW() WHERE status = 'ringing' AND started_at < (NOW() - INTERVAL 2 MINUTE)");

try {
    switch ($action) {
        case 'start':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = getJsonInput();
            $recipientId = (int)($data['recipient_id'] ?? 0);
            $callType = ($data['call_type'] ?? 'audio') === 'video' ? 'video' : 'audio';

            if ($recipientId <= 0 || $recipientId === (int)$currentUserId) {
                jsonResponse(false, 'Invalid call recipient');
            }

            $stmt = $conn->prepare("INSERT INTO calls (caller_id, recipient_id, call_type) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $currentUserId, $recipientId, $callType);
            $stmt->execute();

            jsonResponse(true, 'Call started', ['call_id' => $conn->insert_id]);
            break;

        case 'accept':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = getJsonInput();
            $callId = (int)($data['call_id'] ?? 0);
            $call = getCall($conn, $callId, $currentUserId);

            if (!$call || (int)$call['recipient_id'] !== (int)$currentUserId) {
                jsonResponse(false, 'Call not found');
            }

            $stmt = $conn->prepare("UPDATE calls SET status = 'accepted', answered_at = NOW() WHERE id = ? AND status = 'ringing'");
            $stmt->bind_param("i", $callId);
            $stmt->execute();

            jsonResponse(true, 'Call accepted');
            break;

        case 'decline':
        case 'end':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = getJsonInput();
            $callId = (int)($data['call_id'] ?? 0);
            $call = getCall($conn, $callId, $currentUserId);

            if (!$call) {
                jsonResponse(false, 'Call not found');
            }

            $status = $action === 'decline' ? 'declined' : 'ended';
            $stmt = $conn->prepare("UPDATE calls SET status = ?, ended_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $status, $callId);
            $stmt->execute();

            jsonResponse(true, $action === 'decline' ? 'Call declined' : 'Call ended');
            break;

        case 'signal':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = getJsonInput();
            $callId = (int)($data['call_id'] ?? 0);
            $signalType = $data['signal_type'] ?? '';
            $payload = $data['payload'] ?? null;
            $call = getCall($conn, $callId, $currentUserId);

            if (!$call || !in_array($signalType, ['offer', 'answer', 'ice'], true) || $payload === null) {
                jsonResponse(false, 'Invalid signal');
            }

            $recipientId = (int)$call['caller_id'] === (int)$currentUserId ? (int)$call['recipient_id'] : (int)$call['caller_id'];
            $payloadJson = json_encode($payload);
            $stmt = $conn->prepare("INSERT INTO call_signals (call_id, sender_id, recipient_id, signal_type, payload) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $callId, $currentUserId, $recipientId, $signalType, $payloadJson);
            $stmt->execute();

            jsonResponse(true, 'Signal saved', ['signal_id' => $conn->insert_id]);
            break;

        case 'poll':
            $callId = (int)($_GET['call_id'] ?? 0);
            $sinceId = (int)($_GET['since_id'] ?? 0);
            $response = ['incoming_call' => null, 'call' => null, 'signals' => []];

            if ($callId > 0) {
                $call = getCall($conn, $callId, $currentUserId);
                if ($call) {
                    $response['call'] = formatCall($call, $currentUserId);

                    $stmt = $conn->prepare("
                        SELECT id, signal_type, payload
                        FROM call_signals
                        WHERE call_id = ? AND recipient_id = ? AND id > ?
                        ORDER BY id ASC
                    ");
                    $stmt->bind_param("iii", $callId, $currentUserId, $sinceId);
                    $stmt->execute();
                    $signals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                    foreach ($signals as $signal) {
                        $response['signals'][] = [
                            'id' => (int)$signal['id'],
                            'signal_type' => $signal['signal_type'],
                            'payload' => json_decode($signal['payload'], true),
                        ];
                    }
                }
            }

            $stmt = $conn->prepare("
                SELECT c.*,
                       caller.first_name AS caller_first_name,
                       caller.last_name AS caller_last_name,
                       recipient.first_name AS recipient_first_name,
                       recipient.last_name AS recipient_last_name
                FROM calls c
                JOIN users caller ON caller.id = c.caller_id
                JOIN users recipient ON recipient.id = c.recipient_id
                WHERE c.recipient_id = ? AND c.status = 'ringing'
                ORDER BY c.started_at DESC
                LIMIT 1
            ");
            $stmt->bind_param("i", $currentUserId);
            $stmt->execute();
            $incoming = $stmt->get_result()->fetch_assoc();
            if ($incoming) {
                $response['incoming_call'] = formatCall($incoming, $currentUserId);
            }

            jsonResponse(true, 'Call updates retrieved', $response);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
