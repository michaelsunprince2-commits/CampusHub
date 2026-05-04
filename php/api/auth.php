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

                jsonResponse(true, $result['message'], $result['user']);
            } else {
                jsonResponse(false, $result['message']);
            }
            break;

        case 'logout':
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

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
