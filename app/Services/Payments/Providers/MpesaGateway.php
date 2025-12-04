<?php
/**
 * M-Pesa Daraja API Direct Integration
 * 
 * Direct integration with Safaricom's Daraja API for:
 * - STK Push (Lipa Na M-Pesa Online)
 * - C2B (Paybill & Till/Buy Goods)
 * - B2C (Business to Customer)
 * - Pochi La Biashara
 * 
 * Documentation: https://developer.safaricom.co.ke/APIs
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

namespace App\Services\Payments\Providers;

use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentGatewayResponse;
use App\Services\Payments\PaymentGatewayWebhookResult;
use RuntimeException;

class MpesaGateway extends AbstractGateway implements PaymentGatewayInterface
{
    private const BASE_URL_LIVE = 'https://api.safaricom.co.ke';
    private const BASE_URL_SANDBOX = 'https://sandbox.safaricom.co.ke';

    // Transaction types
    public const TYPE_PAYBILL = 'CustomerPayBillOnline';
    public const TYPE_TILL = 'CustomerBuyGoodsOnline';
    
    // Command IDs
    public const COMMAND_SALARY = 'SalaryPayment';
    public const COMMAND_BUSINESS = 'BusinessPayment';
    public const COMMAND_PROMOTION = 'PromotionPayment';

    private ?string $cachedToken = null;
    private ?int $tokenExpiry = null;

    public function getName(): string
    {
        return 'mpesa';
    }

    /**
     * Get the base URL based on environment
     */
    private function baseUrl(): string
    {
        $env = strtolower($this->config['environment'] ?? 'sandbox');
        return $env === 'live' ? self::BASE_URL_LIVE : self::BASE_URL_SANDBOX;
    }

    /**
     * Get OAuth access token from Daraja API
     */
    private function getAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->cachedToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->cachedToken;
        }

        $consumerKey = $this->requireConfig('consumer_key', 'M-Pesa Consumer Key');
        $consumerSecret = $this->requireConfig('consumer_secret', 'M-Pesa Consumer Secret');

        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        $ch = curl_init($this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException('Failed to get M-Pesa access token: ' . $response);
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('Invalid M-Pesa token response');
        }

        // Cache token (typically valid for 1 hour, we cache for 50 minutes)
        $this->cachedToken = $data['access_token'];
        $this->tokenExpiry = time() + 3000;

        return $this->cachedToken;
    }

    /**
     * Generate password for STK Push
     */
    private function generatePassword(string $shortcode, string $passkey, string $timestamp): string
    {
        return base64_encode($shortcode . $passkey . $timestamp);
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     * Works for both Paybill and Till
     */
    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        return $this->stkPush($request);
    }

    /**
     * STK Push - Lipa Na M-Pesa Online
     */
    public function stkPush(PaymentGatewayRequest $request, string $transactionType = null): PaymentGatewayResponse
    {
        $token = $this->getAccessToken();
        
        $shortcode = $this->config['shortcode'] ?? $this->config['paybill_number'] ?? $this->config['till_number'] ?? '';
        $passkey = $this->requireConfig('passkey', 'M-Pesa Passkey');
        $callbackUrl = $this->requireConfig('callback_url', 'M-Pesa Callback URL');
        
        // Determine transaction type
        if (!$transactionType) {
            $transactionType = !empty($this->config['till_number']) ? self::TYPE_TILL : self::TYPE_PAYBILL;
        }
        
        $timestamp = date('YmdHis');
        $password = $this->generatePassword($shortcode, $passkey, $timestamp);
        $reference = $this->resolveReference($request);
        $phone = $this->normalizePhone($request->customerPhone);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $transactionType,
            'Amount' => (int) round($request->amount),
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $request->metadata['account_reference'] ?? $reference,
            'TransactionDesc' => $request->metadata['description'] ?? 'Payment',
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/stkpush/v1/processrequest',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        if (($response['ResponseCode'] ?? '') !== '0') {
            throw new RuntimeException($response['ResponseDescription'] ?? $response['errorMessage'] ?? 'STK Push failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['CheckoutRequestID'] ?? null,
            'instructions' => 'M-Pesa prompt sent to ' . $this->maskPhone($phone) . '. Enter your PIN to complete payment.',
            'meta' => [
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                'response_code' => $response['ResponseCode'] ?? null,
            ],
        ]);
    }

    /**
     * STK Push for Paybill
     */
    public function stkPushPaybill(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        return $this->stkPush($request, self::TYPE_PAYBILL);
    }

    /**
     * STK Push for Till/Buy Goods
     */
    public function stkPushTill(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        return $this->stkPush($request, self::TYPE_TILL);
    }

    /**
     * Query STK Push transaction status
     */
    public function querySTKStatus(string $checkoutRequestId): array
    {
        $token = $this->getAccessToken();
        
        $shortcode = $this->config['shortcode'] ?? $this->config['paybill_number'] ?? $this->config['till_number'] ?? '';
        $passkey = $this->requireConfig('passkey', 'M-Pesa Passkey');
        
        $timestamp = date('YmdHis');
        $password = $this->generatePassword($shortcode, $passkey, $timestamp);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/stkpushquery/v1/query',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        $resultCode = $response['ResultCode'] ?? null;
        $status = match($resultCode) {
            '0', 0 => 'completed',
            '1032', 1032 => 'cancelled', // User cancelled
            '1037', 1037 => 'timeout',   // Timeout
            '1', 1 => 'failed',
            default => 'pending'
        };

        return [
            'status' => $status,
            'result_code' => $resultCode,
            'result_desc' => $response['ResultDesc'] ?? null,
            'checkout_request_id' => $checkoutRequestId,
            'raw' => $response,
        ];
    }

    /**
     * Register C2B URLs (Paybill/Till confirmation & validation)
     */
    public function registerC2BUrls(string $confirmationUrl, string $validationUrl = null): array
    {
        $token = $this->getAccessToken();
        
        $shortcode = $this->config['shortcode'] ?? $this->config['paybill_number'] ?? $this->config['till_number'] ?? '';
        
        $payload = [
            'ShortCode' => $shortcode,
            'ResponseType' => 'Completed', // or 'Cancelled'
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL' => $validationUrl ?? $confirmationUrl,
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/c2b/v1/registerurl',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        return [
            'success' => ($response['ResponseCode'] ?? '') === '0',
            'message' => $response['ResponseDescription'] ?? 'Unknown',
            'raw' => $response,
        ];
    }

    /**
     * B2C Payment (Business to Customer)
     * For sending money to customers (refunds, salaries, etc.)
     */
    public function b2cPayment(
        string $phone,
        float $amount,
        string $commandId = self::COMMAND_BUSINESS,
        string $remarks = 'Payment'
    ): array {
        $token = $this->getAccessToken();
        
        $shortcode = $this->requireConfig('b2c_shortcode', 'B2C Shortcode');
        $initiatorName = $this->requireConfig('initiator_name', 'Initiator Name');
        $securityCredential = $this->requireConfig('security_credential', 'Security Credential');
        $queueTimeoutUrl = $this->requireConfig('queue_timeout_url', 'Queue Timeout URL');
        $resultUrl = $this->requireConfig('result_url', 'Result URL');

        $payload = [
            'InitiatorName' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => $commandId,
            'Amount' => (int) round($amount),
            'PartyA' => $shortcode,
            'PartyB' => $this->normalizePhone($phone),
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $queueTimeoutUrl,
            'ResultURL' => $resultUrl,
            'Occassion' => $remarks,
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/b2c/v1/paymentrequest',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        return [
            'success' => ($response['ResponseCode'] ?? '') === '0',
            'conversation_id' => $response['ConversationID'] ?? null,
            'originator_conversation_id' => $response['OriginatorConversationID'] ?? null,
            'message' => $response['ResponseDescription'] ?? 'Unknown',
            'raw' => $response,
        ];
    }

    /**
     * Handle M-Pesa callback/webhook
     */
    public function handleWebhook(array $payload, array $headers = []): PaymentGatewayWebhookResult
    {
        // STK Push callback
        if (isset($payload['Body']['stkCallback'])) {
            return $this->handleSTKCallback($payload['Body']['stkCallback']);
        }
        
        // C2B callback
        if (isset($payload['TransID']) || isset($payload['TransactionType'])) {
            return $this->handleC2BCallback($payload);
        }
        
        // B2C callback
        if (isset($payload['Result'])) {
            return $this->handleB2CCallback($payload['Result']);
        }

        throw new RuntimeException('Unknown M-Pesa callback format');
    }

    /**
     * Handle STK Push callback
     */
    private function handleSTKCallback(array $callback): PaymentGatewayWebhookResult
    {
        $resultCode = $callback['ResultCode'] ?? null;
        $status = ($resultCode === 0 || $resultCode === '0') ? 'completed' : 'failed';
        
        $metadata = [];
        if (isset($callback['CallbackMetadata']['Item'])) {
            foreach ($callback['CallbackMetadata']['Item'] as $item) {
                $metadata[$item['Name']] = $item['Value'] ?? null;
            }
        }

        return new PaymentGatewayWebhookResult([
            'status' => $status,
            'reference' => $callback['CheckoutRequestID'] ?? '',
            'provider_reference' => $metadata['MpesaReceiptNumber'] ?? null,
            'message' => $callback['ResultDesc'] ?? null,
            'meta' => [
                'merchant_request_id' => $callback['MerchantRequestID'] ?? null,
                'checkout_request_id' => $callback['CheckoutRequestID'] ?? null,
                'amount' => $metadata['Amount'] ?? null,
                'phone' => $metadata['PhoneNumber'] ?? null,
                'receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
                'transaction_date' => $metadata['TransactionDate'] ?? null,
            ],
        ]);
    }

    /**
     * Handle C2B callback (Paybill/Till payments)
     */
    private function handleC2BCallback(array $payload): PaymentGatewayWebhookResult
    {
        return new PaymentGatewayWebhookResult([
            'status' => 'completed',
            'reference' => $payload['BillRefNumber'] ?? $payload['TransID'] ?? '',
            'provider_reference' => $payload['TransID'] ?? null,
            'message' => 'Payment received',
            'meta' => [
                'transaction_id' => $payload['TransID'] ?? null,
                'transaction_type' => $payload['TransactionType'] ?? null,
                'amount' => $payload['TransAmount'] ?? null,
                'phone' => $payload['MSISDN'] ?? null,
                'bill_ref_number' => $payload['BillRefNumber'] ?? null,
                'org_account_balance' => $payload['OrgAccountBalance'] ?? null,
                'transaction_time' => $payload['TransTime'] ?? null,
                'first_name' => $payload['FirstName'] ?? null,
                'middle_name' => $payload['MiddleName'] ?? null,
                'last_name' => $payload['LastName'] ?? null,
            ],
        ]);
    }

    /**
     * Handle B2C callback
     */
    private function handleB2CCallback(array $result): PaymentGatewayWebhookResult
    {
        $resultCode = $result['ResultCode'] ?? null;
        $status = ($resultCode === 0 || $resultCode === '0') ? 'completed' : 'failed';

        $params = [];
        if (isset($result['ResultParameters']['ResultParameter'])) {
            foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                $params[$param['Key']] = $param['Value'] ?? null;
            }
        }

        return new PaymentGatewayWebhookResult([
            'status' => $status,
            'reference' => $result['OriginatorConversationID'] ?? '',
            'provider_reference' => $params['TransactionReceipt'] ?? $result['ConversationID'] ?? null,
            'message' => $result['ResultDesc'] ?? null,
            'meta' => [
                'conversation_id' => $result['ConversationID'] ?? null,
                'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
                'transaction_receipt' => $params['TransactionReceipt'] ?? null,
                'amount' => $params['TransactionAmount'] ?? null,
                'receiver_phone' => $params['ReceiverPartyPublicName'] ?? null,
            ],
        ]);
    }

    /**
     * Check payment status
     */
    public function checkStatus(string $reference): array
    {
        return $this->querySTKStatus($reference);
    }

    /**
     * Normalize Kenyan phone number to 2547XXXXXXXX format
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Already in correct format
        if (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return $phone;
        }
        
        // 07XXXXXXXX or 01XXXXXXXX
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '254' . substr($phone, 1);
        }
        
        // 7XXXXXXXX or 1XXXXXXXX
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['7', '1'])) {
            return '254' . $phone;
        }
        
        // +2547XXXXXXXX
        if (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
            return substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Mask phone number for display
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 6) return $phone;
        return substr($phone, 0, 6) . '****' . substr($phone, -2);
    }

    private function resolveReference(PaymentGatewayRequest $request): string
    {
        if (!empty($request->metadata['reference'])) {
            return (string) $request->metadata['reference'];
        }

        return 'INV' . strtoupper(substr(md5(microtime(true) . $request->amount), 0, 8));
    }
}
