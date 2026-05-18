<?php

/**
 * User Model
 */

class User
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    private function tableExists($tableName)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS count
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("s", $tableName);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return (int)($result['count'] ?? 0) > 0;
    }

    /**
     * Register a new user
     */
    public function register($email, $password, $firstName, $lastName, $role = 'student')
    {
        // Sanitize and validate input
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $role = in_array($role, ['student', 'landlord'], true) ? $role : 'student';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        // Check if user exists
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $stmt = $this->conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $email, $passwordHash, $firstName, $lastName, $role);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $this->conn->insert_id];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    /**
     * Login user
     */
    public function login($email, $password)
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        $stmt = $this->conn->prepare("SELECT id, password_hash, first_name, last_name, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $email,
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'role' => $user['role']
            ]
        ];
    }

    /**
     * Get user profile
     */
    public function getProfile($userId)
    {
        $stmt = $this->conn->prepare("SELECT u.*, NULL AS rating FROM users u WHERE u.id = ?");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();

        if (!$profile) {
            return null;
        }

        if (($profile['role'] ?? '') === 'landlord' && $this->tableExists('landlord_profiles')) {
            $stmt = $this->conn->prepare("SELECT rating FROM landlord_profiles WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $landlordProfile = $stmt->get_result()->fetch_assoc();
                $profile['rating'] = $landlordProfile['rating'] ?? null;
            }
        }

        return $profile;
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        $updates = [];
        $params = [];
        $types = '';

        foreach ($data as $key => $value) {
            if (in_array($key, ['first_name', 'last_name', 'phone', 'bio', 'profile_picture'])) {
                $updates[] = "$key = ?";
                $params[] = $value;
                $types .= 's';
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }

        $params[] = $userId;
        $types .= 'i';

        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Profile updated successfully'];
        }

        return ['success' => false, 'message' => 'Update failed'];
    }

    /**
     * List users with role filter
     */
    public function listUsers($role = null, $limit = 10, $offset = 0)
    {
        $query = "SELECT id, email, first_name, last_name, role, is_verified, created_at FROM users";
        $params = [];
        $types = '';

        if ($role) {
            $query .= " WHERE role = ?";
            $params[] = $role;
            $types .= 's';
        }

        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Verify user (by admin)
     */
    public function verifyUser($userId, $verifiedBy)
    {
        $verifiedAt = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("UPDATE users SET is_verified = true, verification_date = ?, verified_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $verifiedAt, $verifiedBy, $userId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'User verified'];
        }

        return ['success' => false, 'message' => 'Verification failed'];
    }

    public function createPasswordResetToken($email)
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $stmt = $this->conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            return null;
        }

        $this->ensurePasswordResetTable();
        $this->expireUserPasswordResetTokens((int)$user['id']);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user['id'], $tokenHash, $expiresAt);

        if (!$stmt->execute()) {
            return null;
        }

        return [
            'email' => $email,
            'first_name' => $user['first_name'],
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function getValidPasswordReset($token)
    {
        $tokenHash = hash('sha256', trim((string)$token));
        $this->ensurePasswordResetTable();

        $stmt = $this->conn->prepare("
            SELECT pr.id, pr.user_id, u.email, u.first_name
            FROM password_resets pr
            INNER JOIN users u ON u.id = pr.user_id
            WHERE pr.token_hash = ?
              AND pr.used_at IS NULL
              AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    public function resetPassword($token, $password)
    {
        if (!is_string($password) || strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        $reset = $this->getValidPasswordReset($token);

        if (!$reset) {
            return ['success' => false, 'message' => 'This reset link is invalid or has expired'];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $userId = (int)$reset['user_id'];
        $resetId = (int)$reset['id'];
        $usedAt = date('Y-m-d H:i:s');

        $this->conn->begin_transaction();

        try {
            $stmt = $this->conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $passwordHash, $userId);
            $stmt->execute();

            $stmt = $this->conn->prepare("UPDATE password_resets SET used_at = ? WHERE id = ?");
            $stmt->bind_param("si", $usedAt, $resetId);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM sessions WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $this->conn->commit();
        } catch (Throwable $error) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Unable to reset password. Please try again.'];
        }

        return ['success' => true, 'message' => 'Password reset successful. You can now log in.'];
    }

    private function expireUserPasswordResetTokens($userId)
    {
        $stmt = $this->conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    private function ensurePasswordResetTable()
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_token_hash (token_hash),
                INDEX idx_user (user_id),
                INDEX idx_expires (expires_at)
            )
        ");
    }
}
