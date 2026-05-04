<?php

/**
 * Helper Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 */
function isAuthenticated()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_token']);
}

/**
 * Get current user ID
 */
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole()
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth($allowedRoles = [])
{
    if (!isAuthenticated()) {
        header('Location: ' . pageUrl('login.php'));
        exit();
    }

    if (!empty($allowedRoles) && !in_array(getCurrentUserRole(), $allowedRoles)) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

/**
 * Hash password
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate session token
 */
function generateSessionToken()
{
    return bin2hex(random_bytes(32));
}

/**
 * JSON response
 */
function jsonResponse($success, $message, $data = null)
{
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

/**
 * Sanitize input
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validatePassword($password)
{
    return is_string($password) && strlen($password) >= 8;
}

/**
 * Validate role names
 */
function validateRole($role)
{
    return in_array($role, ['student', 'landlord', 'admin', 'committee'], true);
}

/**
 * Sanitize a single string input
 */
function sanitizeString($value, $maxLength = 255)
{
    return mb_substr(htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8'), 0, $maxLength);
}

/**
 * Sanitize an integer value
 */
function sanitizeInt($value, $default = null)
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered === false ? $default : (int)$filtered;
}

/**
 * Sanitize a float value
 */
function sanitizeFloat($value, $default = null)
{
    $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $filtered === false ? $default : (float)$filtered;
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'NGN')
{
    return '₦' . number_format((float)$amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y')
{
    return date($format, strtotime($date));
}

/**
 * Get user by email
 */
function getUserByEmail($conn, $email)
{
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get user by ID
 */
function getUserById($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Create session for user
 */
function createUserSession($conn, $userId, $sessionToken)
{
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $conn->prepare("INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $sessionToken, $ipAddress, $userAgent, $expiresAt);
    return $stmt->execute();
}

/**
 * Log audit action
 */
function logAudit($conn, $userId, $action, $entityType, $entityId, $changes = null)
{
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $changesJson = $changes ? json_encode($changes) : null;

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issis s", $userId, $action, $entityType, $entityId, $changesJson, $ipAddress);
    return $stmt->execute();
}

/**
 * Paginate results
 */
function paginate($totalItems, $perPage = 10, $page = 1)
{
    $totalPages = ceil($totalItems / $perPage);
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $totalItems,
        'perPage' => $perPage,
        'page' => $page,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'hasMore' => $page < $totalPages
    ];
}

/**
 * Get base URL for navigation links
 * Works whether accessed from root, /php/public/, or /php/api/.
 */
function getBaseUrl()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);

    // Remove known PHP app entry folders from path if they exist.
    $baseUrl = preg_replace('#/php/(public|api|templates|includes|config|models)/?$#', '', $scriptDir);

    return $protocol . '://' . $host . $baseUrl;
}

/**
 * Generate page URL
 * Works from both root and /php/public/
 */
function pageUrl($page)
{
    $baseUrl = getBaseUrl();
    return $baseUrl . '/php/public/' . $page;
}
