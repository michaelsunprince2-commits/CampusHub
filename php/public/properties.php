<?php

/**
 * Properties Listing Page
 */

$pageTitle = 'Browse Properties';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Property.php';

$property = new Property($conn);

// Get filters
$filters = [
    'city' => $_GET['city'] ?? '',
    'property_type' => $_GET['property_type'] ?? '',
    'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
    'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
    'bedrooms' => isset($_GET['bedrooms']) ? (int)$_GET['bedrooms'] : null,
];

// Remove empty filters
$filters = array_filter($filters, fn($v) => $v !== '' && $v !== null);

$page = (int)($_GET['page'] ?? 1);
$limit = 12;

$properties = $property->listProperties($filters, $limit, ($page - 1) * $limit);

require_once '../templates/header.php';
?>

<style>
    .filters {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .filter-row input,
    .filter-row select {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a,
    .pagination span {
        padding: 0.5rem 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #3498db;
    }

    .pagination .active {
        background-color: #3498db;
        color: white;
        cursor: default;
    }

    .properties-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 2rem;
    }

    .card-image {
        position: relative;
    }

    .property-badge {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
        z-index: 2;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        background: #e9f7ef;
        color: #1e8449;
        font-size: 0.82rem;
        font-weight: 800;
        box-shadow: 0 6px 14px rgba(36, 51, 66, 0.14);
    }

    .property-badge.booked {
        background: #fdecea;
        color: #922b21;
    }
</style>

<h1>Browse Properties</h1>

<div class="filters">
    <form method="get" class="filter-row">
        <input type="text" name="city" placeholder="City" value="<?php echo htmlspecialchars($filters['city'] ?? ''); ?>">
        <select name="property_type">
            <option value="">All Types</option>
            <option value="apartment" <?php echo ($filters['property_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
            <option value="house" <?php echo ($filters['property_type'] ?? '') === 'house' ? 'selected' : ''; ?>>House</option>
            <option value="dorm" <?php echo ($filters['property_type'] ?? '') === 'dorm' ? 'selected' : ''; ?>>Dorm</option>
            <option value="shared" <?php echo ($filters['property_type'] ?? '') === 'shared' ? 'selected' : ''; ?>>Shared</option>
        </select>
        <input type="number" name="min_price" placeholder="Min Price" value="<?php echo $filters['min_price'] ?? ''; ?>">
        <input type="number" name="max_price" placeholder="Max Price" value="<?php echo $filters['max_price'] ?? ''; ?>">
        <input type="number" name="bedrooms" placeholder="Min Bedrooms" value="<?php echo $filters['bedrooms'] ?? ''; ?>">
        <button type="submit" class="btn">Filter</button>
        <a href="<?php echo pageUrl('properties.php'); ?>" class="btn" style="background-color: #95a5a6;">Clear</a>
    </form>
</div>

<?php if (empty($properties)): ?>
    <div class="alert alert-info">No properties found matching your criteria. Try adjusting your filters.</div>
<?php else: ?>
    <div class="properties-grid">
        <?php foreach ($properties as $prop): ?>
            <?php $isBooked = !empty($prop['active_booking_count']); ?>
            <div class="card">
                <div class="card-image">
                    <span class="property-badge <?php echo $isBooked ? 'booked' : ''; ?>"><?php echo $isBooked ? 'Booked' : 'Available'; ?></span>
                    <?php
                    $images = $prop['image_urls'];
                    if (!empty($images) && is_array($images) && !empty($images[0])):
                    ?>
                        <img src="<?php echo htmlspecialchars($images[0]); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($prop['name']); ?>">
                    <?php else: ?>
                        🏘️
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php echo htmlspecialchars($prop['name']); ?></div>
                    <p><small><?php echo htmlspecialchars($prop['address']); ?></small></p>
                    <div class="card-price"><?php echo formatCurrency($prop['price_per_month']); ?>/month</div>
                    <div class="rating">
                        <?php
                        if ($prop['avg_rating'] > 0):
                            echo '⭐ ' . number_format($prop['avg_rating'], 1) . ' (' . $prop['review_count'] . ')';
                        else:
                            echo 'No ratings yet';
                        endif;
                        ?>
                    </div>
                    <p><strong><?php echo $prop['bedrooms']; ?> beds</strong> | <strong><?php echo $prop['bathrooms']; ?> baths</strong> | <strong><?php echo $prop['square_feet']; ?> sqft</strong></p>
                    <a href="<?php echo pageUrl('property-details.php?id=' . $prop['id']); ?>" class="btn">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php foreach ($filters as $k => $v) echo "&$k=" . urlencode($v); ?>">← Previous</a>
        <?php endif; ?>

        <span class="active"><?php echo $page; ?></span>

        <?php if (count($properties) == $limit): ?>
            <a href="?page=<?php echo $page + 1; ?><?php foreach ($filters as $k => $v) echo "&$k=" . urlencode($v); ?>">Next →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once '../templates/footer.php'; ?>
