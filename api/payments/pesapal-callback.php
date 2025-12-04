<?php
/**
 * PesaPal Callback Handler
 * User is redirected here after completing payment on PesaPal
 * 
 * Documentation: https://developer.pesapal.com/how-to-integrate/e-commerce/api-30-json/api-reference
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentRequestStore;

// Log callback for debugging
$logFile = ROOT_PATH . '/storage/logs/pesapal-callbacks.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
];
file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

// PesaPal redirects with: OrderTrackingId, OrderMerchantReference
$trackingId = $_GET['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? null;
$merchantRef = $_GET['OrderMerchantReference'] ?? $_GET['orderMerchantReference'] ?? null;

if (!$trackingId && !$merchantRef) {
    $_SESSION['error_message'] = 'Invalid payment callback. Missing reference.';
    header('Location: ' . APP_URL . '/pos.php');
    exit;
}

try {
    $db = Database::getInstance();
    $store = new PaymentRequestStore();
    
    // Find the payment request
    $reference = $merchantRef ?: $trackingId;
    $paymentRequest = $store->findByReference($reference);
    
    if (!$paymentRequest) {
        // Try by provider reference
        $paymentRequest = $store->findByProviderReference($trackingId);
    }
    
    if (!$paymentRequest) {
        $_SESSION['error_message'] = 'Payment record not found.';
        header('Location: ' . APP_URL . '/pos.php');
        exit;
    }
    
    // Check payment status from PesaPal
    $consumerKey = settings('pesapal_consumer_key', '');
    $consumerSecret = settings('pesapal_consumer_secret', '');
    $environment = settings('pesapal_environment', 'sandbox');
    
    $baseUrl = $environment === 'live' 
        ? 'https://pay.pesapal.com/v3/api'
        : 'https://cybqa.pesapal.com/pesapalv3/api';
    
    // Get auth token
    $ch = curl_init($baseUrl . '/Auth/RequestToken');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $tokenResponse = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    $status = 'pending';
    $message = 'Payment is being processed.';
    
    if (!empty($tokenResponse['token'])) {
        // Get transaction status
        $ch = curl_init($baseUrl . '/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($trackingId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $tokenResponse['token'],
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $statusResponse = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        $statusCode = $statusResponse['payment_status_description'] ?? '';
        
        if (strtolower($statusCode) === 'completed') {
            $status = 'completed';
            $message = 'Payment completed successfully!';
            
            // Update payment request
            $store->updateStatus($reference, 'completed', [
                'provider_reference' => $trackingId,
                'amount_paid' => $statusResponse['amount'] ?? 0,
                'payment_method' => $statusResponse['payment_method'] ?? null,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            
            // Update related sale/order
            $contextType = $paymentRequest['context_type'] ?? null;
            $contextId = $paymentRequest['context_id'] ?? null;
            
            if ($contextType === 'sale' && $contextId) {
                $db->query(
                    "UPDATE sales SET payment_status = 'paid', payment_reference = ? WHERE id = ?",
                    [$trackingId, $contextId]
                );
            }
        } elseif (strtolower($statusCode) === 'failed') {
            $status = 'failed';
            $message = 'Payment failed. Please try again.';
            $store->updateStatus($reference, 'failed');
        }
    }
    
    // Determine redirect based on context
    $contextType = $paymentRequest['context_type'] ?? 'sale';
    $contextId = $paymentRequest['context_id'] ?? null;
    
    if ($status === 'completed') {
        $_SESSION['success_message'] = $message;
        
        if ($contextType === 'sale' && $contextId) {
            header('Location: ' . APP_URL . '/receipt.php?sale_id=' . $contextId);
        } else {
            header('Location: ' . APP_URL . '/pos.php?payment=success');
        }
    } else {
        $_SESSION['warning_message'] = $message;
        header('Location: ' . APP_URL . '/pos.php?payment=' . $status);
    }
    exit;
    
} catch (Throwable $e) {
    error_log("PesaPal callback error: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred processing your payment.';
    header('Location: ' . APP_URL . '/pos.php');
    exit;
}
