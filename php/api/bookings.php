<?php

/**
 * Bookings API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Booking.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$booking = new Booking($conn);

try {
    switch ($action) {
        case 'create':
            // Create booking
            requireAuth(['student']);

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['property_id'], $data['check_in_date'], $data['check_out_date'], $data['number_of_occupants'])) {
                jsonResponse(false, 'Missing required fields');
            }

            $result = $booking->create(
                (int)$data['property_id'],
                getCurrentUserId(),
                $data['check_in_date'],
                $data['check_out_date'],
                (int)$data['number_of_occupants']
            );

            jsonResponse(
                $result['success'],
                $result['message'],
                ['booking_id' => $result['booking_id'] ?? null, 'total_price' => $result['total_price'] ?? null]
            );
            break;

        case 'get':
            // Get booking details
            requireAuth();

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Booking ID required');
            }

            $bookingData = $booking->getById((int)$_GET['id']);

            if (!$bookingData) {
                jsonResponse(false, 'Booking not found');
            }

            // Verify user is student or landlord of the property
            if (
                $bookingData['student_id'] != getCurrentUserId() &&
                getCurrentUserRole() !== 'admin'
            ) {
                jsonResponse(false, 'Access denied');
            }

            jsonResponse(true, 'Booking retrieved', $bookingData);
            break;

        case 'my-bookings':
            // Get student bookings
            requireAuth(['student']);

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $results = $booking->getStudentBookings(getCurrentUserId(), $limit, $offset);
            jsonResponse(true, 'Bookings retrieved', ['bookings' => $results]);
            break;

        case 'landlord-bookings':
            // Get landlord's bookings
            requireAuth(['landlord']);

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $results = $booking->getLandlordBookings(getCurrentUserId(), $limit, $offset);
            jsonResponse(true, 'Bookings retrieved', ['bookings' => $results]);
            break;

        case 'confirm':
            // Confirm booking (landlord)
            requireAuth(['landlord']);

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Booking ID required');
            }

            $result = $booking->confirm((int)$_GET['id'], getCurrentUserId());
            jsonResponse($result['success'], $result['message']);
            break;

        case 'cancel':
            // Cancel booking (student)
            requireAuth(['student']);

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Booking ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? '';

            $result = $booking->cancel((int)$_GET['id'], getCurrentUserId(), $reason);
            jsonResponse($result['success'], $result['message']);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
