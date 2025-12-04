<?php
/**
 * M-Pesa Daraja API Callback Handler
 * Receives payment notifications directly from Safaricom
 * 
 * Handles:
 * - STK Push callbacks
 * - C2B validation and confirmation
 * - B2C result callbacks
 * 
 * Documentation: https://developer.safaricom.co.ke/APIs
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentRequestStore;

// Log all incoming requests for debugging
$logFile = ROOT_PATH . '/storage/logs/mpesa-callbacks.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'raw' => file_get_contents('php://input'),
];
file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

header('Content-Type: application/json');

function respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

// M-Pesa sends POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ResultCode' => 1, 'ResultDesc' => 'Method not allowed']);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    respond(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
}

try {
    $db = Database::getInstance();
    $store = new PaymentRequestStore();
    
    // Determine callback type and process accordingly
    
    // 1. STK Push Callback
    if (isset($payload['Body']['stkCallback'])) {
        $callback = $payload['Body']['stkCallback'];
        $resultCode = $callback['ResultCode'] ?? null;
        $checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
        $merchantRequestId = $callback['MerchantRequestID'] ?? '';
        
        $status = ($resultCode === 0 || $resultCode === '0') ? 'completed' : 'failed';
        
        // Extract metadata
        $metadata = [];
        if (isset($callback['CallbackMetadata']['Item'])) {
            foreach ($callback['CallbackMetadata']['Item'] as $item) {
                $metadata[$item['Name']] = $item['Value'] ?? null;
            }
        }
        
        // Update payment request
        $updated = $store->updateStatus($checkoutRequestId, $status, [
            'provider_reference' => $metadata['MpesaReceiptNumber'] ?? null,
            'amount_paid' => $metadata['Amount'] ?? 0,
            'message' => $callback['ResultDesc'] ?? null,
            'raw_response' => $callback,
            'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
        ]);
        
        // If not found by checkout ID, try merchant request ID
        if (!$updated) {
            $store->updateStatus($merchantRequestId, $status, [
                'provider_reference' => $metadata['MpesaReceiptNumber'] ?? null,
                'amount_paid' => $metadata['Amount'] ?? 0,
                'message' => $callback['ResultDesc'] ?? null,
                'raw_response' => $callback,
            ]);
        }
        
        // Update related sale if payment completed
        if ($status === 'completed') {
            $paymentRequest = $store->findByReference($checkoutRequestId) 
                           ?? $store->findByProviderReference($metadata['MpesaReceiptNumber'] ?? '');
            
            if ($paymentRequest) {
                $contextType = $paymentRequest['context_type'] ?? null;
                $contextId = $paymentRequest['context_id'] ?? null;
                
                if ($contextType === 'sale' && $contextId) {
                    $db->query(
                        "UPDATE sales SET payment_status = 'paid', payment_reference = ?, payment_method = 'mpesa', updated_at = NOW() WHERE id = ?",
                        [$metadata['MpesaReceiptNumber'] ?? $checkoutRequestId, $contextId]
                    );
                }
            }
        }
        
        // Log
        if (class_exists('SystemLogger')) {
            SystemLogger::getInstance()->info(
                "M-Pesa STK callback: {$checkoutRequestId} - {$status}",
                'payment',
                ['receipt' => $metadata['MpesaReceiptNumber'] ?? null, 'amount' => $metadata['Amount'] ?? 0]
            );
        }
        
        // M-Pesa expects this exact response format
        respond(['ResultCode' => 0, 'ResultDesc' => 'Callback received successfully']);
    }
    
    // 2. C2B Validation (optional - for validating before accepting payment)
    if (isset($payload['TransactionType']) && isset($payload['TransID']) && !isset($payload['Body'])) {
        // This is a C2B confirmation or validation
        $transId = $payload['TransID'] ?? '';
        $transType = $payload['TransactionType'] ?? '';
        $amount = $payload['TransAmount'] ?? 0;
        $phone = $payload['MSISDN'] ?? '';
        $billRefNumber = $payload['BillRefNumber'] ?? '';
        
        // For validation endpoint - you can add custom validation logic here
        // Return ResultCode 0 to accept, non-zero to reject
        
        // For confirmation - record the payment
        $reference = $billRefNumber ?: $transId;
        
        $store->create([
            'reference' => $reference,
            'provider' => 'mpesa',
            'status' => 'completed',
            'context_type' => 'c2b',
            'context_id' => 0,
            'amount' => $amount,
            'currency' => 'KES',
            'customer_phone' => $phone,
            'provider_reference' => $transId,
            'meta' => $payload,
        ]);
        
        // Try to match with existing sale by bill reference
        if ($billRefNumber) {
            $db->query(
                "UPDATE sales SET payment_status = 'paid', payment_reference = ?, payment_method = 'mpesa' 
                 WHERE invoice_number = ? OR id = ?",
                [$transId, $billRefNumber, (int)$billRefNumber]
            );
        }
        
        if (class_exists('SystemLogger')) {
            SystemLogger::getInstance()->info(
                "M-Pesa C2B received: {$transId} - KES {$amount}",
                'payment',
                ['phone' => $phone, 'ref' => $billRefNumber]
            );
        }
        
        respond(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
    
    // 3. B2C Result Callback
    if (isset($payload['Result'])) {
        $result = $payload['Result'];
        $resultCode = $result['ResultCode'] ?? null;
        $conversationId = $result['ConversationID'] ?? '';
        $originatorConversationId = $result['OriginatorConversationID'] ?? '';
        
        $status = ($resultCode === 0 || $resultCode === '0') ? 'completed' : 'failed';
        
        $params = [];
        if (isset($result['ResultParameters']['ResultParameter'])) {
            foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                $params[$param['Key']] = $param['Value'] ?? null;
            }
        }
        
        $store->updateStatus($originatorConversationId, $status, [
            'provider_reference' => $params['TransactionReceipt'] ?? $conversationId,
            'amount_paid' => $params['TransactionAmount'] ?? 0,
            'message' => $result['ResultDesc'] ?? null,
            'raw_response' => $result,
        ]);
        
        if (class_exists('SystemLogger')) {
            SystemLogger::getInstance()->info(
                "M-Pesa B2C result: {$originatorConversationId} - {$status}",
                'payment',
                $params
            );
        }
        
        respond(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
    
    // Unknown callback format
    respond(['ResultCode' => 0, 'ResultDesc' => 'Received']);
    
} catch (Throwable $e) {
    error_log("M-Pesa callback error: " . $e->getMessage());
    
    // Always return success to M-Pesa to prevent retries
    respond(['ResultCode' => 0, 'ResultDesc' => 'Processed']);
}
