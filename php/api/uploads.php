<?php

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'property-image':
            requireAuth(['landlord']);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_FILES['image'])) {
                jsonResponse(false, 'Image file is required');
            }

            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(false, 'Image upload failed');
            }

            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $mimeType = mime_content_type($file['tmp_name']);
            if (!isset($allowedTypes[$mimeType])) {
                jsonResponse(false, 'Only JPG, PNG, GIF, and WebP images are allowed');
            }

            $uploadDir = __DIR__ . '/../uploads/properties/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = $allowedTypes[$mimeType];
            $fileName = 'property_mobile_' . getCurrentUserId() . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $uploadPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                jsonResponse(false, 'Unable to save image');
            }

            jsonResponse(true, 'Image uploaded', ['url' => getBaseUrl() . '/php/uploads/properties/' . $fileName]);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
