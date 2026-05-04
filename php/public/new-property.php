<?php

/**
 * Create New Property Page
 */

$pageTitle = 'Add New Property';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['landlord']);

require_once '../models/Property.php';

$error = '';
$success = '';

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
        'availability_date' => sanitizeString($_POST['availability_date'] ?? date('Y-m-d')),
        'max_occupants' => isset($_POST['max_occupants']) ? (int)$_POST['max_occupants'] : 1,
        'amenities' => array_map('sanitizeString', (array)($_POST['amenities'] ?? [])),
        'rules' => array_map('sanitizeString', (array)($_POST['rules'] ?? [])),
        'image_urls' => []
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
    } elseif ($data['bedrooms'] <= 0) {
        $error = 'Number of bedrooms must be at least 1';
    } else {
        // Handle image uploads
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

                    $data['image_urls'][] = getBaseUrl() . '/php/uploads/properties/' . $newFileName;
                }
            }
        }

        if (!$error) {
            // Create property
            $propertyModel = new Property($conn);
            $result = $propertyModel->create(getCurrentUserId(), $data);

            if ($result['success']) {
                $success = 'Property created successfully! It is now pending verification by the admin.';
                // Redirect after 2 seconds
                header('refresh:2;url=' . pageUrl('landlord-dashboard.php'));
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

    .checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
    }

    .checkbox-item input[type="checkbox"] {
        width: auto;
        margin-right: 0.5rem;
    }

    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
        width: 100%;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        left: -9999px;
    }

    .file-input-label {
        display: block;
        padding: 1rem;
        background-color: #ecf0f1;
        border: 2px dashed #bdc3c7;
        border-radius: 4px;
        text-align: center;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .file-input-label:hover {
        background-color: #d5dbdb;
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
</style>

<div class="property-form">
    <h1>Add New Property</h1>

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
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    <small>e.g., "Cozy 2-Bedroom Apartment Near Campus"</small>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <small>Describe your property in detail. Mention special features, renovations, etc.</small>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="property_type">Property Type *</label>
                    <select id="property_type" name="property_type" required>
                        <option value="apartment" <?php echo ($_POST['property_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                        <option value="house" <?php echo ($_POST['property_type'] ?? '') === 'house' ? 'selected' : ''; ?>>House</option>
                        <option value="dorm" <?php echo ($_POST['property_type'] ?? '') === 'dorm' ? 'selected' : ''; ?>>Dorm</option>
                        <option value="shared" <?php echo ($_POST['property_type'] ?? '') === 'shared' ? 'selected' : ''; ?>>Shared Space</option>
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
                    <input type="text" id="address" name="address" required value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="zipcode">ZIP Code</label>
                    <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($_POST['zipcode'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="latitude">Latitude (Optional)</label>
                    <input type="number" id="latitude" name="latitude" step="0.00000001" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="longitude">Longitude (Optional)</label>
                    <input type="number" id="longitude" name="longitude" step="0.00000001" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Property Details -->
        <div class="form-section">
            <h3>Property Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="bedrooms">Number of Bedrooms *</label>
                    <input type="number" id="bedrooms" name="bedrooms" min="1" required value="<?php echo htmlspecialchars($_POST['bedrooms'] ?? '1'); ?>">
                </div>
                <div class="form-group">
                    <label for="bathrooms">Number of Bathrooms *</label>
                    <input type="number" id="bathrooms" name="bathrooms" min="0.5" step="0.5" required value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? '1'); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="square_feet">Square Feet</label>
                    <input type="number" id="square_feet" name="square_feet" min="0" value="<?php echo htmlspecialchars($_POST['square_feet'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="max_occupants">Maximum Occupants *</label>
                    <input type="number" id="max_occupants" name="max_occupants" min="1" required value="<?php echo htmlspecialchars($_POST['max_occupants'] ?? '1'); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="furnished">
                        <input type="checkbox" id="furnished" name="furnished" value="1" <?php echo ($_POST['furnished'] ?? false) ? 'checked' : ''; ?>>
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
                    <input type="number" id="price_per_month" name="price_per_month" min="0" step="0.01" required value="<?php echo htmlspecialchars($_POST['price_per_month'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="availability_date">Available From *</label>
                    <input type="date" id="availability_date" name="availability_date" required value="<?php echo htmlspecialchars($_POST['availability_date'] ?? date('Y-m-d')); ?>">
                </div>
            </div>
        </div>

        <!-- Amenities -->
        <div class="form-section">
            <h3>Amenities</h3>
            <div class="checkbox-group">
                <?php
                $amenitiesList = ['WiFi', 'AC', 'Heating', 'Kitchen', 'Laundry', 'Parking', 'Gym', 'Pool', 'Balcony', 'Washer/Dryer'];
                foreach ($amenitiesList as $amenity):
                    $selectedAmenities = $_POST['amenities'] ?? [];
                ?>
                    <div class="checkbox-item">
                        <input type="checkbox" id="amenity-<?php echo $amenity; ?>" name="amenities[]" value="<?php echo $amenity; ?>" <?php echo in_array($amenity, $selectedAmenities) ? 'checked' : ''; ?>>
                        <label for="amenity-<?php echo $amenity; ?>" style="margin: 0; font-weight: 400;"><?php echo $amenity; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Rules -->
        <div class="form-section">
            <h3>House Rules</h3>
            <div class="checkbox-group">
                <?php
                $rulesList = ['No Smoking', 'No Pets', 'No Parties', 'Quiet Hours', 'No Drinking', 'Female Only'];
                $selectedRules = $_POST['rules'] ?? [];
                foreach ($rulesList as $rule):
                ?>
                    <div class="checkbox-item">
                        <input type="checkbox" id="rule-<?php echo $rule; ?>" name="rules[]" value="<?php echo $rule; ?>" <?php echo in_array($rule, $selectedRules) ? 'checked' : ''; ?>>
                        <label for="rule-<?php echo $rule; ?>" style="margin: 0; font-weight: 400;"><?php echo $rule; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Images -->
        <div class="form-section">
            <h3>Property Images</h3>
            <div class="form-group">
                <label for="images">Upload Images</label>
                <div class="file-input-wrapper">
                    <input type="file" id="images" name="images[]" multiple accept="image/*" onchange="updateFileCount(this)">
                    <label for="images" class="file-input-label">
                        <div>📷 Click to select images or drag and drop</div>
                        <small>You can upload up to 5 images (JPG, PNG, GIF)</small>
                    </label>
                </div>
                <small id="file-count" style="color: #7f8c8d; margin-top: 0.5rem; display: block;"></small>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Add Property</button>
            <a href="<?php echo pageUrl('landlord-dashboard.php'); ?>" class="btn" style="background-color: #95a5a6; text-align: center;">Cancel</a>
        </div>
    </form>
</div>

<script>
    function updateFileCount(input) {
        const count = input.files.length;
        const fileCountEl = document.getElementById('file-count');
        if (count > 0) {
            fileCountEl.textContent = count + ' file(s) selected';
        } else {
            fileCountEl.textContent = '';
        }
    }

    // Drag and drop
    const fileInput = document.getElementById('images');
    const fileLabel = document.querySelector('.file-input-label');

    fileLabel.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileLabel.style.backgroundColor = '#d5dbdb';
    });

    fileLabel.addEventListener('dragleave', () => {
        fileLabel.style.backgroundColor = '#ecf0f1';
    });

    fileLabel.addEventListener('drop', (e) => {
        e.preventDefault();
        fileLabel.style.backgroundColor = '#ecf0f1';
        fileInput.files = e.dataTransfer.files;
        updateFileCount(fileInput);
    });
</script>

<?php require_once '../templates/footer.php'; ?>