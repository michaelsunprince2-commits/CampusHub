<?php

/**
 * Platform Review Model
 * Handles user reviews and ratings about the CampusNest platform
 */

class PlatformReview
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Submit a new platform review
     */
    public function create($userId, $role, $rating, $title, $comment)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO platform_reviews (user_id, user_role, rating, title, comment, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->bind_param("isiss", $userId, $role, $rating, $title, $comment);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review submitted successfully and is awaiting admin approval', 'review_id' => $this->conn->insert_id];
        }

        return ['success' => false, 'message' => 'Failed to submit review'];
    }

    /**
     * Get review by ID
     */
    public function getById($reviewId)
    {
        $stmt = $this->conn->prepare("
            SELECT pr.*, u.first_name, u.last_name, u.profile_picture
            FROM platform_reviews pr
            LEFT JOIN users u ON pr.user_id = u.id
            WHERE pr.id = ?
        ");
        $stmt->bind_param("i", $reviewId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get approved reviews with pagination
     */
    public function getApprovedReviews($limit = 10, $offset = 0, $role = null)
    {
        $query = "
            SELECT pr.*, u.first_name, u.last_name, u.profile_picture
            FROM platform_reviews pr
            LEFT JOIN users u ON pr.user_id = u.id
            WHERE pr.status = 'approved'
        ";

        $params = [];
        $types = '';

        if (!empty($role)) {
            $query .= " AND pr.user_role = ?";
            $params[] = $role;
            $types .= 's';
        }

        $query .= " ORDER BY pr.created_at DESC LIMIT ? OFFSET ?";
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
     * Check if user has already reviewed
     */
    public function hasUserReviewed($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT id FROM platform_reviews
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Get user's review if exists
     */
    public function getUserReview($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM platform_reviews
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Update review
     */
    public function update($reviewId, $userId, $rating, $title, $comment)
    {
        // Verify ownership
        $stmt = $this->conn->prepare("SELECT user_id FROM platform_reviews WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || $result['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $stmt = $this->conn->prepare("
            UPDATE platform_reviews
            SET rating = ?, title = ?, comment = ?, status = 'pending'
            WHERE id = ?
        ");
        $stmt->bind_param("issi", $rating, $title, $comment, $reviewId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review updated successfully'];
        }

        return ['success' => false, 'message' => 'Failed to update review'];
    }

    /**
     * Delete review
     */
    public function delete($reviewId, $userId)
    {
        // Verify ownership
        $stmt = $this->conn->prepare("SELECT user_id FROM platform_reviews WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || $result['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $stmt = $this->conn->prepare("DELETE FROM platform_reviews WHERE id = ?");
        $stmt->bind_param("i", $reviewId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review deleted successfully'];
        }

        return ['success' => false, 'message' => 'Failed to delete review'];
    }

    /**
     * Get average rating
     */
    public function getAverageRating($role = null)
    {
        $query = "
            SELECT AVG(rating) as avg_rating, COUNT(id) as total_reviews
            FROM platform_reviews
            WHERE status = 'approved'
        ";

        if (!empty($role)) {
            $query .= " AND user_role = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $role);
        } else {
            $stmt = $this->conn->prepare($query);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get rating distribution
     */
    public function getRatingDistribution($role = null)
    {
        $query = "
            SELECT rating, COUNT(id) as count
            FROM platform_reviews
            WHERE status = 'approved'
        ";

        if (!empty($role)) {
            $query .= " AND user_role = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $role);
        } else {
            $stmt = $this->conn->prepare($query);
        }

        $query .= " GROUP BY rating ORDER BY rating DESC";
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get reviews for admin dashboard
     */
    public function getPendingReviews($limit = 10, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT pr.*, u.first_name, u.last_name, u.email
            FROM platform_reviews pr
            LEFT JOIN users u ON pr.user_id = u.id
            WHERE pr.status = 'pending'
            ORDER BY pr.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Approve review (admin only)
     */
    public function approveReview($reviewId)
    {
        $stmt = $this->conn->prepare("
            UPDATE platform_reviews
            SET status = 'approved'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $reviewId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review approved'];
        }

        return ['success' => false, 'message' => 'Failed to approve review'];
    }

    /**
     * Reject review (admin only)
     */
    public function rejectReview($reviewId)
    {
        $stmt = $this->conn->prepare("
            UPDATE platform_reviews
            SET status = 'rejected'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $reviewId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review rejected'];
        }

        return ['success' => false, 'message' => 'Failed to reject review'];
    }
}
