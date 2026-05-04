<?php

/**
 * Bookings List Page (Student)
 */

$pageTitle = 'My Bookings';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['student']);

require_once '../models/Booking.php';

$bookingModel = new Booking($conn);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Changed mind');

    if ($bookingId <= 0) {
        $error = 'Invalid booking selected.';
    } else {
        $result = $bookingModel->cancel($bookingId, getCurrentUserId(), $reason);
        if ($result['success']) {
            header('Location: ' . pageUrl('bookings.php?cancelled=1'));
            exit();
        }

        $error = $result['message'];
    }
}

$page = (int)($_GET['page'] ?? 1);
$bookings = $bookingModel->getStudentBookings(getCurrentUserId(), 10, ($page - 1) * 10);

require_once '../templates/header.php';
?>

<style>
    .booking-item {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 2rem;
        align-items: start;
    }

    .booking-details h3 {
        margin-bottom: 0.5rem;
    }

    .booking-status {
        padding: 0.5rem 1rem;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
        margin: 0.5rem 0;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-confirmed {
        background-color: #d4edda;
        color: #155724;
    }

    .status-cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }

    .booking-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
</style>

<h1>My Bookings</h1>

<?php if (!empty($_GET['cancelled'])): ?>
    <div class="alert alert-success">Booking cancelled successfully.</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($bookings)): ?>
    <div class="alert alert-info">You haven't made any bookings yet. <a href="<?php echo pageUrl('properties.php'); ?>">Browse properties</a></div>
<?php else: ?>
    <?php foreach ($bookings as $booking): ?>
        <div class="booking-item">
            <div class="booking-details">
                <h3><?php echo htmlspecialchars($booking['property_name']); ?></h3>
                <p><?php echo htmlspecialchars($booking['address']); ?></p>
                <p>
                    <strong>Check-in:</strong> <?php echo formatDate($booking['check_in_date']); ?><br>
                    <strong>Check-out:</strong> <?php echo formatDate($booking['check_out_date']); ?><br>
                    <strong>Total:</strong> <?php echo formatCurrency($booking['total_price']); ?>
                </p>
                <span class="booking-status status-<?php echo $booking['status']; ?>">
                    <?php echo ucfirst($booking['status']); ?>
                </span>
            </div>
            <div class="booking-actions">
                <a href="<?php echo pageUrl('property-details.php?id=' . $booking['property_id']); ?>" class="btn">View Property</a>
                <?php if ($booking['status'] === 'pending'): ?>
                    <a href="<?php echo pageUrl('payment.php?booking_id=' . $booking['id']); ?>" class="btn btn-success">Pay Now</a>
                <?php elseif ($booking['status'] === 'confirmed'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                        <input type="hidden" name="reason" value="Changed mind">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Cancel Booking</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../templates/footer.php'; ?>
