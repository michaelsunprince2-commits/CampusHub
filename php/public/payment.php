<?php

/**
 * Payment Page
 */

$pageTitle = 'Payment';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['student']);

require_once '../models/Booking.php';
require_once '../models/Payment.php';
require_once '../config/paystack.php';

if (!isset($_GET['booking_id'])) {
    header('Location: ' . pageUrl('bookings.php'));
    exit();
}

$bookingModel = new Booking($conn);
$paymentModel = new Payment($conn);

$booking = $bookingModel->getById((int)$_GET['booking_id']);

if (!$booking || $booking['student_id'] != getCurrentUserId()) {
    header('Location: ' . pageUrl('bookings.php'));
    exit();
}

$error = '';
$success = '';
$paystackReference = $_GET['reference'] ?? '';

// Check if payment already exists
$existingPayment = $paymentModel->getByBookingId($booking['id']);

if ($existingPayment && $existingPayment['status'] === 'completed') {
    $success = 'Payment processed successfully!';
}

require_once '../templates/header.php';
?>

<style>
    .payment-container {
        max-width: 980px;
        margin: 0 auto;
    }

    .payment-shell {
        background: #ffffff;
        border: 1px solid #e3edf2;
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(36, 51, 66, 0.1);
        overflow: hidden;
    }

    .payment-header {
        padding: 1.5rem 1.75rem;
        background: linear-gradient(135deg, #243342 0%, #1f6f78 100%);
        color: #ffffff;
    }

    .payment-header h2 {
        color: #ffffff;
        margin-bottom: 0.35rem;
    }

    .payment-header p {
        color: #d8e8eb;
        margin-bottom: 0;
    }

    .payment-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 1.25rem;
        padding: 1.5rem;
    }

    .payment-summary {
        border: 1px solid #e3edf2;
        background: #fbfcfd;
        padding: 1.25rem;
        border-radius: 8px;
        margin-bottom: 0;
    }

    .payment-summary h3,
    .paystack-card h3 {
        margin-bottom: 0.85rem;
        color: #243342;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.8rem 0;
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
        border-bottom: none;
        border-top: 1px solid #d7e3ea;
        margin-top: 0.35rem;
        padding-top: 1rem;
        font-weight: bold;
        font-size: 1.2rem;
    }

    .summary-total span:last-child {
        color: #1f6f78;
        font-size: 1.35rem;
    }

    .paystack-card {
        border: 1px solid #dfeaf0;
        border-radius: 8px;
        padding: 1.25rem;
        background: #ffffff;
        box-shadow: 0 8px 20px rgba(36, 51, 66, 0.06);
    }

    .paystack-card p {
        color: #657786;
        margin-bottom: 1rem;
    }

    .paystack-test-note {
        padding: 0.9rem;
        border-radius: 8px;
        border: 1px solid #f3dd9a;
        background: #fff9e8;
        color: #6f5200;
        margin-bottom: 1rem;
        font-size: 0.95rem;
        line-height: 1.45;
    }

    .paystack-actions {
        display: grid;
        gap: 0.65rem;
    }

    .paystack-btn {
        width: 100%;
        padding: 1rem;
        border-radius: 8px;
        font-weight: 800;
        background: #1f6f78;
        box-shadow: 0 8px 18px rgba(31, 111, 120, 0.18);
    }

    .paystack-btn:hover {
        background: #195f67;
    }

    .paystack-btn:disabled {
        background: #9dafb7;
        cursor: not-allowed;
        box-shadow: none;
    }

    .payment-back {
        width: 100%;
        text-align: center;
        background: #eef3f6;
        color: #243342;
    }

    .payment-back:hover {
        background: #dfe8ee;
        color: #243342;
    }

    .success-message {
        text-align: center;
        padding: 3rem 1.5rem;
    }

    .success-icon {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        margin: 0 auto 1rem;
        background: #e9f7ef;
        color: #1e8449;
        font-size: 2.2rem;
        font-weight: 900;
    }

    .success-message p {
        color: #657786;
    }

    @media (max-width: 860px) {
        .payment-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .payment-header,
        .payment-layout {
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

<div class="payment-container">
    <div class="payment-shell">
        <?php if ($success): ?>
            <div class="success-message">
                <div class="success-icon">✓</div>
                <h2><?php echo $success; ?></h2>
                <p>Your booking for <strong><?php echo htmlspecialchars($booking['property_name']); ?></strong> has been confirmed.</p>
                <p>Check-in: <?php echo formatDate($booking['check_in_date']); ?><br>
                    Check-out: <?php echo formatDate($booking['check_out_date']); ?></p>
                <a href="bookings.php" class="btn" style="margin-top: 1rem;">View My Bookings</a>
            </div>
        <?php else: ?>
            <div class="payment-header">
                <h2>Complete Your Booking</h2>
                <p>Review your stay details and continue to secure Paystack checkout.</p>
            </div>

            <div class="payment-layout">
                <section class="payment-summary">
                    <h3>Booking Summary</h3>
                    <div class="summary-row">
                        <span>Property</span>
                        <span><?php echo htmlspecialchars($booking['property_name']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Check-in</span>
                        <span><?php echo formatDate($booking['check_in_date']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Check-out</span>
                        <span><?php echo formatDate($booking['check_out_date']); ?></span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total Amount</span>
                        <span><?php echo formatCurrency($booking['total_price']); ?></span>
                    </div>
                </section>

                <section class="paystack-card">
                    <h3>Pay securely with Paystack</h3>
                    <p>Your payment is processed through Paystack test checkout for local development.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($paystackReference): ?>
                        <div class="alert alert-info" id="paystack-verifying">Verifying your Paystack payment...</div>
                    <?php endif; ?>

                    <?php if (!isPaystackConfigured()): ?>
                        <div class="paystack-test-note">
                            Add your Paystack test keys in <code>php/config/paystack.php</code> before starting checkout.
                        </div>
                    <?php else: ?>
                        <div class="paystack-test-note">
                            Test mode is enabled. Use Paystack test payment details from your Paystack dashboard.
                        </div>
                    <?php endif; ?>

                    <div class="paystack-actions">
                        <button type="button"
                            class="btn paystack-btn"
                            id="paystack-pay"
                            data-booking-id="<?php echo (int)$booking['id']; ?>"
                            <?php echo isPaystackConfigured() ? '' : 'disabled'; ?>>
                            Pay with Paystack
                        </button>
                        <a href="bookings.php" class="btn payment-back">Back to Bookings</a>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const payButton = document.getElementById('paystack-pay');
        const reference = <?php echo json_encode($paystackReference); ?>;
        const bookingId = <?php echo (int)$booking['id']; ?>;
        const apiBase = <?php echo json_encode(getBaseUrl() . '/php/api/payments.php'); ?>;

        async function requestJson(url, options = {}) {
            const response = await fetch(url, options);
            return response.json();
        }

        if (payButton) {
            payButton.addEventListener('click', async function() {
                payButton.disabled = true;
                payButton.textContent = 'Starting Paystack checkout...';

                try {
                    const result = await requestJson(apiBase + '?action=paystack-initialize', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            booking_id: Number(payButton.dataset.bookingId)
                        })
                    });

                    if (!result.success) {
                        throw new Error(result.message || 'Unable to start Paystack checkout.');
                    }

                    window.location.href = result.data.authorization_url;
                } catch (error) {
                    alert(error.message);
                    payButton.disabled = false;
                    payButton.textContent = 'Pay with Paystack';
                }
            });
        }

        if (reference) {
            requestJson(apiBase + '?action=paystack-verify&reference=' + encodeURIComponent(reference))
                .then(result => {
                    if (result.success) {
                        window.location.href = <?php echo json_encode(pageUrl('payment.php?booking_id=' . $booking['id'])); ?>;
                        return;
                    }

                    const verifying = document.getElementById('paystack-verifying');
                    if (verifying) {
                        verifying.className = 'alert alert-error';
                        verifying.textContent = result.message || 'Payment verification failed.';
                    }
                })
                .catch(() => {
                    const verifying = document.getElementById('paystack-verifying');
                    if (verifying) {
                        verifying.className = 'alert alert-error';
                        verifying.textContent = 'Payment verification failed. Please try again.';
                    }
                });
        }
    });
</script>

<?php require_once '../templates/footer.php'; ?>
