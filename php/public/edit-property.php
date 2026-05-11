<?php

/**
 * Edit Property Page
 */

$pageTitle = 'Edit Property';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['landlord']);

require_once '../models/Property.php';

if (!isset($_GET['id'])) {
    header('Location: ' . pageUrl('landlord-dashboard.php'));
    exit();
}

$propertyModel = new Property($conn);
$propertyData = $propertyModel->getById((int)$_GET['id']);

if (!$propertyData || $propertyData['landlord_id'] != getCurrentUserId()) {
    header('Location: ' . pageUrl('landlord-dashboard.php'));
    exit();
}

$error = '';
$success = '';
$videoUploadsEnabled = arePropertyVideoUploadsEnabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => sanitizeString($_POST['name'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'address' => sanitizeString($_POST['address'] ?? ''),
        'city' => sanitizeString($_POST['city'] ?? ''),
        'zipcode' => sanitizeString($_POST['zipcode'] ?? ''),
        'latitude' => isset($_POST['latitude']) ? (float)$_POST['latitude'] : null,
        'longitude' => isset($_POST['longitude']) ? (float)$_POST['longitude'] : null,
        'property_type' => sanitizeString($_POST['property_type'] ?? 'apartment'),
        'bedrooms' => isset($_POST['bedrooms']) ? (int)$_POST['bedrooms'] : 1,
        'bathrooms' => isset($_POST['bathrooms']) ? (float)$_POST['bathrooms'] : 1,
        'square_feet' => isset($_POST['square_feet']) ? (int)$_POST['square_feet'] : null,
        'furnished' => isset($_POST['furnished']) ? 1 : 0,
        'price_per_month' => isset($_POST['price_per_month']) ? (float)$_POST['price_per_month'] : 0,
        'availability_date' => sanitizeString($_POST['availability_date'] ?? $propertyData['availability_date']),
        'max_occupants' => isset($_POST['max_occupants']) ? (int)$_POST['max_occupants'] : 1,
        'amenities' => array_map('sanitizeString', (array)($_POST['amenities'] ?? [])),
        'rules' => array_map('sanitizeString', (array)($_POST['rules'] ?? [])),
        'image_urls' => $propertyData['image_urls'] ?? [],
        'video_url' => isset($_POST['remove_video']) ? null : ($propertyData['video_url'] ?? null),
    ];

    // Validation
    if (empty($data['name'])) {
        $error = 'Property name is required';
    } elseif (empty($data['address'])) {
        $error = 'Address is required';
    } elseif (empty($data['city'])) {
        $error = 'City is required';
    } elseif ($data['price_per_month'] <= 0) {
        $error = 'Price must be greater than 0';
    } else {
        if (!empty($_FILES['images']['name'][0])) {
            $uploadedFiles = array_filter($_FILES['images']['name']);

            if (count($uploadedFiles) > 5) {
                $error = 'You can upload up to 5 images only';
            } else {
                $uploadDir = __DIR__ . '/../uploads/properties/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxFileSize = 5 * 1024 * 1024;
                $newImageUrls = [];

                foreach ($_FILES['images']['name'] as $key => $imageName) {
                    if (empty($imageName) || $_FILES['images']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                        $error = 'One of the images could not be uploaded. Please try again.';
                        break;
                    }

                    if ($_FILES['images']['size'][$key] > $maxFileSize) {
                        $error = 'Each image must be 5MB or smaller';
                        break;
                    }

                    $tmpPath = $_FILES['images']['tmp_name'][$key];
                    $mimeType = mime_content_type($tmpPath);
                    $fileExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));

                    if (!in_array($fileExt, $allowedExts, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
                        $error = 'Images must be JPG, PNG, GIF, or WebP files';
                        break;
                    }

                    $newFileName = 'property_' . getCurrentUserId() . '_' . time() . '_' . uniqid() . '.' . $fileExt;
                    $uploadPath = $uploadDir . $newFileName;

                    if (!move_uploaded_file($tmpPath, $uploadPath)) {
                        $error = 'Unable to save one of the uploaded images';
                        break;
                    }

                    $newImageUrls[] = getBaseUrl() . '/php/uploads/properties/' . $newFileName;
                }

                if (!$error && !empty($newImageUrls)) {
                    $data['image_urls'] = $newImageUrls;
                }
            }
        }

        if (!empty($_FILES['video']['name']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE) {
            if (!$videoUploadsEnabled) {
                $error = 'Property video uploads are currently paused. Please save changes without replacing the video.';
            } elseif ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $error = 'The video could not be uploaded. Please try again.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/properties/videos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedVideoExts = ['mp4', 'webm', 'mov'];
                $allowedVideoMimeTypes = [
                    'video/mp4',
                    'video/webm',
                    'video/quicktime',
                    'video/x-m4v',
                    'application/mp4',
                    'application/octet-stream'
                ];
                $maxVideoSize = 40 * 1024 * 1024;
                $videoName = $_FILES['video']['name'];
                $videoTmpPath = $_FILES['video']['tmp_name'];
                $videoMimeType = mime_content_type($videoTmpPath);
                $videoExt = strtolower(pathinfo($videoName, PATHINFO_EXTENSION));

                if ($_FILES['video']['size'] > $maxVideoSize) {
                    $error = 'Property video must be 40MB or smaller';
                } elseif (!in_array($videoExt, $allowedVideoExts, true) || !in_array($videoMimeType, $allowedVideoMimeTypes, true)) {
                    $error = 'Property video must be an MP4, WebM, or MOV file';
                } else {
                    $newVideoName = 'property_video_' . getCurrentUserId() . '_' . time() . '_' . uniqid() . '.' . $videoExt;
                    $videoUploadPath = $uploadDir . $newVideoName;

                    if (!move_uploaded_file($videoTmpPath, $videoUploadPath)) {
                        $error = 'Unable to save the uploaded video';
                    } else {
                        $data['video_url'] = getBaseUrl() . '/php/uploads/properties/videos/' . $newVideoName;
                    }
                }
            }
        }

        if (!$error) {
            $result = $propertyModel->update((int)$_GET['id'], getCurrentUserId(), $data);

            if ($result['success']) {
                $success = 'Property updated successfully!';
                $propertyData = array_merge($propertyData, $data);
            } else {
                $error = $result['message'];
            }
        }
    }
}

