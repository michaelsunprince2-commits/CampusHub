<?php

/**
 * User Profile Page
 */

$pageTitle = 'Profile';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth();

require_once '../models/User.php';
require_once '../models/Booking.php';
require_once '../models/Property.php';

$userModel = new User($conn);
$currentUserId = getCurrentUserId();
$userProfile = $userModel->getProfile($currentUserId);

$verificationRequest = null;
$verificationDocuments = [];
$verificationStatus = 'not_requested';
$verificationNotes = '';

$userRole = $userProfile['role'] ?? getCurrentUserRole() ?? 'student';

$stmt = $conn->prepare("SELECT * FROM verification_requests WHERE user_id = ? AND request_type = 'user' ORDER BY created_at DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $verificationRequest = $stmt->get_result()->fetch_assoc();
}
if ($verificationRequest) {
    $verificationDocuments = json_decode($verificationRequest['document_urls'] ?? '{}', true);
    $verificationStatus = $verificationRequest['status'];
    $verificationNotes = $verificationRequest['reviewer_notes'];
}

$error = '';
$success = '';

function saveVerificationFiles($uploadField, $uploadDir, $filePrefix, &$error)
{
    $urls = [];
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $maxFileSize = 5 * 1024 * 1024;

    if (!isset($_FILES[$uploadField])) {
        return $urls;
    }

    $field = $_FILES[$uploadField];
    $files = is_array($field['name']) ? $field['name'] : [$field['name']];
    $tmpNames = is_array($field['tmp_name']) ? $field['tmp_name'] : [$field['tmp_name']];
    $errors = is_array($field['error']) ? $field['error'] : [$field['error']];
    $sizes = is_array($field['size']) ? $field['size'] : [$field['size']];

    foreach ($files as $key => $name) {
        if (empty($name) || $errors[$key] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errors[$key] !== UPLOAD_ERR_OK) {
            $error = 'One of the verification files could not be uploaded. Please try again.';
            return [];
        }

        if ($sizes[$key] > $maxFileSize) {
            $error = 'Each verification file must be 5MB or smaller.';
            return [];
        }

        $tmpPath = $tmpNames[$key];
        $fileExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mimeType = mime_content_type($tmpPath);

        if (!in_array($fileExt, $allowedExts, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
            $error = 'Verification files must be JPG, PNG, GIF, WebP, or PDF.';
            return [];
        }

        $newFileName = $filePrefix . '_' . time() . '_' . uniqid() . '.' . $fileExt;
        $uploadPath = $uploadDir . $newFileName;
        if (!move_uploaded_file($tmpPath, $uploadPath)) {
            $error = 'Unable to save one of the verification files.';
            return [];
        }

        $urls[] = getBaseUrl() . '/php/uploads/verification/' . $newFileName;
    }

    return $urls;
}

function safeProfileCount($conn, $query)
{
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }

    if (!$stmt->execute()) {
        return 0;
    }

    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['count'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitizeString($_POST['first_name'] ?? '');
    $lastName = sanitizeString($_POST['last_name'] ?? '');
    $phone = sanitizeString($_POST['phone'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        $error = 'First name and last name are required.';
    } elseif (!empty($phone) && !preg_match('/^[0-9+\s()\-]{7,20}$/', $phone)) {
        $error = 'Phone number contains invalid characters.';
    }

    $data = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'bio' => $bio,
    ];

    $verificationFileUploads = [];
    $verificationUploadDir = __DIR__ . '/../uploads/verification/';
    if (!is_dir($verificationUploadDir)) {
        mkdir($verificationUploadDir, 0755, true);
    }

    if ($userRole === 'landlord') {
        if (!empty($_FILES['government_id']['name'])) {
            $verificationFileUploads['government_id'] = saveVerificationFiles('government_id', $verificationUploadDir, 'government_id_' . $currentUserId, $error);
        }

        if (!empty(array_filter($_FILES['property_documents']['name'] ?? []))) {
            $verificationFileUploads['property_documents'] = saveVerificationFiles('property_documents', $verificationUploadDir, 'property_documents_' . $currentUserId, $error);
        }

        if (!empty(array_filter($_FILES['proof_of_residence']['name'] ?? []))) {
            $verificationFileUploads['proof_of_residence'] = saveVerificationFiles('proof_of_residence', $verificationUploadDir, 'proof_of_residence_' . $currentUserId, $error);
        }
    }

    if (!empty($_FILES['profile_picture']['name'])) {
        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Profile picture could not be uploaded. Please try again.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 3 * 1024 * 1024;
            $tmpPath = $_FILES['profile_picture']['tmp_name'];
            $fileExt = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $mimeType = mime_content_type($tmpPath);

            if ($_FILES['profile_picture']['size'] > $maxFileSize) {
                $error = 'Profile picture must be 3MB or smaller.';
            } elseif (!in_array($fileExt, $allowedExts, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
                $error = 'Profile picture must be a JPG, PNG, GIF, or WebP image.';
            } else {
                $newFileName = 'profile_' . $currentUserId . '_' . time() . '_' . uniqid() . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpPath, $uploadPath)) {
                    $data['profile_picture'] = getBaseUrl() . '/php/uploads/profiles/' . $newFileName;
                } else {
                    $error = 'Unable to save profile picture.';
                }
            }
        }
    }

    if (!$error) {
        $result = $userModel->updateProfile($currentUserId, $data);

        if ($result['success']) {
            if ($userRole === 'landlord' && !empty($verificationFileUploads)) {
                $existingRequestId = $verificationRequest['id'] ?? null;
                $existingDocs = $verificationDocuments;

                foreach ($verificationFileUploads as $key => $urls) {
                    if (!empty($urls)) {
                        $existingDocs[$key] = $urls;
                    }
                }

                if ($existingRequestId) {
                    $status = $verificationRequest['status'] === 'approved' ? 'approved' : 'pending';
                    $stmt = $conn->prepare(
                        "UPDATE verification_requests SET document_urls = ?, status = ?, reviewer_notes = '', reviewed_by = NULL, review_date = NULL WHERE id = ?"
                    );
                    $docJson = json_encode($existingDocs);
                    $stmt->bind_param("ssi", $docJson, $status, $existingRequestId);
                    $stmt->execute();
                    $verificationStatus = $status;
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO verification_requests (user_id, request_type, document_urls, status) VALUES (?, 'user', ?, 'pending')"
                    );
                    $docJson = json_encode($existingDocs);
                    $stmt->bind_param("is", $currentUserId, $docJson);
                    $stmt->execute();
                    $verificationStatus = 'pending';
                }
                $verificationNotes = '';
                $verificationDocuments = $existingDocs;
            }

            $success = 'Profile updated successfully';
            $_SESSION['user_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
            $userProfile = $userModel->getProfile($currentUserId);
        } else {
            $error = $result['message'];
        }
    }
}

