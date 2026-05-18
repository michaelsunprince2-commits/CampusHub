<?php

/**
 * Messages API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Message.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$message = new Message($conn);

try {
    switch ($action) {
        case 'send':
            // Send message
            requireAuth();

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['recipient_id'], $data['content'])) {
                jsonResponse(false, 'Missing required fields');
            }

            $result = $message->send(
                getCurrentUserId(),
                (int)$data['recipient_id'],
                sanitizeInput($data['content']),
                $data['attachment_url'] ?? null
            );

            if ($result['success']) {
                $sender = getUserById($conn, getCurrentUserId());
                $senderName = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? '')) ?: 'CampusNest';
                sendPushNotificationToUser(
                    $conn,
                    (int)$data['recipient_id'],
                    'New message',
                    $senderName . ': ' . mb_substr(sanitizeInput($data['content']), 0, 80),
                    ['type' => 'message', 'userId' => getCurrentUserId()]
                );
            }

            jsonResponse($result['success'], $result['message'], ['message_id' => $result['message_id'] ?? null]);
            break;

        case 'conversation':
            // Get conversation with user
            requireAuth();

            if (!isset($_GET['user_id'])) {
                jsonResponse(false, 'User ID required');
            }

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            $results = $message->getConversation(getCurrentUserId(), (int)$_GET['user_id'], $limit, $offset);
            jsonResponse(true, 'Conversation retrieved', ['messages' => $results]);
            break;

        case 'conversations':
            // Get list of conversations
            requireAuth();

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            $results = $message->getConversations(getCurrentUserId(), $limit, $offset);
            jsonResponse(true, 'Conversations retrieved', ['conversations' => $results]);
            break;

        case 'unread-count':
            // Get unread message count
            requireAuth();

            $count = $message->getUnreadCount(getCurrentUserId());
            jsonResponse(true, 'Unread count', ['count' => $count]);
            break;

        case 'mark-read':
            // Mark message as read
            requireAuth();

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Message ID required');
            }

            $success = $message->markAsRead((int)$_GET['id']);
            jsonResponse($success, $success ? 'Message marked as read' : 'Failed to mark as read');
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
