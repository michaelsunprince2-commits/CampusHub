<?php

/**
 * Landlord Dashboard
 */

$pageTitle = 'Landlord Dashboard';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['landlord']);

require_once '../models/Property.php';
require_once '../models/Booking.php';

$propertyModel = new Property($conn);
$bookingModel = new Booking($conn);

$properties = $propertyModel->getLandlordProperties(getCurrentUserId(), 50, 0);
$bookings = $bookingModel->getLandlordBookings(getCurrentUserId(), 50, 0);

require_once '../templates/header.php';
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: bold;
        color: #3498db;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: #7f8c8d;
    }

    .property-item {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        display: grid;
        grid-template-columns: 140px 1fr auto;
        gap: 1.25rem;
        align-items: center;
    }

    .property-thumb {
        width: 140px;
        height: 100px;
        border-radius: 8px;
        background: #ecf0f1;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: #7f8c8d;
        font-size: 2rem;
    }

    .property-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .property-item h3 {
        margin-bottom: 0.5rem;
    }

    .property-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #ecf0f1;
    }

    .tab {
        padding: 1rem;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }

    .tab.active {
        border-bottom-color: #3498db;
        color: #3498db;
        font-weight: 600;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    @media (max-width: 700px) {
        .property-item {
            grid-template-columns: 1fr;
        }

        .property-thumb {
            width: 100%;
            height: 180px;
        }
    }
</style>

<h1>Landlord Dashboard</h1>

<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo count($properties); ?></div>
        <div class="stat-label">Total Properties</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed')); ?></div>
        <div class="stat-label">Active Bookings</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'pending')); ?></div>
        <div class="stat-label">Pending Bookings</div>
    </div>
</div>

<div class="tabs">
    <div class="tab active" onclick="switchTab('properties')">📋 My Properties</div>
    <div class="tab" onclick="switchTab('bookings')">📅 Bookings</div>
    <div class="tab" onclick="switchTab('add-property')">➕ Add Property</div>
</div>

<div id="properties" class="tab-content active">
    <h2>My Properties</h2>
    <?php if (empty($properties)): ?>
        <div class="alert alert-info">You haven't listed any properties yet. <a href="#add-property" onclick="switchTab('add-property')">Add one now</a></div>
    <?php else: ?>
        <?php foreach ($properties as $prop): ?>
            <div class="property-item">
                <div class="property-thumb">
                    <?php
                    $images = $prop['image_urls'] ?? [];
                    if (!empty($images) && is_array($images) && !empty($images[0])):
                    ?>
                        <img src="<?php echo htmlspecialchars($images[0]); ?>" alt="<?php echo htmlspecialchars($prop['name']); ?>">
                    <?php else: ?>
                        🏠
                    <?php endif; ?>
                </div>
                <div>
                    <h3><?php echo htmlspecialchars($prop['name']); ?></h3>
                    <p><?php echo htmlspecialchars($prop['address']); ?>, <?php echo htmlspecialchars($prop['city']); ?></p>
                    <p><strong><?php echo formatCurrency($prop['price_per_month']); ?>/month</strong> | <?php echo $prop['bedrooms']; ?> bed | <?php echo $prop['bathrooms']; ?> bath</p>
                    <p style="color: #7f8c8d;">Status: <?php echo ucfirst($prop['verification_status']); ?></p>
                </div>
                <div class="property-actions">
                    <a href="<?php echo pageUrl('property-details.php?id=' . $prop['id']); ?>" class="btn">View</a>
                    <a href="<?php echo pageUrl('edit-property.php?id=' . $prop['id']); ?>" class="btn">Edit</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="bookings" class="tab-content">
    <h2>Bookings</h2>
    <?php if (empty($bookings)): ?>
        <div class="alert alert-info">No bookings yet.</div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #ecf0f1; border-bottom: 2px solid #bdc3c7;">
                    <th style="padding: 1rem; text-align: left;">Property</th>
                    <th style="padding: 1rem; text-align: left;">Guest</th>
                    <th style="padding: 1rem; text-align: left;">Check-in</th>
                    <th style="padding: 1rem; text-align: left;">Check-out</th>
                    <th style="padding: 1rem; text-align: left;">Status</th>
                    <th style="padding: 1rem; text-align: left;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr style="border-bottom: 1px solid #ecf0f1;">
                        <td style="padding: 1rem;"><?php echo htmlspecialchars($booking['property_name']); ?></td>
                        <td style="padding: 1rem;"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                        <td style="padding: 1rem;"><?php echo formatDate($booking['check_in_date']); ?></td>
                        <td style="padding: 1rem;"><?php echo formatDate($booking['check_out_date']); ?></td>
                        <td style="padding: 1rem;">
                            <span class="booking-status status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 1rem;"><?php echo formatCurrency($booking['total_price']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="add-property" class="tab-content">
    <h2>Add New Property</h2>
    <p><a href="new-property.php" class="btn btn-success">Create New Listing</a></p>
</div>

<script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Remove active class from all tab buttons
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabName).classList.add('active');

        // Add active class to clicked tab button
        event.target.classList.add('active');
    }
</script>

<?php require_once '../templates/footer.php'; ?>
