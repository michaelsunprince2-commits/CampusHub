<?php

/**
 * Payment Model
 */

class Payment
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create payment
     */
    public function create($bookingId, $amount, $paymentMethod)
    {
        $status = 'pending';
        $stmt = $this->conn->prepare("
            INSERT INTO payments (booking_id, amount, payment_method, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("idss", $bookingId, $amount, $paymentMethod, $status);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Payment created', 'payment_id' => $this->conn->insert_id];
        }

        return ['success' => false, 'message' => 'Payment creation failed'];
    }

    /**
     * Get payment
     */
    public function getById($paymentId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->bind_param("i", $paymentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get booking payment
     */
    public function getByBookingId($bookingId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE booking_id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get payment by transaction reference
     */
    public function getByTransactionId($transactionId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE transaction_id = ?");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Store or replace a payment gateway reference
     */
    public function setTransactionReference($paymentId, $transactionId)
    {
        $stmt = $this->conn->prepare("UPDATE payments SET transaction_id = ? WHERE id = ?");
        $stmt->bind_param("si", $transactionId, $paymentId);
        return $stmt->execute();
    }

    /**
     * Complete payment
     */
    public function complete($paymentId, $transactionId = null)
    {
        $status = 'completed';
        $paymentDate = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("UPDATE payments SET status = ?, transaction_id = ?, payment_date = ? WHERE id = ?");
        $stmt->bind_param("sssi", $status, $transactionId, $paymentDate, $paymentId);

        if ($stmt->execute()) {
            // Update booking status to confirmed
            $stmt = $this->conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = (SELECT booking_id FROM payments WHERE id = ?)");
            $stmt->bind_param("i", $paymentId);
            $stmt->execute();

            return ['success' => true, 'message' => 'Payment completed'];
        }

        return ['success' => false, 'message' => 'Payment update failed'];
    }

    /**
     * Fail payment
     */
    public function fail($paymentId)
    {
        $status = 'failed';
        $stmt = $this->conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $paymentId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Payment marked as failed'];
        }

        return ['success' => false, 'message' => 'Update failed'];
    }

    /**
     * Refund payment
     */
    public function refund($paymentId, $reason = '')
    {
        $status = 'refunded';
        $refundDate = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("UPDATE payments SET status = ?, refund_date = ?, refund_reason = ? WHERE id = ?");
        $stmt->bind_param("sssi", $status, $refundDate, $reason, $paymentId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Payment refunded'];
        }

        return ['success' => false, 'message' => 'Refund failed'];
    }

    /**
     * Get user payments
     */
    public function getUserPayments($userId, $limit = 20, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, b.property_id, pr.name as property_name
            FROM payments p
            LEFT JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN properties pr ON b.property_id = pr.id
            LEFT JOIN users u ON b.student_id = u.id
            WHERE u.id = ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
