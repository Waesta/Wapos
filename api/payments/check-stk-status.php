<?php
/**
 * Check M-Pesa STK Push Payment Status
 * 
 * Query the status of an STK Push payment
 * 
 * GET/POST Parameters:
 * - checkout_request_id: The CheckoutRequestID from STK Push response
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once '../../includes/bootstrap.php';

use App\Services\Payments\Providers\MpesaGateway;
use App\Services\Payments\PaymentRequestStore;

header('Content-Type: application/json');

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Require authentication
$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    respond(401, ['success' => false, 'message' => 'Authentication required']);
}

// Get checkout request ID
$checkoutRequestId = $_GET['checkout_request_id'] ?? $_POST['checkout_request_id'] ?? '';

if (empty($checkoutRequestId)) {
    respond(400, ['success' => false, 'message' => 'checkout_request_id is required']);
}

// Check if M-Pesa is configured
$consumerKey = settings('mpesa_consumer_key', '');
$consumerSecret = settings('mpesa_consumer_secret', '');
$passkey = settings('mpesa_passkey', '');
$shortcode = settings('mpesa_shortcode', '');

if (empty($consumerKey) || empty($consumerSecret)) {
    respond(500, ['success' => false, 'message' => 'M-Pesa is not configured']);
}

try {
    // First check our local database
    $store = new PaymentRequestStore();
    $localRecord = $store->findByReference($checkoutRequestId);
    
    if ($localRecord && $localRecord['status'] === 'completed') {
        respond(200, [
            'success' => true,
            'status' => 'completed',
            'message' => 'Payment completed successfully',
            'receipt_number' => $localRecord['provider_reference'] ?? null,
            'amount' => $localRecord['amount'] ?? 0,
        ]);
    }
    
    if ($localRecord && $localRecord['status'] === 'failed') {
        respond(200, [
            'success' => true,
            'status' => 'failed',
            'message' => $localRecord['instructions'] ?? 'Payment failed',
        ]);
    }

    // Query M-Pesa API for status
    $gateway = new MpesaGateway([
        'consumer_key' => $consumerKey,
        'consumer_secret' => $consumerSecret,
        'passkey' => $passkey,
        'shortcode' => $shortcode,
        'environment' => settings('mpesa_environment', 'sandbox'),
    ]);

    $result = $gateway->querySTKStatus($checkoutRequestId);

    // Update local record if status changed
    if ($localRecord && $result['status'] !== 'pending') {
        $store->updateStatus($checkoutRequestId, $result['status'], [
            'message' => $result['result_desc'] ?? null,
            'raw_response' => $result['raw'] ?? null,
        ]);
    }

    respond(200, [
        'success' => true,
        'status' => $result['status'],
        'message' => $result['result_desc'] ?? 'Status: ' . $result['status'],
        'result_code' => $result['result_code'] ?? null,
    ]);

} catch (Throwable $e) {
    error_log("STK Status check error: " . $e->getMessage());
    
    respond(500, [
        'success' => false,
        'message' => 'Failed to check status: ' . $e->getMessage(),
    ]);
}
