<?php

/**
 * Authentication API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/User.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$user = new User($conn);

try {
    switch ($action) {
        case 'register':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['email'], $data['password'], $data['firstName'], $data['lastName'])) {
                jsonResponse(false, 'Missing required fields');
            }

            if (!validateEmail($data['email'])) {
                jsonResponse(false, 'Invalid email format');
            }

            if (strlen($data['password']) < 6) {
                jsonResponse(false, 'Password must be at least 6 characters');
            }

            $result = $user->register(
                sanitizeInput($data['email']),
                $data['password'],
                sanitizeInput($data['firstName']),
                sanitizeInput($data['lastName']),
                $data['role'] ?? 'student'
            );

            jsonResponse($result['success'], $result['message'], ['user_id' => $result['user_id'] ?? null]);
            break;

        case 'login':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['email'], $data['password'])) {
                jsonResponse(false, 'Missing email or password');
            }

            $result = $user->login($data['email'], $data['password']);

            if ($result['success']) {
                $sessionToken = generateSessionToken();
                createUserSession($conn, $result['user']['id'], $sessionToken);

                $_SESSION['user_id'] = $result['user']['id'];
                $_SESSION['user_email'] = $result['user']['email'];
                $_SESSION['user_name'] = $result['user']['name'];
                $_SESSION['user_role'] = $result['user']['role'];
                $_SESSION['user_token'] = $sessionToken;

                jsonResponse(true, $result['message'], array_merge($result['user'], ['session_token' => $sessionToken]));
            } else {
                jsonResponse(false, $result['message']);
            }
            break;

        case 'logout':
            authenticateWithSessionToken($conn);
            if (!empty($_SESSION['user_token'])) {
                $stmt = $conn->prepare("DELETE FROM sessions WHERE session_token = ?");
                $stmt->bind_param("s", $_SESSION['user_token']);
                $stmt->execute();
            }
            session_destroy();
            jsonResponse(true, 'Logout successful');
            break;

        case 'check':
            if (isAuthenticated()) {
                $userInfo = $user->getProfile(getCurrentUserId());
                jsonResponse(true, 'Authenticated', $userInfo);
            } else {
                jsonResponse(false, 'Not authenticated');
            }
            break;

        case 'update-profile':
            authenticateWithSessionToken($conn);

            if (!isAuthenticated()) {
                jsonResponse(false, 'Authentication required');
            }

            if ($method !== 'PUT' && $method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                jsonResponse(false, 'Invalid profile data');
            }

            $profileData = [];
            foreach (['first_name', 'last_name', 'phone', 'bio'] as $field) {
                if (array_key_exists($field, $data)) {
                    $profileData[$field] = sanitizeInput((string)$data[$field]);
                }
            }

            if (isset($profileData['first_name']) && $profileData['first_name'] === '') {
                jsonResponse(false, 'First name is required');
            }

            if (isset($profileData['last_name']) && $profileData['last_name'] === '') {
                jsonResponse(false, 'Last name is required');
            }

            $result = $user->updateProfile(getCurrentUserId(), $profileData);

            if (!$result['success']) {
                jsonResponse(false, $result['message']);
            }

            $userInfo = $user->getProfile(getCurrentUserId());
            jsonResponse(true, $result['message'], $userInfo);
            break;

        case 'register-push-token':
            authenticateWithSessionToken($conn);

            if (!isAuthenticated()) {
                jsonResponse(false, 'Authentication required');
            }

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data) || empty($data['push_token'])) {
                jsonResponse(false, 'Push token is required');
            }

            $pushToken = sanitizeInput((string)$data['push_token']);
            $platform = sanitizeInput((string)($data['platform'] ?? 'unknown'));

            $conn->query("
                CREATE TABLE IF NOT EXISTS user_push_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    push_token VARCHAR(255) NOT NULL UNIQUE,
                    platform VARCHAR(32) NOT NULL DEFAULT 'unknown',
                    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $userId = getCurrentUserId();
            $stmt = $conn->prepare("
                INSERT INTO user_push_tokens (user_id, push_token, platform, last_used_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    platform = VALUES(platform),
                    last_used_at = NOW()
            ");
            $stmt->bind_param("iss", $userId, $pushToken, $platform);

            if (!$stmt->execute()) {
                jsonResponse(false, 'Unable to register push token');
            }

            jsonResponse(true, 'Push token registered');
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
