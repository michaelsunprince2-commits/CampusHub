<?php

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'verification-dashboard':
            requireAuth(['admin', 'committee']);

            $stats = [];
            $statQueries = [
                'users' => "SELECT COUNT(*) AS count FROM users",
                'properties' => "SELECT COUNT(*) AS count FROM properties",
                'pending_properties' => "SELECT COUNT(*) AS count FROM properties WHERE verification_status = 'pending'"
            ];

            if (getCurrentUserRole() === 'admin') {
                $statQueries['bookings'] = "SELECT COUNT(*) AS count FROM bookings";
            }

            foreach ($statQueries as $key => $query) {
                $result = $conn->query($query)->fetch_assoc();
                $stats[$key] = (int)($result['count'] ?? 0);
            }

            $properties = $conn->query("
                SELECT p.id, p.name, p.address, p.city, p.price_per_month, p.verification_status, p.created_at,
                       u.first_name, u.last_name, u.email
                FROM properties p
                LEFT JOIN users u ON p.landlord_id = u.id
                WHERE p.verification_status = 'pending'
                ORDER BY p.created_at DESC
                LIMIT 50
            ")->fetch_all(MYSQLI_ASSOC);

            jsonResponse(true, 'Verification dashboard retrieved', ['stats' => $stats, 'pending_properties' => $properties]);
            break;

        case 'property-status':
            requireAuth(['admin', 'committee']);

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $propertyId = (int)($data['property_id'] ?? 0);
            $status = $data['status'] ?? '';

            if (!$propertyId || !in_array($status, ['pending', 'verified', 'rejected'], true)) {
                jsonResponse(false, 'Invalid property status request');
            }

            $verifiedBy = $status === 'verified' ? getCurrentUserId() : null;
            $stmt = $conn->prepare("UPDATE properties SET verification_status = ?, verified_by = ? WHERE id = ?");
            $stmt->bind_param("sii", $status, $verifiedBy, $propertyId);
            $stmt->execute();

            jsonResponse(true, 'Property status updated');
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