$fullName = trim(($userProfile['first_name'] ?? '') . ' ' . ($userProfile['last_name'] ?? ''));
$initials = strtoupper(substr($userProfile['first_name'] ?? 'U', 0, 1) . substr($userProfile['last_name'] ?? '', 0, 1));
$initials = $initials ?: 'U';
$profileStats = [];
$primaryAction = null;
$secondaryAction = ['label' => 'Messages', 'url' => pageUrl('messages.php')];
$isAdminProfile = in_array($userRole, ['admin', 'committee'], true);
$adminPermissions = [];

if ($userRole === 'landlord') {
    $propertyModel = new Property($conn);
    $bookingModel = new Booking($conn);
    $landlordProperties = $propertyModel->getLandlordProperties($currentUserId, 100, 0);
    $landlordBookings = $bookingModel->getLandlordBookings($currentUserId, 100, 0);

    $profileStats = [
        ['label' => 'Properties', 'value' => count($landlordProperties)],
        ['label' => 'Pending Bookings', 'value' => count(array_filter($landlordBookings, fn($booking) => $booking['status'] === 'pending'))],
        ['label' => 'Rating', 'value' => $userProfile['rating'] ? number_format((float)$userProfile['rating'], 1) : 'New'],
    ];
    $primaryAction = ['label' => 'Manage Properties', 'url' => pageUrl('landlord-dashboard.php')];
} elseif ($userRole === 'student') {
    $bookingModel = new Booking($conn);
    $studentBookings = $bookingModel->getStudentBookings($currentUserId, 100, 0);

    $profileStats = [
        ['label' => 'Bookings', 'value' => count($studentBookings)],
        ['label' => 'Confirmed', 'value' => count(array_filter($studentBookings, fn($booking) => $booking['status'] === 'confirmed'))],
        ['label' => 'Pending', 'value' => count(array_filter($studentBookings, fn($booking) => $booking['status'] === 'pending'))],
    ];
    $primaryAction = ['label' => 'View Bookings', 'url' => pageUrl('bookings.php')];
} else {
    $platformCounts = [
        'users' => 0,
        'properties' => 0,
        'pending_properties' => 0,
        'bookings' => 0,
    ];

    $countQueries = [
        'users' => "SELECT COUNT(*) AS count FROM users",
        'properties' => "SELECT COUNT(*) AS count FROM properties",
        'pending_properties' => "SELECT COUNT(*) AS count FROM properties WHERE verification_status = 'pending'",
    ];

    if ($userRole === 'admin') {
        $countQueries['bookings'] = "SELECT COUNT(*) AS count FROM bookings";
    }

    foreach ($countQueries as $key => $query) {
        $platformCounts[$key] = safeProfileCount($conn, $query);
    }

    $profileStats = [
        ['label' => 'Users', 'value' => $platformCounts['users']],
        ['label' => 'Properties', 'value' => $platformCounts['properties']],
        ['label' => 'Pending Reviews', 'value' => $platformCounts['pending_properties']],
    ];

    if ($userRole === 'admin') {
        $profileStats[] = ['label' => 'Bookings', 'value' => $platformCounts['bookings']];
        $adminPermissions = [
            ['title' => 'Property Verification', 'description' => 'Review, approve, or reject landlord listings.'],
            ['title' => 'User Oversight', 'description' => 'Verify users and manage account roles.'],
            ['title' => 'Booking Operations', 'description' => 'Monitor and update booking statuses.'],
        ];
    } else {
        $adminPermissions = [
            ['title' => 'Property Verification', 'description' => 'Review, approve, or reject landlord listings.'],
            ['title' => 'Landlord Verification', 'description' => 'Review submitted documents for landlord approval.'],
            ['title' => 'Review Moderation', 'description' => 'Approve or reject property and platform reviews.'],
        ];
    }

    $primaryAction = ['label' => 'Dashboard', 'url' => pageUrl('admin-dashboard.php')];
}

