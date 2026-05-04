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
    }

    .property-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
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
                    <div class="property-image">
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Property image">
                    </div>
                <?php endforeach;
            } else {
                for ($i = 0; $i < 4; $i++):
                ?>
                    <div class="property-image">🏘️</div>
            <?php endfor;
            }
            ?>
        </div>

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
