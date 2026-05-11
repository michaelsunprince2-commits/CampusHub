<?php

/**
 * Review Model
 */

class Review
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create review
     */
    public function create($propertyId, $reviewerId, $rating, $title, $comment)
    {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }

        // Check if reviewer has booked the property
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM bookings
            WHERE property_id = ? AND student_id = ? AND status = 'completed'
        ");
        $stmt->bind_param("ii", $propertyId, $reviewerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ((int)$result['count'] === 0) {
            return ['success' => false, 'message' => 'You must have completed a booking to review'];
        }

        // Check if already reviewed
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM reviews
            WHERE property_id = ? AND reviewer_id = ?
        ");
        $stmt->bind_param("ii", $propertyId, $reviewerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ((int)$result['count'] > 0) {
            return ['success' => false, 'message' => 'You have already reviewed this property'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO reviews (property_id, reviewer_id, rating, title, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $propertyId, $reviewerId, $rating, $title, $comment);

        if ($stmt->execute()) {
            // Update property rating
            $this->updatePropertyRating($propertyId);
            return ['success' => true, 'message' => 'Review created', 'review_id' => $this->conn->insert_id];
        }

        return ['success' => false, 'message' => 'Review creation failed'];
    }

    /**
     * Get reviews for property
     */
    public function getPropertyReviews($propertyId, $limit = 10, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT r.*, u.first_name, u.last_name, u.profile_picture
            FROM reviews r
            LEFT JOIN users u ON r.reviewer_id = u.id
            WHERE r.property_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $propertyId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Update property rating
     */
    public function updatePropertyRating($propertyId)
    {
        $stmt = $this->conn->prepare("
            UPDATE properties 
            SET rating = (SELECT AVG(rating) FROM reviews WHERE property_id = ?),
                review_count = (SELECT COUNT(*) FROM reviews WHERE property_id = ?)
            WHERE id = ?
        ");
        $stmt->bind_param("iii", $propertyId, $propertyId, $propertyId);
        return $stmt->execute();
    }

    /**
     * Mark review helpful
     */
    public function markHelpful($reviewId)
    {
        $stmt = $this->conn->prepare("UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        return $stmt->execute();
    }

    /**
     * Delete review (owner only)
     */
    public function delete($reviewId, $userId)
    {
        $stmt = $this->conn->prepare("SELECT reviewer_id, property_id FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        $stmt->execute();
        $review = $stmt->get_result()->fetch_assoc();

        if (!$review || $review['reviewer_id'] != $userId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $stmt = $this->conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $reviewId);

        if ($stmt->execute()) {
            $this->updatePropertyRating($review['property_id']);
            return ['success' => true, 'message' => 'Review deleted'];
        }

        return ['success' => false, 'message' => 'Delete failed'];
    }
}