require_once '../templates/header.php';
?>

<style>
    .property-form {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid #ecf0f1;
    }

    .form-section h3 {
        margin-bottom: 1.5rem;
        color: #2c3e50;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-row.full {
        grid-template-columns: 1fr;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #2c3e50;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
        font-family: inherit;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        background-color: #fff3cd;
        color: #856404;
        margin-bottom: 1.5rem;
        font-weight: 500;
    }

    .status-badge.verified {
        background-color: #d4edda;
        color: #155724;
    }

    .status-badge.rejected {
        background-color: #f8d7da;
        color: #721c24;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .form-actions button,
    .form-actions a {
        flex: 1;
    }

    .current-images {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .current-image {
        aspect-ratio: 4 / 3;
        border-radius: 8px;
        background: #ecf0f1;
        overflow: hidden;
    }

    .current-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
</style>

<div class="property-form">
    <h1>Edit Property</h1>

    <div class="status-badge <?php echo $propertyData['verification_status']; ?>">
        Status: <?php echo ucfirst($propertyData['verification_status']); ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- Basic Information -->
        <div class="form-section">
            <h3>Basic Information</h3>

            <div class="form-row full">
                <div class="form-group">
                    <label for="name">Property Name *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($propertyData['name']); ?>">
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required><?php echo htmlspecialchars($propertyData['description']); ?></textarea>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="property_type">Property Type *</label>
                    <select id="property_type" name="property_type" required>
                        <option value="apartment" <?php echo $propertyData['property_type'] === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                        <option value="house" <?php echo $propertyData['property_type'] === 'house' ? 'selected' : ''; ?>>House</option>
                        <option value="dorm" <?php echo $propertyData['property_type'] === 'dorm' ? 'selected' : ''; ?>>Dorm</option>
                        <option value="shared" <?php echo $propertyData['property_type'] === 'shared' ? 'selected' : ''; ?>>Shared Space</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Location -->
        <div class="form-section">
            <h3>Location</h3>

            <div class="form-row full">
                <div class="form-group">
                    <label for="address">Street Address *</label>
                    <input type="text" id="address" name="address" required value="<?php echo htmlspecialchars($propertyData['address']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" required value="<?php echo htmlspecialchars($propertyData['city']); ?>">
                </div>
                <div class="form-group">
                    <label for="zipcode">ZIP Code</label>
                    <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($propertyData['zipcode'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="latitude">Latitude</label>
                    <input type="number" id="latitude" name="latitude" step="0.00000001" value="<?php echo htmlspecialchars($propertyData['latitude'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="longitude">Longitude</label>
                    <input type="number" id="longitude" name="longitude" step="0.00000001" value="<?php echo htmlspecialchars($propertyData['longitude'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Property Details -->
        <div class="form-section">
            <h3>Property Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="bedrooms">Number of Bedrooms *</label>
                    <input type="number" id="bedrooms" name="bedrooms" min="1" required value="<?php echo $propertyData['bedrooms']; ?>">
                </div>
                <div class="form-group">
                    <label for="bathrooms">Number of Bathrooms *</label>
                    <input type="number" id="bathrooms" name="bathrooms" min="0.5" step="0.5" required value="<?php echo $propertyData['bathrooms']; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="square_feet">Square Feet</label>
                    <input type="number" id="square_feet" name="square_feet" min="0" value="<?php echo htmlspecialchars($propertyData['square_feet'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="max_occupants">Maximum Occupants *</label>
                    <input type="number" id="max_occupants" name="max_occupants" min="1" required value="<?php echo $propertyData['max_occupants']; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="furnished">
                        <input type="checkbox" id="furnished" name="furnished" value="1" <?php echo $propertyData['furnished'] ? 'checked' : ''; ?>>
                        Furnished
                    </label>
                </div>
            </div>
        </div>

        <!-- Pricing & Availability -->
        <div class="form-section">
            <h3>Pricing & Availability</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_per_month">Price Per Month *</label>
                    <input type="number" id="price_per_month" name="price_per_month" min="0" step="0.01" required value="<?php echo $propertyData['price_per_month']; ?>">
                </div>
                <div class="form-group">
                    <label for="availability_date">Available From *</label>
                    <input type="date" id="availability_date" name="availability_date" required value="<?php echo $propertyData['availability_date']; ?>">
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="form-section">
            <h3>Property Images</h3>
            <?php
            $currentImages = $propertyData['image_urls'] ?? [];
            if (!empty($currentImages) && is_array($currentImages)):
            ?>
                <div class="current-images">
                    <?php foreach (array_slice($currentImages, 0, 5) as $image): ?>
                        <div class="current-image">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="Current property image">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="images">Replace Property Images</label>
                <input type="file" id="images" name="images[]" multiple accept="image/*" onchange="updateImageCount(this)">
                <small>Optional. Select up to 5 new JPG, PNG, GIF, or WebP images. Selecting images will replace the current set.</small>
                <small id="image-count" style="color: #7f8c8d; margin-top: 0.5rem; display: block;"></small>
            </div>
        </div>

        <div class="form-section">
            <h3>Property Video</h3>
            <div class="form-group">
                <?php if (!empty($propertyData['video_url'])): ?>
                    <video controls style="width: 100%; max-height: 320px; margin-bottom: 1rem; border-radius: 8px; background: #000;">
                        <source src="<?php echo htmlspecialchars($propertyData['video_url']); ?>">
                        Your browser does not support the video tag.
                    </video>
                    <div class="checkbox-item" style="margin-bottom: 1rem;">
                        <input type="checkbox" id="remove_video" name="remove_video" value="1">
                        <label for="remove_video" style="margin: 0; font-weight: 400;">Remove current video</label>
                    </div>
                <?php endif; ?>
                <?php if ($videoUploadsEnabled): ?>
                    <label for="video"><?php echo empty($propertyData['video_url']) ? 'Upload Interior Video' : 'Replace Interior Video'; ?></label>
                    <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/quicktime">
                    <small>Optional. Upload one MP4, WebM, or MOV video up to 40MB.</small>
                <?php else: ?>
                    <div class="alert alert-info">Video uploads are currently paused. Existing videos can still be viewed, but landlords cannot upload or replace videos right now.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Save Changes</button>
            <a href="<?php echo pageUrl('landlord-dashboard.php'); ?>" class="btn" style="background-color: #95a5a6; text-align: center;">Cancel</a>
        </div>
    </form>
</div>

<script>
    function updateImageCount(input) {
        const count = input.files.length;
        const imageCountEl = document.getElementById('image-count');
        imageCountEl.textContent = count > 0 ? count + ' image(s) selected' : '';
    }
</script>

<?php require_once '../templates/footer.php'; ?>
