<?php

/**
 * Reviews API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Review.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$review = new Review($conn);

try {
    switch ($action) {
        case 'create':
            // Create review
            requireAuth(['student']);

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['property_id'], $data['rating'])) {
                jsonResponse(false, 'Missing required fields');
            }

            $result = $review->create(
                (int)$data['property_id'],
                getCurrentUserId(),
                (int)$data['rating'],
                $data['title'] ?? '',
                $data['comment'] ?? ''
            );

            jsonResponse($result['success'], $result['message'], ['review_id' => $result['review_id'] ?? null]);
            break;

        case 'list':
            // Get property reviews
            if (!isset($_GET['property_id'])) {
                jsonResponse(false, 'Property ID required');
            }

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $results = $review->getPropertyReviews((int)$_GET['property_id'], $limit, $offset);
            jsonResponse(true, 'Reviews retrieved', ['reviews' => $results]);
            break;

        case 'helpful':
            // Mark review as helpful
            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Review ID required');
            }

            $success = $review->markHelpful((int)$_GET['id']);
            jsonResponse($success, $success ? 'Review marked helpful' : 'Failed');
            break;

        case 'delete':
            // Delete review
            requireAuth();

            if ($method !== 'DELETE') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Review ID required');
            }

            $result = $review->delete((int)$_GET['id'], getCurrentUserId());
            jsonResponse($result['success'], $result['message']);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
