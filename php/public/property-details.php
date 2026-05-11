<?php

/**
 * Property Details Page
 */

$pageTitle = 'Property Details';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Property.php';
require_once '../models/Review.php';

if (!isset($_GET['id'])) {
    header('Location: ' . pageUrl('properties.php'));
    exit();
}

$property = new Property($conn);
$review = new Review($conn);
$propertyData = $property->getById((int)$_GET['id']);

if (!$propertyData) {
    header('Location: ' . pageUrl('properties.php'));
    exit();
}

$reviewError = '';
$reviewSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_property_review') {
    if (!isAuthenticated() || getCurrentUserRole() !== 'student') {
        $reviewError = 'Only students can review properties.';
    } else {
        $rating = sanitizeInt($_POST['rating'] ?? 0, 0);
        $title = sanitizeString($_POST['title'] ?? '', 255);
        $comment = sanitizeInput($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Please select a rating between 1 and 5 stars.';
        } elseif ($title === '') {
            $reviewError = 'Please enter a review title.';
        } elseif ($comment === '') {
            $reviewError = 'Please write a short review.';
        } else {
            $result = $review->create($propertyData['id'], getCurrentUserId(), $rating, $title, $comment);

            if ($result['success']) {
                $reviewSuccess = 'Thank you. Your review has been added.';
                $propertyData = $property->getById((int)$_GET['id']);
            } else {
                $reviewError = $result['message'];
            }
        }
    }
}

$reviews = $review->getPropertyReviews($propertyData['id'], 10, 0);

require_once '../templates/header.php';
?>

