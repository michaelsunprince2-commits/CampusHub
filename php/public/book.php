<?php

/**
 * Booking Page
 */

$pageTitle = 'Book Property';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['student']);

require_once '../models/Property.php';
require_once '../models/Booking.php';

if (!isset($_GET['id'])) {
    header('Location: ' . pageUrl('properties.php'));
    exit();
}

$propertyModel = new Property($conn);
$propertyData = $propertyModel->getById((int)$_GET['id']);

if (!$propertyData) {
    header('Location: ' . pageUrl('properties.php'));
    exit();
}

if (!empty($propertyData['active_booking_count'])) {
    require_once '../templates/header.php';
?>
    <style>
        .booked-notice {
            max-width: 620px;
            margin: 3rem auto;
            padding: 2rem;
            border: 1px solid #f1c7c2;
            border-radius: 8px;
            background: #fff6f5;
            text-align: center;
            box-shadow: 0 10px 28px rgba(36, 51, 66, 0.08);
        }

        .booked-notice h2 {
            color: #922b21;
        }

        .booked-notice p {
            color: #657786;
        }
    </style>
    <div class="booked-notice">
        <h2>Property Already Booked</h2>
        <p><?php echo htmlspecialchars($propertyData['name']); ?> currently has an active booking, so it cannot be booked again.</p>
        <a href="<?php echo pageUrl('property-details.php?id=' . $propertyData['id']); ?>" class="btn">Back to Property</a>
    </div>
<?php
    require_once '../templates/footer.php';
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkInDate = $_POST['check_in_date'] ?? '';
    $checkOutDate = $_POST['check_out_date'] ?? '';
    $occupants = (int)($_POST['occupants'] ?? 1);

    if (empty($checkInDate) || empty($checkOutDate)) {
        $error = 'Check-in and check-out dates are required';
    } else {
        $bookingModel = new Booking($conn);
        $result = $bookingModel->create(
            $propertyData['id'],
            getCurrentUserId(),
            $checkInDate,
            $checkOutDate,
            $occupants
        );

        if ($result['success']) {
            header('Location: ' . pageUrl('payment.php') . '?booking_id=' . $result['booking_id']);
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

require_once '../templates/header.php';
?>

<style>
    .booking-shell {
        max-width: 980px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #e3edf2;
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(36, 51, 66, 0.1);
        overflow: hidden;
    }

    .booking-hero {
        padding: 1.5rem 1.75rem;
        background: linear-gradient(135deg, #243342 0%, #1f6f78 100%);
        color: #ffffff;
    }

    .booking-hero h2 {
        color: #ffffff;
        margin-bottom: 0.35rem;
    }

    .booking-hero p {
        color: #d8e8eb;
        margin-bottom: 0;
    }

    .booking-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 1.25rem;
        padding: 1.5rem;
        align-items: start;
    }

    .booking-card {
        border: 1px solid #e3edf2;
        border-radius: 8px;
        padding: 1.25rem;
        background: #ffffff;
    }

    .booking-card h3 {
        margin-bottom: 1rem;
        color: #243342;
    }

    .booking-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .booking-form-grid .form-group:last-child {
        grid-column: 1 / -1;
    }

    .booking-card input {
        min-height: 46px;
        border-radius: 8px;
        border-color: #d8e3ea;
    }

    .booking-card input:focus {
        border-color: #1f6f78;
        box-shadow: 0 0 0 3px rgba(31, 111, 120, 0.14);
    }

    .summary {
        background: #fbfcfd;
        border: 1px solid #e3edf2;
        padding: 1.25rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .summary h3 {
        margin-bottom: 1rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e8eef2;
    }

    .summary-row span:first-child {
        color: #657786;
    }

    .summary-row span:last-child {
        color: #243342;
        font-weight: 700;
        text-align: right;
    }

    .summary-total {
        border-top: 1px solid #d7e3ea;
        border-bottom: none;
        margin-top: 0.35rem;
        padding-top: 1rem;
        font-size: 1.15rem;
    }

    .summary-total span:last-child {
        color: #1f6f78;
        font-size: 1.35rem;
        font-weight: 900;
    }

    .rent-note {
        color: #657786;
        font-size: 0.92rem;
        line-height: 1.45;
        margin-bottom: 1rem;
    }

    .booking-actions {
        display: grid;
        gap: 0.65rem;
    }

    .booking-submit {
        width: 100%;
        padding: 1rem;
        border-radius: 8px;
        font-weight: 800;
        box-shadow: 0 8px 18px rgba(39, 174, 96, 0.18);
    }

    .booking-back {
        width: 100%;
        text-align: center;
        background: #eef3f6;
        color: #243342;
    }

    .booking-back:hover {
        background: #dfe8ee;
        color: #243342;
    }

    @media (max-width: 860px) {
        .booking-layout,
        .booking-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .booking-hero,
        .booking-layout {
            padding: 1.15rem;
        }

        .summary-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.25rem;
        }

        .summary-row span:last-child {
            text-align: left;
        }
    }
</style>

<div class="booking-shell">
    <div class="booking-hero">
        <h2>Book: <?php echo htmlspecialchars($propertyData['name']); ?></h2>
        <p>Choose your rental period and review the rent before continuing to payment.</p>
    </div>

    <form method="post" class="booking-layout">
        <section class="booking-card">
            <h3>Rental Details</h3>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="booking-form-grid">
                <div class="form-group">
                    <label for="check_in_date">Move-in Date</label>
                    <input type="date" id="check_in_date" name="check_in_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="check_out_date">Move-out Date</label>
                    <input type="date" id="check_out_date" name="check_out_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>

                <div class="form-group">
                    <label for="occupants">Number of Occupants</label>
                    <input type="number" id="occupants" name="occupants" min="1" max="<?php echo $propertyData['max_occupants']; ?>" value="1" required>
                    <small>Maximum allowed: <?php echo $propertyData['max_occupants']; ?> occupants</small>
                </div>
            </div>
        </section>

        <aside>
            <div class="summary">
                <h3>Rent Summary</h3>
                <div class="summary-row">
                    <span>Monthly Rent</span>
                    <span><?php echo formatCurrency($propertyData['price_per_month']); ?></span>
                </div>
                <div class="summary-row">
                    <span>Rental Duration</span>
                    <span id="duration-display">Select dates</span>
                </div>
                <div class="summary-row">
                    <span>Billing Period</span>
                    <span id="billing-display">Monthly rent</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total Rent</span>
                    <span id="total-price"><?php echo formatCurrency(0); ?></span>
                </div>
                <p class="rent-note">Rent is calculated from the monthly rate. Exact yearly stays count as 12 months, while partial extra months are rounded up.</p>
            </div>

            <div class="booking-actions">
                <button type="submit" class="btn btn-success booking-submit">Proceed to Payment</button>
                <a href="<?php echo pageUrl('property-details.php?id=' . $propertyData['id']); ?>" class="btn booking-back">Back to Property</a>
            </div>
        </aside>
    </form>
</div>

<script>
    const pricePerMonth = <?php echo json_encode((float)$propertyData['price_per_month']); ?>;

    function formatNaira(amount) {
        const value = Number(amount || 0);
        return '\u20A6' + value.toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getRentalMonths(checkIn, checkOut) {
        let months = ((checkOut.getFullYear() - checkIn.getFullYear()) * 12) + (checkOut.getMonth() - checkIn.getMonth());

        if (checkOut.getDate() > checkIn.getDate()) {
            months += 1;
        }

        return Math.max(1, months);
    }

    function formatRentalDuration(days, months) {
        if (months >= 12 && months % 12 === 0) {
            const years = months / 12;
            return `${days} day${days === 1 ? '' : 's'} (${years} year${years === 1 ? '' : 's'})`;
        }

        return `${days} day${days === 1 ? '' : 's'} (${months} month${months === 1 ? '' : 's'})`;
    }

    function calculateTotal() {
        const checkInValue = document.getElementById('check_in_date').value;
        const checkOutValue = document.getElementById('check_out_date').value;
        const durationDisplay = document.getElementById('duration-display');
        const billingDisplay = document.getElementById('billing-display');
        const totalPrice = document.getElementById('total-price');

        if (!checkInValue || !checkOutValue) {
            durationDisplay.textContent = 'Select dates';
            billingDisplay.textContent = 'Monthly rent';
            totalPrice.textContent = formatNaira(0);
            return;
        }

        const checkIn = new Date(checkInValue);
        const checkOut = new Date(checkOutValue);

        if (checkIn && checkOut && checkOut > checkIn) {
            const days = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            const months = getRentalMonths(checkIn, checkOut);
            const total = months * pricePerMonth;

            durationDisplay.textContent = formatRentalDuration(days, months);
            billingDisplay.textContent = months >= 12 ? 'Yearly rent equivalent' : 'Monthly rent';
            totalPrice.textContent = formatNaira(total);
        } else {
            durationDisplay.textContent = 'Choose a later move-out date';
            billingDisplay.textContent = 'Monthly rent';
            totalPrice.textContent = formatNaira(0);
        }
    }

    document.getElementById('check_in_date').addEventListener('change', calculateTotal);
    document.getElementById('check_out_date').addEventListener('change', calculateTotal);
    document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

<?php require_once '../templates/footer.php'; ?>
