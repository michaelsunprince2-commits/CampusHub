<?php

/**
 * Booking Model
 */

class Booking
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a new booking
     */
    public function create($propertyId, $studentId, $checkInDate, $checkOutDate, $numberOfOccupants)
    {
        // Check if property exists and is available
        $stmt = $this->conn->prepare("SELECT price_per_month, verification_status FROM properties WHERE id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        $propertyResult = $stmt->get_result()->fetch_assoc();

        if (!$propertyResult) {
            return ['success' => false, 'message' => 'Property not found'];
        }

        if (($propertyResult['verification_status'] ?? '') !== 'verified') {
            return ['success' => false, 'message' => 'Only verified properties can be booked'];
        }

        try {
            $checkIn = new DateTime($checkInDate);
            $checkOut = new DateTime($checkOutDate);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Enter valid move-in and check-out dates'];
        }

        if ($checkOut <= $checkIn) {
            return ['success' => false, 'message' => 'Check-out date must be after move-in date'];
        }

        if ($numberOfOccupants < 1) {
            return ['success' => false, 'message' => 'Occupants must be at least 1'];
        }

        // Calculate total rent from the monthly price. Exact yearly stays count as
        // 12 months, while partial extra months are rounded up.
        $months = (($checkOut->format('Y') - $checkIn->format('Y')) * 12) + ($checkOut->format('n') - $checkIn->format('n'));
        if ((int)$checkOut->format('j') > (int)$checkIn->format('j')) {
            $months++;
        }
        $months = max(1, (int)$months);
        $totalPrice = (float)$propertyResult['price_per_month'] * $months;

        // Check for conflicts
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM bookings 
            WHERE property_id = ? 
            AND status IN ('confirmed', 'pending')
            AND (
                (check_in_date < ? AND check_out_date > ?)
            )
        ");
        $stmt->bind_param("iss", $propertyId, $checkOutDate, $checkInDate);
        $stmt->execute();
        $conflictResult = $stmt->get_result()->fetch_assoc();

        if ($conflictResult['count'] > 0) {
            return ['success' => false, 'message' => 'Property not available for selected dates'];
        }

        // Create booking
        $status = 'pending';
        $stmt = $this->conn->prepare("
            INSERT INTO bookings (property_id, student_id, check_in_date, check_out_date, number_of_occupants, total_price, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iissids", $propertyId, $studentId, $checkInDate, $checkOutDate, $numberOfOccupants, $totalPrice, $status);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Booking created', 'booking_id' => $this->conn->insert_id, 'total_price' => $totalPrice];
        }

        return ['success' => false, 'message' => 'Booking creation failed: ' . $this->conn->error];
    }

    /**
     * Get booking by ID
     */
    public function getById($bookingId)
    {
        $stmt = $this->conn->prepare("
            SELECT b.*, 
                   p.name as property_name,
                   p.address as property_address,
                   u.first_name, u.last_name, u.email
            FROM bookings b
            LEFT JOIN properties p ON b.property_id = p.id
            LEFT JOIN users u ON b.student_id = u.id
            WHERE b.id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get student bookings
     */
    public function getStudentBookings($studentId, $limit = 10, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT b.*, p.name as property_name, p.address
            FROM bookings b
            LEFT JOIN properties p ON b.property_id = p.id
            WHERE b.student_id = ?
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $studentId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get landlord bookings
     */
    public function getLandlordBookings($landlordId, $limit = 10, $offset = 0)
    {
        $stmt = $this->conn->prepare("
            SELECT b.*, p.name as property_name, u.first_name, u.last_name, u.email
            FROM bookings b
            LEFT JOIN properties p ON b.property_id = p.id
            LEFT JOIN users u ON b.student_id = u.id
            WHERE p.landlord_id = ?
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $landlordId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Confirm booking (landlord)
     */
    public function confirm($bookingId, $landlordId)
    {
        // Verify ownership
        $stmt = $this->conn->prepare("
            SELECT b.id FROM bookings b
            LEFT JOIN properties p ON b.property_id = p.id
            WHERE b.id = ? AND p.landlord_id = ?
        ");
        $stmt->bind_param("ii", $bookingId, $landlordId);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $status = 'confirmed';
        $stmt = $this->conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $bookingId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Booking confirmed'];
        }

        return ['success' => false, 'message' => 'Confirmation failed'];
    }

    /**
     * Cancel booking
     */
    public function cancel($bookingId, $userId, $reason = '')
    {
        $stmt = $this->conn->prepare("SELECT student_id FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking || $booking['student_id'] != $userId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $status = 'cancelled';
        $stmt = $this->conn->prepare("UPDATE bookings SET status = ?, cancellation_reason = ?, cancelled_by = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $reason, $userId, $bookingId);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Booking cancelled'];
        }

        return ['success' => false, 'message' => 'Cancellation failed: ' . $this->conn->error];
    }
}
