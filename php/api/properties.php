<?php

/**
 * Properties API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Property.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$property = new Property($conn);

try {
    switch ($action) {
        case 'list':
            // List all properties (with filters)
            $filters = [
                'city' => $_GET['city'] ?? null,
                'property_type' => $_GET['property_type'] ?? null,
                'min_price' => $_GET['min_price'] ?? null,
                'max_price' => $_GET['max_price'] ?? null,
                'bedrooms' => $_GET['bedrooms'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $results = $property->listProperties($filters, $limit, $offset);
            jsonResponse(true, 'Properties retrieved', ['properties' => $results, 'page' => $page, 'limit' => $limit]);
            break;

        case 'get':
            // Get single property
            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Property ID required');
            }

            $propertyData = $property->getById((int)$_GET['id']);
            if ($propertyData) {
                foreach (['amenities', 'rules', 'image_urls'] as $jsonField) {
                    if (!is_array($propertyData[$jsonField] ?? null)) {
                        $propertyData[$jsonField] = json_decode($propertyData[$jsonField] ?? '[]', true) ?: [];
                    }
                }
                jsonResponse(true, 'Property retrieved', $propertyData);
            } else {
                jsonResponse(false, 'Property not found');
            }
            break;

        case 'create':
            // Create property (landlord only)
            requireAuth(['landlord']);

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['name'], $data['address'], $data['city'], $data['property_type'], $data['price_per_month'])) {
                jsonResponse(false, 'Missing required fields');
            }

            $result = $property->create(getCurrentUserId(), $data);
            jsonResponse($result['success'], $result['message'], ['property_id' => $result['property_id'] ?? null]);
            break;

        case 'update':
            // Update property (landlord only)
            requireAuth(['landlord']);

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Property ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $result = $property->update((int)$_GET['id'], getCurrentUserId(), $data);
            jsonResponse($result['success'], $result['message']);
            break;

        case 'delete':
            // Delete property (landlord only)
            requireAuth(['landlord']);

            if ($method !== 'DELETE') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Property ID required');
            }

            $result = $property->delete((int)$_GET['id'], getCurrentUserId());
            jsonResponse($result['success'], $result['message']);
            break;

        case 'my-properties':
            // Get landlord's properties
            requireAuth(['landlord']);

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $results = $property->getLandlordProperties(getCurrentUserId(), $limit, $offset);
            jsonResponse(true, 'Properties retrieved', ['properties' => $results]);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
