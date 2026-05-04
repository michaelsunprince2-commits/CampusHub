<?php

/**
 * Messages Page
 */

$pageTitle = 'Messages';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth();

require_once '../models/Message.php';

$messageModel = new Message($conn);
$conversations = $messageModel->getConversations(getCurrentUserId(), 20, 0);
$selectedUserId = $_GET['user_id'] ?? null;
$messages = [];
$selectedUser = null;

if ($selectedUserId) {
    $messages = $messageModel->getConversation(getCurrentUserId(), (int)$selectedUserId, 50, 0);
    $messages = array_reverse($messages);

    $userStmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $userStmt->bind_param("i", $selectedUserId);
    $userStmt->execute();
    $selectedUser = $userStmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedUserId) {
    $content = $_POST['message'] ?? '';

    if (!empty($content)) {
        $messageModel->send(getCurrentUserId(), (int)$selectedUserId, $content);
        header('Location: ' . pageUrl('messages.php') . '?user_id=' . $selectedUserId);
        exit();
    }
}

require_once '../templates/header.php';
?>

<style>
    .messages-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 1rem;
        height: 600px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .conversations-list {
        border-right: 1px solid #ecf0f1;
        overflow-y: auto;
    }

    .conversation-item {
        padding: 1rem;
        border-bottom: 1px solid #ecf0f1;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .conversation-item:hover {
        background-color: #f5f5f5;
    }

    .conversation-item.active {
        background-color: #d1ecf1;
    }

    .conversation-name {
        font-weight: 600;
    }

    .conversation-preview {
        font-size: 0.9rem;
        color: #7f8c8d;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .messages-view {
        display: flex;
        flex-direction: column;
    }

    .messages-header {
        padding: 1rem 1.15rem;
        border-bottom: 1px solid #dfe8ee;
        background: linear-gradient(180deg, #ffffff 0%, #f3f7f9 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .messages-header h3 {
        margin-bottom: 0;
    }

    .messages-header small {
        color: #657786;
    }

    .message-call-actions {
        display: flex;
        gap: 0.65rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .call-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        min-height: 42px;
        border: 1px solid #c8d8df;
        border-radius: 8px;
        background: #ffffff;
        color: #243342;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 800;
        padding: 0.6rem 0.9rem 0.6rem 0.65rem;
        box-shadow: 0 3px 10px rgba(44, 62, 80, 0.06);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background 0.2s ease;
    }

    .call-btn::before {
        content: "";
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-block;
        flex: 0 0 auto;
        background: #eef7f4;
        color: #1e8449;
        background-color: #eef7f4;
        background-repeat: no-repeat;
        background-position: center;
        background-size: 16px 16px;
    }

    .call-btn[data-call-type="audio"]::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%231e8449' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.68 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.25a2 2 0 0 1 2.11-.45c.91.32 1.85.55 2.81.68A2 2 0 0 1 22 16.92z'/%3E%3C/svg%3E");
    }

    .call-btn[data-call-type="video"]::before {
        background: #eef5f6;
        background-color: #eef5f6;
        background-repeat: no-repeat;
        background-position: center;
        background-size: 17px 17px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%231f6f78' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m16 13 5.22 3.48A.5.5 0 0 0 22 16.06V7.94a.5.5 0 0 0-.78-.42L16 11'/%3E%3Crect x='2' y='6' width='14' height='12' rx='2' ry='2'/%3E%3C/svg%3E");
    }

    .call-btn:hover {
        background: #fbffff;
        border-color: #1f6f78;
        box-shadow: 0 8px 18px rgba(44, 62, 80, 0.12);
        transform: translateY(-1px);
    }

    .call-btn:focus {
        outline: none;
        border-color: #1f6f78;
        box-shadow: 0 0 0 4px rgba(31, 111, 120, 0.14);
    }

    .call-btn:disabled {
        cursor: not-allowed;
        opacity: 0.62;
        transform: none;
        box-shadow: none;
    }

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

    .call-video-grid {
        display: grid;
        grid-template-columns: 1fr 180px;
        gap: 0.75rem;
        padding: 1rem;
        background: #17212b;
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

    .call-unsupported {
        margin-top: 0.5rem;
        color: #c0392b;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .messages-body {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .message-row {
        display: flex;
        margin-bottom: 1rem;
    }

    .message-row.sent {
        justify-content: flex-end;
    }

    .message-content {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        background-color: #ecf0f1;
    }

    .message-row.sent .message-content {
        background-color: #3498db;
        color: white;
    }

    .messages-footer {
        padding: 1rem;
        border-top: 1px solid #ecf0f1;
        display: flex;
        gap: 0.5rem;
    }

    .messages-footer input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .no-conversation {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #7f8c8d;
    }

    @media (max-width: 768px) {
        .messages-container {
            grid-template-columns: 1fr;
            height: auto;
            min-height: 600px;
        }

        .conversations-list {
            display: none;
        }

        .conversations-list.show {
            display: block;
        }

        .messages-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .message-call-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .call-video-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<h1>Messages</h1>

<?php
$selectedUserName = $selectedUser ? trim(($selectedUser['first_name'] ?? '') . ' ' . ($selectedUser['last_name'] ?? '')) : '';
?>

<div class="messages-container"
    data-call-root
    data-call-api-url="<?php echo getBaseUrl(); ?>/php/api/calls.php"
    data-current-user-id="<?php echo (int)getCurrentUserId(); ?>"
    data-selected-user-id="<?php echo $selectedUser ? (int)$selectedUser['id'] : 0; ?>"
    data-selected-user-name="<?php echo htmlspecialchars($selectedUserName ?: 'this user'); ?>">
    <div class="conversations-list">
        <h3 style="padding: 1rem; border-bottom: 1px solid #ecf0f1;">Conversations</h3>
        <?php if (empty($conversations)): ?>
            <p style="padding: 1rem; color: #7f8c8d; text-align: center;">No conversations yet</p>
        <?php else: ?>
            <?php foreach ($conversations as $conv): ?>
                <div class="conversation-item <?php echo ($conv['user_id'] == $selectedUserId) ? 'active' : ''; ?>"
                    onclick="window.location.href='<?php echo pageUrl('messages.php?user_id=' . $conv['user_id']); ?>'">
                    <div class="conversation-name"><?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?></div>
                    <div class="conversation-preview"><?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages', 0, 50)); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="messages-view">
        <?php if ($selectedUserId): ?>
            <div class="messages-header">
                <div>
                    <h3><?php echo htmlspecialchars($selectedUserName ?: 'Chat'); ?></h3>
                    <small>Messages, audio calls, and video calls</small>
                </div>
                <div class="message-call-actions">
                    <button type="button" class="call-btn" data-call-type="audio">Audio Call</button>
                    <button type="button" class="call-btn" data-call-type="video">Video Call</button>
                </div>
            </div>

            <div class="messages-body">
                <?php if (empty($messages)): ?>
                    <div class="no-conversation">No messages yet</div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-row <?php echo ($msg['sender_id'] == getCurrentUserId()) ? 'sent' : ''; ?>">
                            <div class="message-content">
                                <?php echo htmlspecialchars($msg['content']); ?>
                                <small style="opacity: 0.7; display: block; margin-top: 0.25rem;">
                                    <?php echo formatDate($msg['created_at'], 'H:i'); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="messages-footer">
                <form method="post" style="display: flex; width: 100%; gap: 0.5rem;">
                    <input type="text" name="message" placeholder="Type a message..." required>
                    <button type="submit" class="btn">Send</button>
                </form>
            </div>
        <?php else: ?>
            <div class="no-conversation">
                Select a conversation to start messaging
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
