<?php

/**
 * Favorites API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            requireAuth(['student']);

            $stmt = $conn->prepare("
                SELECT p.*,
                       u.first_name as landlord_first_name,
                       u.last_name as landlord_last_name,
                       COUNT(DISTINCT r.id) as review_count,
                       AVG(r.rating) as avg_rating,
                       COUNT(DISTINCT active_b.id) as active_booking_count,
                       f.created_at as favorited_at
                FROM favorites f
                INNER JOIN properties p ON p.id = f.property_id
                LEFT JOIN users u ON p.landlord_id = u.id
                LEFT JOIN reviews r ON r.property_id = p.id
                LEFT JOIN bookings active_b ON active_b.property_id = p.id AND active_b.status IN ('pending', 'confirmed')
                WHERE f.student_id = ?
                GROUP BY p.id, f.created_at
                ORDER BY f.created_at DESC
            ");
            $studentId = getCurrentUserId();
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($favorites as &$property) {
                foreach (['amenities', 'rules', 'image_urls'] as $jsonField) {
                    if (!is_array($property[$jsonField] ?? null)) {
                        $property[$jsonField] = json_decode($property[$jsonField] ?? '[]', true) ?: [];
                    }
                }
            }

            jsonResponse(true, 'Favorites retrieved', ['favorites' => $favorites]);
            break;

        case 'add':
            requireAuth(['student']);

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $propertyId = (int)($data['property_id'] ?? 0);

            if ($propertyId <= 0) {
                jsonResponse(false, 'Property ID required');
            }

            $studentId = getCurrentUserId();
            $stmt = $conn->prepare("INSERT IGNORE INTO favorites (student_id, property_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $studentId, $propertyId);

            if ($stmt->execute()) {
                jsonResponse(true, 'Property saved');
            }

            jsonResponse(false, 'Unable to save property');
            break;

        case 'remove':
            requireAuth(['student']);

            if ($method !== 'DELETE') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $propertyId = (int)($_GET['property_id'] ?? ($data['property_id'] ?? 0));

            if ($propertyId <= 0) {
                jsonResponse(false, 'Property ID required');
            }

            $studentId = getCurrentUserId();
            $stmt = $conn->prepare("DELETE FROM favorites WHERE student_id = ? AND property_id = ?");
            $stmt->bind_param("ii", $studentId, $propertyId);

            if ($stmt->execute()) {
                jsonResponse(true, 'Property removed from saved');
            }

            jsonResponse(false, 'Unable to remove property');
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