require_once '../templates/header.php';
?>

<style>
    .profile-shell {
        max-width: 1080px;
        margin: 0 auto;
    }

    .admin-profile-shell {
        max-width: 1180px;
    }

    .profile-hero {
        background: #ffffff;
        border: 1px solid #e5edf2;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 6px 18px rgba(44, 62, 80, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .admin-profile-shell .profile-hero {
        align-items: stretch;
        background: #ffffff;
        border-color: #dbe7ee;
        overflow: hidden;
        position: relative;
    }

    .admin-profile-shell .profile-hero::before {
        content: "";
        width: 6px;
        background: #1f6f78;
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
    }

    .profile-identity {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .profile-avatar {
        width: 84px;
        height: 84px;
        background: #1f6f78;
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: #ffffff;
        font-size: 1.8rem;
        font-weight: 700;
        flex: 0 0 auto;
        overflow: hidden;
    }

    .admin-profile-shell .profile-avatar {
        width: 96px;
        height: 96px;
        background: #2c3e50;
        border: 4px solid #eef5f6;
        box-shadow: 0 8px 22px rgba(44, 62, 80, 0.16);
    }

    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-picture-field {
        display: grid;
        grid-template-columns: 84px 1fr;
        gap: 1rem;
        align-items: center;
        padding: 1rem;
        border: 1px solid #e8eef2;
        border-radius: 8px;
        background: #fbfcfd;
        margin-bottom: 1.5rem;
    }

    .profile-picture-preview {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        background: #1f6f78;
        color: #ffffff;
        display: grid;
        place-items: center;
        font-weight: 700;
        overflow: hidden;
    }

    .profile-picture-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-picture-field input {
        width: 100%;
    }

    .profile-picture-field input[type="file"],
    .verification-upload-card input[type="file"] {
        border: 1px dashed #b8c8d2;
        border-radius: 8px;
        background: #ffffff;
        color: #34495e;
        cursor: pointer;
        padding: 0.7rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .profile-picture-field input[type="file"]:hover,
    .verification-upload-card input[type="file"]:hover {
        border-color: #1f6f78;
        background: #f8fbfc;
    }

    .profile-picture-field input[type="file"]:focus,
    .verification-upload-card input[type="file"]:focus {
        outline: none;
        border-color: #1f6f78;
        box-shadow: 0 0 0 3px rgba(31, 111, 120, 0.14);
    }

    .profile-picture-field input[type="file"]::file-selector-button,
    .verification-upload-card input[type="file"]::file-selector-button {
        margin-right: 0.85rem;
        padding: 0.55rem 0.85rem;
        border: none;
        border-radius: 6px;
        background: #1f6f78;
        color: #ffffff;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .profile-picture-field input[type="file"]::file-selector-button:hover,
    .verification-upload-card input[type="file"]::file-selector-button:hover {
        background: #195f67;
    }

    .verification-upload-section {
        margin: 1.75rem 0 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e8eef2;
    }

    .verification-upload-header {
        margin-bottom: 1rem;
    }

    .verification-upload-header h3 {
        margin-bottom: 0.25rem;
        font-size: 1.15rem;
    }

    .verification-upload-header p {
        color: #657786;
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .verification-upload-grid {
        display: grid;
        gap: 0.9rem;
    }

    .verification-upload-card {
        display: grid;
        gap: 0.65rem;
        padding: 1rem;
        border: 1px solid #dfeaf0;
        border-radius: 8px;
        background: linear-gradient(180deg, #ffffff 0%, #f9fbfc 100%);
    }

    .verification-upload-card label {
        margin-bottom: 0;
        color: #2c3e50;
        font-weight: 700;
    }

    .verification-upload-card small {
        margin-top: 0;
        color: #6b7c86;
        line-height: 1.45;
    }

    .verification-documents {
        display: grid;
        gap: 0.85rem;
    }

    .verification-document-group {
        padding: 0.9rem;
        border: 1px solid #e8eef2;
        border-radius: 8px;
        background: #fbfcfd;
    }

    .verification-document-title {
        display: block;
        margin-bottom: 0.65rem;
        color: #2c3e50;
        font-weight: 700;
    }

    .verification-document-list {
        display: grid;
        gap: 0.5rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .verification-document-link {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        min-width: 0;
        padding: 0.65rem 0.75rem;
        border: 1px solid #dfeaf0;
        border-radius: 7px;
        background: #ffffff;
        color: #1f6f78;
        font-weight: 700;
        overflow-wrap: anywhere;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    .verification-document-link::before {
        content: "FILE";
        flex: 0 0 auto;
        padding: 0.18rem 0.4rem;
        border-radius: 4px;
        background: #eef5f6;
        color: #1f6f78;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.02em;
    }

    .verification-document-link:hover {
        border-color: #1f6f78;
        box-shadow: 0 6px 14px rgba(44, 62, 80, 0.08);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .verification-notes {
        margin-top: 0.9rem;
        padding: 0.9rem;
        border: 1px solid #f1c7c2;
        border-radius: 8px;
        background: #fff6f5;
    }

    .verification-notes .profile-detail-label {
        display: block;
        margin-bottom: 0.35rem;
        color: #922b21;
        font-weight: 700;
    }

    .verification-notes .profile-detail-value {
        display: block;
        color: #c0392b;
        font-weight: 500;
        text-align: left;
    }

    .profile-title {
        min-width: 0;
    }

    .profile-title h1 {
        margin-bottom: 0.35rem;
        line-height: 1.2;
    }

    .profile-subtitle {
        color: #657786;
        margin-bottom: 0;
        overflow-wrap: anywhere;
    }

    .profile-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .profile-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.35rem 0.75rem;
        background: #eef5f6;
        color: #1f6f78;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: capitalize;
    }

    .profile-badge.verified {
        background: #e9f7ef;
        color: #1e8449;
    }

    .profile-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .profile-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .admin-profile-shell .profile-actions {
        align-content: center;
        align-items: center;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 1.5rem;
        align-items: start;
    }

    .admin-profile-shell .profile-grid {
        grid-template-columns: minmax(0, 1fr) 380px;
    }

    .profile-panel {
        background: #ffffff;
        border: 1px solid #e5edf2;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 4px 14px rgba(44, 62, 80, 0.06);
    }

    .admin-profile-shell .profile-panel {
        border-color: #dfeaf0;
        box-shadow: 0 8px 22px rgba(44, 62, 80, 0.07);
    }

    .profile-panel h2,
    .profile-panel h3 {
        margin-bottom: 1.25rem;
    }

    .profile-edit-panel {
        padding: 0;
        overflow: hidden;
    }

    .profile-edit-header {
        padding: 1.5rem 1.5rem 1.15rem;
        border-bottom: 1px solid #e8eef2;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
    }

    .profile-edit-header h2 {
        margin-bottom: 0.35rem;
    }

    .profile-edit-header p {
        color: #657786;
        margin-bottom: 0;
        max-width: 680px;
    }

    .profile-edit-form {
        padding: 1.5rem;
    }

    .profile-edit-form .form-group {
        margin-bottom: 1.15rem;
    }

    .profile-edit-form .form-group label {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.45rem;
        color: #243342;
        font-size: 0.92rem;
        font-weight: 800;
    }

    .profile-edit-form .form-group input:not([type="file"]),
    .profile-edit-form .form-group textarea {
        width: 100%;
        min-height: 46px;
        border: 1px solid #d8e3ea;
        border-radius: 8px;
        background: #ffffff;
        color: #243342;
        font-size: 1rem;
        padding: 0.78rem 0.9rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .profile-edit-form .form-group textarea {
        min-height: 132px;
        resize: vertical;
        line-height: 1.55;
    }

    .profile-edit-form .form-group input:not([type="file"]):focus,
    .profile-edit-form .form-group textarea:focus {
        outline: none;
        border-color: #1f6f78;
        box-shadow: 0 0 0 4px rgba(31, 111, 120, 0.12);
        background: #fbffff;
    }

    .profile-edit-form .form-group input:disabled {
        background: #f3f6f8;
        border-color: #dfe7ec;
        color: #74828b;
        cursor: not-allowed;
    }

    .profile-edit-form .form-group small {
        margin-top: 0.4rem;
        color: #71828d;
        font-size: 0.86rem;
        line-height: 1.45;
    }

    .profile-picture-field .form-group {
        margin-bottom: 0;
    }

    .profile-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .profile-sidebar {
        display: grid;
        gap: 1rem;
    }

    .profile-stat-grid {
        display: grid;
        gap: 0.75rem;
    }

    .admin-profile-shell .profile-stat-grid {
        grid-template-columns: 1fr 1fr;
    }

    .profile-stat {
        border: 1px solid #e8eef2;
        border-radius: 8px;
        padding: 1rem;
        background: #fbfcfd;
    }

    .admin-profile-shell .profile-stat {
        background: #ffffff;
        border-color: #dfeaf0;
    }

    .profile-stat-value {
        color: #2c3e50;
        font-size: 1.8rem;
        line-height: 1;
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .profile-stat-label,
    .profile-detail-label {
        color: #6b7c86;
        font-size: 0.9rem;
    }

    .profile-detail {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.8rem 0;
        border-bottom: 1px solid #edf2f5;
    }

    .profile-detail:last-child {
        border-bottom: none;
    }

    .profile-detail-value {
        color: #2c3e50;
        font-weight: 700;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .profile-save {
        width: 100%;
        padding: 0.95rem 1rem;
        margin-top: 0.75rem;
        border-radius: 8px;
        font-weight: 800;
        box-shadow: 0 8px 18px rgba(39, 174, 96, 0.18);
    }

    .profile-save:hover {
        box-shadow: 0 10px 22px rgba(39, 174, 96, 0.22);
    }

    .admin-command-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .admin-command {
        background: #ffffff;
        border: 1px solid #dfeaf0;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 4px 14px rgba(44, 62, 80, 0.05);
    }

    .admin-command strong {
        display: block;
        color: #2c3e50;
        margin-bottom: 0.35rem;
    }

    .admin-command span {
        display: block;
        color: #657786;
        font-size: 0.92rem;
        line-height: 1.45;
    }

    .admin-security-list {
        display: grid;
        gap: 0.75rem;
    }

    .admin-security-item {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem;
        border: 1px solid #edf2f5;
        border-radius: 8px;
        background: #fbfcfd;
    }

    .admin-security-item strong {
        color: #2c3e50;
    }

    .admin-security-item span {
        color: #1e8449;
        font-weight: 700;
    }

    .profile-logout {
        width: 100%;
        margin-top: 0.75rem;
    }

    @media (max-width: 860px) {

        .profile-grid,
        .profile-form-row,
        .admin-command-grid,
        .admin-profile-shell .profile-stat-grid {
            grid-template-columns: 1fr;
        }

        .profile-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .profile-actions {
            justify-content: flex-start;
            width: 100%;
        }

        .profile-actions .btn {
            flex: 1;
        }
    }

    @media (max-width: 520px) {
        .profile-identity {
            align-items: flex-start;
        }

        .profile-avatar {
            width: 68px;
            height: 68px;
            font-size: 1.4rem;
        }

        .profile-picture-field {
            grid-template-columns: 1fr;
        }

        .profile-edit-header,
        .profile-edit-form {
            padding: 1.15rem;
        }

        .profile-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="profile-shell <?php echo $isAdminProfile ? 'admin-profile-shell' : ''; ?>">
    <section class="profile-hero">
        <div class="profile-identity">
            <div class="profile-avatar">
                <?php if (!empty($userProfile['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($userProfile['profile_picture']); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                <?php else: ?>
                    <?php echo htmlspecialchars($initials); ?>
                <?php endif; ?>
            </div>
            <div class="profile-title">
                <h1><?php echo htmlspecialchars($fullName ?: 'My Profile'); ?></h1>
                <p class="profile-subtitle"><?php echo htmlspecialchars($userProfile['email']); ?></p>
                <div class="profile-badges">
                    <span class="profile-badge"><?php echo htmlspecialchars($userRole); ?> Account</span>
                    <?php if ($userProfile['is_verified']): ?>
                        <span class="profile-badge verified">Verified</span>
                    <?php else: ?>
                        <span class="profile-badge pending">Verification Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-actions">
            <?php if ($primaryAction): ?>
                <a href="<?php echo $primaryAction['url']; ?>" class="btn btn-success"><?php echo htmlspecialchars($primaryAction['label']); ?></a>
            <?php endif; ?>
            <a href="<?php echo $secondaryAction['url']; ?>" class="btn"><?php echo htmlspecialchars($secondaryAction['label']); ?></a>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($isAdminProfile): ?>
        <section class="admin-command-grid">
            <?php foreach ($adminPermissions as $permission): ?>
                <div class="admin-command">
                    <strong><?php echo htmlspecialchars($permission['title']); ?></strong>
                    <span><?php echo htmlspecialchars($permission['description']); ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="profile-grid">
        <section class="profile-panel profile-edit-panel">
            <div class="profile-edit-header">
                <h2><?php echo $isAdminProfile ? ($userRole === 'admin' ? 'Administrator Profile' : 'Committee Profile') : 'Edit Profile'; ?></h2>
                <p><?php echo $isAdminProfile ? ($userRole === 'admin' ? 'Keep your administrator account details accurate for platform oversight.' : 'Keep your committee account details accurate for moderation work.') : 'Keep your contact details, bio, and verification documents up to date.'; ?></p>
            </div>

            <form class="profile-edit-form" method="post" enctype="multipart/form-data">
                <div class="profile-picture-field">
                    <div class="profile-picture-preview">
                        <?php if (!empty($userProfile['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($userProfile['profile_picture']); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small>Upload a JPG, PNG, GIF, or WebP image up to 3MB.</small>
                    </div>
                </div>

                <div class="profile-form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userProfile['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userProfile['last_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userProfile['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userProfile['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="bio"><?php echo $userRole === 'landlord' ? 'About You / Your Rentals' : 'Bio'; ?></label>
                    <textarea id="bio" name="bio" rows="5"><?php echo htmlspecialchars($userProfile['bio'] ?? ''); ?></textarea>
                </div>

                <?php if ($userRole === 'landlord' && !$userProfile['is_verified']): ?>
                    <div class="verification-upload-section">
                        <div class="verification-upload-header">
                            <h3>Verification Documents</h3>
                            <p>Attach clear JPG, PNG, WebP, GIF, or PDF files. Each file must be 5MB or smaller.</p>
                        </div>

                        <div class="verification-upload-grid">
                            <div class="verification-upload-card">
                                <label for="government_id">Government ID</label>
                                <input type="file" id="government_id" name="government_id" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf">
                                <small>Upload a photo or scan of your government ID.</small>
                            </div>

                            <div class="verification-upload-card">
                                <label for="property_documents">Property Documents</label>
                                <input type="file" id="property_documents" name="property_documents[]" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" multiple>
                                <small>Upload proof of ownership or a lease document for your property.</small>
                            </div>

                            <div class="verification-upload-card">
                                <label for="proof_of_residence">Proof of Residence</label>
                                <input type="file" id="proof_of_residence" name="proof_of_residence[]" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" multiple>
                                <small>Upload proof of residence such as a utility bill or bank statement.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-success profile-save">Update Profile</button>
            </form>
        </section>

        <aside class="profile-sidebar">
            <section class="profile-panel">
                <h3><?php echo $userRole === 'landlord' ? 'Listing Summary' : ($userRole === 'student' ? 'Housing Summary' : ($isAdminProfile ? 'Platform Scope' : 'Profile Summary')); ?></h3>
                <div class="profile-stat-grid">
                    <?php foreach ($profileStats as $stat): ?>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo htmlspecialchars((string)$stat['value']); ?></div>
                            <div class="profile-stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="profile-panel">
                <h3>Account Details</h3>
                <div class="profile-detail">
                    <span class="profile-detail-label">Account Type</span>
                    <span class="profile-detail-value"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span>
                </div>
                <div class="profile-detail">
                    <span class="profile-detail-label">Member Since</span>
                    <span class="profile-detail-value"><?php echo formatDate($userProfile['created_at']); ?></span>
                </div>
                <div class="profile-detail">
                    <span class="profile-detail-label">Verification</span>
                    <span class="profile-detail-value"><?php echo $userProfile['is_verified'] ? 'Verified' : ($verificationStatus === 'pending' ? 'Pending Review' : ($verificationStatus === 'rejected' ? 'Rejected' : 'Not Requested')); ?></span>
                </div>

                <?php if ($userRole === 'landlord'): ?>
                    <div class="profile-detail">
                        <span class="profile-detail-label">Request Status</span>
                        <span class="profile-detail-value"><?php echo ucfirst(str_replace('_', ' ', $verificationStatus)); ?></span>
                    </div>
                <?php endif; ?>

                <a href="<?php echo pageUrl('logout.php'); ?>" class="btn btn-danger profile-logout">Logout</a>
            </section>

            <?php if ($userRole === 'landlord' && !empty($verificationDocuments)): ?>
                <section class="profile-panel">
                    <h3>Uploaded Verification Documents</h3>
                    <div class="verification-documents">
                        <?php foreach (['government_id' => 'Government ID', 'property_documents' => 'Property Documents', 'proof_of_residence' => 'Proof of Residence'] as $key => $label): ?>
                            <?php if (!empty($verificationDocuments[$key])): ?>
                                <div class="verification-document-group">
                                    <span class="verification-document-title"><?php echo $label; ?></span>
                                    <ul class="verification-document-list">
                                        <?php foreach ((array)$verificationDocuments[$key] as $url): ?>
                                            <li>
                                                <a class="verification-document-link" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo htmlspecialchars(basename($url)); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($verificationStatus === 'rejected' && !empty($verificationNotes)): ?>
                        <div class="verification-notes">
                            <span class="profile-detail-label">Admin Notes</span>
                            <span class="profile-detail-value"><?php echo htmlspecialchars($verificationNotes); ?></span>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($isAdminProfile): ?>
                <section class="profile-panel">
                    <h3>Access Controls</h3>
                    <div class="admin-security-list">
                        <div class="admin-security-item">
                            <strong>Dashboard Access</strong>
                            <span>Enabled</span>
                        </div>
                        <div class="admin-security-item">
                            <strong>Property Review</strong>
                            <span>Enabled</span>
                        </div>
                        <div class="admin-security-item">
                            <strong>User Management</strong>
                            <span><?php echo $userRole === 'admin' ? 'Enabled' : 'Restricted'; ?></span>
                        </div>
                    </div>
                    <a href="<?php echo pageUrl('admin-dashboard.php'); ?>" class="btn profile-logout"><?php echo $userRole === 'admin' ? 'Open Admin Dashboard' : 'Open Review Dashboard'; ?></a>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