<style>
    .property-header {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .property-images {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .property-image {
        width: 100%;
        height: 200px;
        background-color: #ddd;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        overflow: hidden;
        border: 0;
        cursor: pointer;
        padding: 0;
    }

    .property-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-lightbox {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.86);
    }

    .image-lightbox.active {
        display: flex;
    }

    .image-lightbox img {
        max-width: 95vw;
        max-height: 88vh;
        object-fit: contain;
        border-radius: 8px;
        background: #111;
    }

    .lightbox-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.94);
        color: #2c3e50;
        cursor: pointer;
        font-size: 1.7rem;
        line-height: 1;
    }

    .property-video {
        width: 100%;
        margin-top: 1.5rem;
        border-radius: 8px;
        background: #000;
        max-height: 460px;
    }

    .property-info {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .availability-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.4rem 0.75rem;
        margin-bottom: 1rem;
        background: #e9f7ef;
        color: #1e8449;
        font-weight: 800;
        font-size: 0.9rem;
    }

    .availability-badge.booked {
        background: #fdecea;
        color: #922b21;
    }

    .booked-panel {
        padding: 0.9rem;
        border: 1px solid #f1c7c2;
        border-radius: 8px;
        background: #fff6f5;
        color: #922b21;
        text-align: center;
        font-weight: 700;
        margin-top: 1rem;
    }

    .amenities {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin: 1.5rem 0;
    }

    .amenity {
        padding: 0.5rem;
        background-color: #ecf0f1;
        border-radius: 4px;
    }

    .landlord-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .landlord-info {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .landlord-avatar {
        width: 60px;
        height: 60px;
        background-color: #3498db;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        overflow: hidden;
        flex: 0 0 auto;
    }

    .landlord-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .reviews {
        margin-top: 2rem;
    }

    .review {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .review-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .review-author {
        font-weight: 600;
    }

    .review-rating {
        color: #f39c12;
    }

    .review-form {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin: 2rem 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .review-form .form-group {
        margin-bottom: 1rem;
    }

    .review-form label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #2c3e50;
    }

    .review-form input,
    .review-form select,
    .review-form textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
        font-size: 1rem;
    }

    .review-form textarea {
        min-height: 110px;
        resize: vertical;
    }
</style>

<div class="property-header">
    <div>
        <h1><?php echo htmlspecialchars($propertyData['name']); ?></h1>
        <p><?php echo htmlspecialchars($propertyData['address']); ?>, <?php echo htmlspecialchars($propertyData['city']); ?></p>
        <div class="rating" style="font-size: 1.2rem; margin: 1rem 0;">
            <?php if ($propertyData['avg_rating']): ?>
                ⭐ <?php echo number_format($propertyData['avg_rating'], 1); ?> (<?php echo $propertyData['review_count']; ?> reviews)
            <?php else: ?>
                No ratings yet
            <?php endif; ?>
        </div>

        <div class="property-images">
            <?php
            $images = $propertyData['image_urls'];
            if (is_array($images)) {
                foreach (array_slice($images, 0, 4) as $image):
            ?>
                    <button type="button" class="property-image" onclick="openImageLightbox('<?php echo htmlspecialchars($image, ENT_QUOTES); ?>')" aria-label="View full property image">
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Property image">
                    </button>
                <?php endforeach;
            } else {
                for ($i = 0; $i < 4; $i++):
                ?>
                    <div class="property-image">🏘️</div>
            <?php endfor;
            }
            ?>
        </div>

        <?php if (!empty($propertyData['video_url'])): ?>
            <h2 style="margin-top: 2rem;">Interior Video</h2>
            <video class="property-video" controls preload="metadata">
                <source src="<?php echo htmlspecialchars($propertyData['video_url']); ?>">
                Your browser does not support the video tag.
            </video>
        <?php endif; ?>

        <h2 style="margin-top: 2rem;">Description</h2>
        <p><?php echo nl2br(htmlspecialchars($propertyData['description'])); ?></p>

        <h2 style="margin-top: 1.5rem;">Features</h2>
        <ul>
            <li><strong><?php echo $propertyData['bedrooms']; ?></strong> Bedrooms</li>
            <li><strong><?php echo $propertyData['bathrooms']; ?></strong> Bathrooms</li>
            <li><strong><?php echo $propertyData['square_feet']; ?></strong> Square Feet</li>
            <li><strong>Max Occupants:</strong> <?php echo $propertyData['max_occupants']; ?></li>
            <li><strong>Furnished:</strong> <?php echo $propertyData['furnished'] ? 'Yes' : 'No'; ?></li>
            <li><strong>Type:</strong> <?php echo ucfirst($propertyData['property_type']); ?></li>
        </ul>

        <?php if (!empty($propertyData['amenities']) && is_array($propertyData['amenities'])): ?>
            <h2 style="margin-top: 1.5rem;">Amenities</h2>
            <div class="amenities">
                <?php foreach ($propertyData['amenities'] as $amenity): ?>
                    <div class="amenity">✓ <?php echo htmlspecialchars($amenity); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="review-form">
            <h2>Rate This Property</h2>

            <?php if ($reviewError): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($reviewError); ?></div>
            <?php endif; ?>

            <?php if ($reviewSuccess): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($reviewSuccess); ?></div>
            <?php endif; ?>

            <?php if (isAuthenticated() && getCurrentUserRole() === 'student'): ?>
                <form method="post">
                    <input type="hidden" name="action" value="submit_property_review">
                    <div class="form-group">
                        <label for="rating">Rating *</label>
                        <select id="rating" name="rating" required>
                            <option value="">Select rating</option>
                            <option value="5">5 stars - Excellent</option>
                            <option value="4">4 stars - Good</option>
                            <option value="3">3 stars - Okay</option>
                            <option value="2">2 stars - Poor</option>
                            <option value="1">1 star - Bad</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="review-title">Title *</label>
                        <input type="text" id="review-title" name="title" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="review-comment">Review *</label>
                        <textarea id="review-comment" name="comment" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Submit Review</button>
                </form>
                <small style="display: block; margin-top: 0.75rem; color: #7f8c8d;">Only students with a completed booking for this property can submit a review.</small>
            <?php elseif (!isAuthenticated()): ?>
                <p><a href="<?php echo pageUrl('login.php'); ?>">Login as a student</a> to rate this property after completing a booking.</p>
            <?php else: ?>
                <p style="color: #7f8c8d;">Only students can rate properties.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="property-info">
            <?php $isBooked = !empty($propertyData['active_booking_count']); ?>
            <span class="availability-badge <?php echo $isBooked ? 'booked' : ''; ?>">
                <?php echo $isBooked ? 'Booked' : 'Available'; ?>
            </span>

            <div style="font-size: 2rem; color: #27ae60; margin-bottom: 1rem;">
                <?php echo formatCurrency($propertyData['price_per_month']); ?>
                <small style="font-size: 1rem; color: #666;">/month</small>
            </div>

            <p><strong>Available from:</strong> <?php echo formatDate($propertyData['availability_date']); ?></p>

            <?php if ($isBooked): ?>
                <div class="booked-panel">This property already has an active booking.</div>
            <?php elseif (isAuthenticated() && getCurrentUserRole() === 'student'): ?>
                <a href="<?php echo pageUrl('book.php?id=' . $propertyData['id']); ?>" class="btn btn-success" style="width: 100%; margin-top: 1rem;">Book Now</a>
            <?php elseif (!isAuthenticated()): ?>
                <p style="text-align: center; margin-top: 1rem;"><a href="<?php echo pageUrl('login.php'); ?>" class="btn">Login to Book</a></p>
            <?php else: ?>
                <p style="text-align: center; margin-top: 1rem; color: #7f8c8d;">Students can book this property</p>
            <?php endif; ?>
        </div>

        <div class="landlord-card">
            <h3>Property Owner</h3>
            <div class="landlord-info">
                <div class="landlord-avatar">
                    <?php if (!empty($propertyData['landlord_profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($propertyData['landlord_profile_picture']); ?>" alt="<?php echo htmlspecialchars($propertyData['landlord_first_name'] . ' ' . $propertyData['landlord_last_name']); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars(strtoupper(substr($propertyData['landlord_first_name'] ?? 'U', 0, 1) . substr($propertyData['landlord_last_name'] ?? '', 0, 1))); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div><strong><?php echo htmlspecialchars($propertyData['landlord_first_name'] . ' ' . $propertyData['landlord_last_name']); ?></strong></div>
                    <div style="font-size: 0.9rem; color: #7f8c8d;"><?php echo htmlspecialchars($propertyData['landlord_email']); ?></div>
                    <?php if ($propertyData['landlord_rating']): ?>
                        <div style="color: #f39c12;">⭐ <?php echo $propertyData['landlord_rating']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (isAuthenticated()): ?>
                <a href="<?php echo pageUrl('messages.php?user_id=' . $propertyData['landlord_id']); ?>" class="btn" style="width: 100%;">Send Message</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="image-lightbox" id="image-lightbox" onclick="closeImageLightbox()">
    <button type="button" class="lightbox-close" onclick="closeImageLightbox()" aria-label="Close full image">&times;</button>
    <img id="lightbox-image" src="" alt="Full property image" onclick="event.stopPropagation()">
</div>

<script>
    function openImageLightbox(imageUrl) {
        const lightbox = document.getElementById('image-lightbox');
        const image = document.getElementById('lightbox-image');
        image.src = imageUrl;
        lightbox.classList.add('active');
    }

    function closeImageLightbox() {
        const lightbox = document.getElementById('image-lightbox');
        const image = document.getElementById('lightbox-image');
        lightbox.classList.remove('active');
        image.src = '';
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeImageLightbox();
        }
    });
</script>

<?php if (!empty($reviews)): ?>
    <h2>Reviews</h2>
    <div class="reviews">
        <?php foreach ($reviews as $rev): ?>
            <div class="review">
                <div class="review-header">
                    <div>
                        <div class="review-author"><?php echo htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']); ?></div>
                        <small style="color: #7f8c8d;"><?php echo formatDate($rev['created_at']); ?></small>
                    </div>
                    <div class="review-rating">⭐ <?php echo $rev['rating']; ?>/5</div>
                </div>
                <h4><?php echo htmlspecialchars($rev['title']); ?></h4>
                <p><?php echo htmlspecialchars($rev['comment']); ?></p>
                <small style="color: #7f8c8d;"><?php echo $rev['helpful_count']; ?> people found this helpful</small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../templates/footer.php'; ?>
