<?php

/**
 * Payments API Endpoint
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/Payment.php';
require_once '../models/Booking.php';
require_once '../config/paystack.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$payment = new Payment($conn);
$bookingModel = new Booking($conn);

function paystackRequest($endpoint, $method = 'GET', $payload = null)
{
    if (!isPaystackConfigured()) {
        jsonResponse(false, 'Paystack test secret key is not configured.');
    }

    $ch = curl_init('https://api.paystack.co' . $endpoint);
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        jsonResponse(false, 'Paystack request failed: ' . $error);
    }

    $decoded = json_decode($response, true);
    if ($statusCode >= 400 || !$decoded) {
        jsonResponse(false, 'Paystack returned an invalid response.');
    }

    return $decoded;
}

function generatePaystackReference($bookingId)
{
    return 'CN-' . (int)$bookingId . '-' . time() . '-' . bin2hex(random_bytes(4));
}

function publicPageUrl($page)
{
    return getBaseUrl() . '/php/public/' . ltrim($page, '/');
}

try {
    switch ($action) {
        case 'create':
            // Create payment
            requireAuth(['student']);

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['booking_id'], $data['amount'], $data['payment_method'])) {
                jsonResponse(false, 'Missing required fields');
            }

            $result = $payment->create(
                (int)$data['booking_id'],
                (float)$data['amount'],
                $data['payment_method']
            );

            jsonResponse($result['success'], $result['message'], ['payment_id' => $result['payment_id'] ?? null]);
            break;

        case 'paystack-initialize':
            requireAuth(['student']);

            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $bookingId = (int)($data['booking_id'] ?? 0);
            $booking = $bookingModel->getById($bookingId);

            if (!$booking || (int)$booking['student_id'] !== (int)getCurrentUserId()) {
                jsonResponse(false, 'Booking not found');
            }

            $existingPayment = $payment->getByBookingId($bookingId);
            if ($existingPayment && $existingPayment['status'] === 'completed') {
                jsonResponse(false, 'This booking has already been paid.');
            }

            if (!$existingPayment) {
                $created = $payment->create($bookingId, (float)$booking['total_price'], 'debit_card');
                if (!$created['success']) {
                    jsonResponse(false, $created['message']);
                }
                $paymentId = $created['payment_id'];
            } else {
                $paymentId = (int)$existingPayment['id'];
            }

            $reference = generatePaystackReference($bookingId);
            $payment->setTransactionReference($paymentId, $reference);

            $callbackUrl = publicPageUrl('payment.php?booking_id=' . $bookingId);
            $amountInKobo = (int)round(((float)$booking['total_price']) * 100);

            $paystack = paystackRequest('/transaction/initialize', 'POST', [
                'email' => $booking['email'],
                'amount' => (string)$amountInKobo,
                'currency' => PAYSTACK_CURRENCY,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'booking_id' => $bookingId,
                    'payment_id' => $paymentId,
                    'property_name' => $booking['property_name'],
                ],
            ]);

            if (empty($paystack['status']) || empty($paystack['data']['authorization_url'])) {
                jsonResponse(false, $paystack['message'] ?? 'Unable to initialize Paystack payment.');
            }

            jsonResponse(true, 'Paystack payment initialized', [
                'authorization_url' => $paystack['data']['authorization_url'],
                'reference' => $reference,
                'payment_id' => $paymentId,
            ]);
            break;

        case 'paystack-verify':
            requireAuth(['student']);

            $reference = $_GET['reference'] ?? '';
            if ($reference === '') {
                jsonResponse(false, 'Payment reference is required.');
            }

            $paymentData = $payment->getByTransactionId($reference);
            if (!$paymentData) {
                jsonResponse(false, 'Payment reference not found.');
            }

            $booking = $bookingModel->getById((int)$paymentData['booking_id']);
            if (!$booking || (int)$booking['student_id'] !== (int)getCurrentUserId()) {
                jsonResponse(false, 'Unauthorized payment verification.');
            }

            $paystack = paystackRequest('/transaction/verify/' . rawurlencode($reference));

            if (empty($paystack['status']) || empty($paystack['data'])) {
                jsonResponse(false, $paystack['message'] ?? 'Unable to verify Paystack payment.');
            }

            $transaction = $paystack['data'];
            $expectedAmount = (int)round(((float)$paymentData['amount']) * 100);
            $paidAmount = (int)($transaction['amount'] ?? 0);
            $paidCurrency = $transaction['currency'] ?? '';

            if (($transaction['status'] ?? '') === 'success' && $paidAmount >= $expectedAmount && $paidCurrency === PAYSTACK_CURRENCY) {
                $result = $payment->complete((int)$paymentData['id'], $reference);
                jsonResponse($result['success'], $result['message'], ['payment' => $payment->getById((int)$paymentData['id'])]);
            }

            $payment->fail((int)$paymentData['id']);
            jsonResponse(false, 'Paystack payment was not successful.');
            break;

        case 'get':
            // Get payment details
            requireAuth();

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Payment ID required');
            }

            $paymentData = $payment->getById((int)$_GET['id']);

            if (!$paymentData) {
                jsonResponse(false, 'Payment not found');
            }

            jsonResponse(true, 'Payment retrieved', $paymentData);
            break;

        case 'complete':
            // Mark payment as complete (simulate payment processing)
            requireAuth();

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Payment ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $result = $payment->complete((int)$_GET['id'], $data['transaction_id'] ?? null);
            jsonResponse($result['success'], $result['message']);
            break;

        case 'fail':
            // Mark payment as failed
            requireAuth();

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Payment ID required');
            }

            $result = $payment->fail((int)$_GET['id']);
            jsonResponse($result['success'], $result['message']);
            break;

        case 'refund':
            // Refund payment
            requireAuth(['admin']);

            if ($method !== 'PUT') {
                jsonResponse(false, 'Method not allowed');
            }

            if (!isset($_GET['id'])) {
                jsonResponse(false, 'Payment ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $result = $payment->refund((int)$_GET['id'], $data['reason'] ?? '');
            jsonResponse($result['success'], $result['message']);
            break;

        case 'my-payments':
            // Get user payments
            requireAuth();

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            $results = $payment->getUserPayments(getCurrentUserId(), $limit, $offset);
            jsonResponse(true, 'Payments retrieved', ['payments' => $results]);
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
