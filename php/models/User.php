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
        $stmt = $this->conn->prepare("
            SELECT u.*, 
                   CASE 
                       WHEN u.role = 'landlord' THEN (SELECT rating FROM landlord_profiles WHERE user_id = u.id)
                       ELSE NULL
                   END as rating
            FROM users u 
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
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
}
