<?php

/**
 * Message Model
 */

class Message
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Send message
     */
    public function send($senderId, $recipientId, $content, $attachmentUrl = null)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO messages (sender_id, recipient_id, content, attachment_url)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $senderId, $recipientId, $content, $attachmentUrl);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Message sent', 'message_id' => $this->conn->insert_id];
        }

        return ['success' => false, 'message' => 'Failed to send message'];
    }

    /**
     * Get conversation with user
     */
    public function getConversation($userId, $otherUserId, $limit = 50, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM messages
            WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iiiiii", $userId, $otherUserId, $otherUserId, $userId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get conversations list
     */
    public function getConversations($userId, $limit = 20, $offset = 0)
    {
        // First get the list of users we've communicated with
        $stmt = $this->conn->prepare("
            SELECT DISTINCT 
                   CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END as user_id,
                   MAX(created_at) as last_message_time
            FROM messages
            WHERE sender_id = ? OR recipient_id = ?
            GROUP BY user_id
            ORDER BY last_message_time DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iiiii", $userId, $userId, $userId, $limit, $offset);
        $stmt->execute();
        $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get user details and last message for each conversation
        foreach ($conversations as &$conv) {
            $userStmt = $this->conn->prepare("
                SELECT id, first_name, last_name FROM users WHERE id = ?
            ");
            $userStmt->bind_param("i", $conv['user_id']);
            $userStmt->execute();
            $userResult = $userStmt->get_result()->fetch_assoc();

            if ($userResult) {
                $conv['first_name'] = $userResult['first_name'];
                $conv['last_name'] = $userResult['last_name'];
            }

            // Get last message
            $msgStmt = $this->conn->prepare("
                SELECT content FROM messages 
                WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $msgStmt->bind_param("iiii", $userId, $conv['user_id'], $conv['user_id'], $userId);
            $msgStmt->execute();
            $msgResult = $msgStmt->get_result()->fetch_assoc();
            $conv['last_message'] = $msgResult ? $msgResult['content'] : '';
        }

        return $conversations;
    }

    /**
     * Mark as read
     */
    public function markAsRead($messageId)
    {
        $readAt = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("UPDATE messages SET is_read = true, read_at = ? WHERE id = ?");
        $stmt->bind_param("si", $readAt, $messageId);
        return $stmt->execute();
    }

    /**
     * Get unread count
     */
    public function getUnreadCount($userId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = false");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'];
    }
}
