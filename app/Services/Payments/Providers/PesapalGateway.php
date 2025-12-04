<?php
/**
 * PesaPal Payment Gateway Integration (API v3)
 * 
 * Supports:
 * - M-Pesa (Kenya, Tanzania)
 * - Airtel Money
 * - Visa / Mastercard / Amex
 * - Bank Transfers
 * - Equity Bank
 * 
 * Documentation: https://developer.pesapal.com/how-to-integrate/e-commerce/api-30-json/api-reference
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

namespace App\Services\Payments\Providers;

use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentGatewayResponse;
use App\Services\Payments\PaymentGatewayWebhookResult;
use RuntimeException;

class PesapalGateway extends AbstractGateway implements PaymentGatewayInterface
{
    private const BASE_URL_LIVE = 'https://pay.pesapal.com/v3/api';
    private const BASE_URL_SANDBOX = 'https://cybqa.pesapal.com/pesapalv3/api';
    
    private ?string $cachedToken = null;
    private ?int $tokenExpiry = null;

    public function getName(): string
    {
        return 'pesapal';
    }

    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $token = $this->issueToken();
        $callbackUrl = $this->requireConfig('callback_url', 'Pesapal Callback URL');

        $payload = [
            'id' => $this->resolveReference($request),
            'currency' => strtoupper($request->currency ?? 'KES'),
            'amount' => round($request->amount, 2),
            'description' => $request->metadata['description'] ?? 'Payment request',
            'callback_url' => $callbackUrl,
            'notification_id' => $this->config['ipn_id'] ?? null,
            'billing_address' => [
                'email_address' => $request->customerEmail,
                'phone_number' => $request->customerPhone,
                'country_code' => $request->metadata['country_code'] ?? 'KE',
                'first_name' => $request->customerName,
            ],
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/Transactions/SubmitOrderRequest',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        if (!(isset($response['status']) && strtolower((string)$response['status']) === '200')) {
            throw new RuntimeException($response['description'] ?? 'Pesapal order request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $payload['id'],
            'provider_reference' => $response['merchant_reference'] ?? null,
            'redirect_url' => $response['redirect_url'] ?? null,
            'instructions' => 'Complete payment on Pesapal hosted page.',
            'meta' => $response,
        ]);
    }

    public function handleWebhook(array $payload, array $headers = []): PaymentGatewayWebhookResult
    {
        $token = $this->issueToken();
        $trackingId = $payload['merchant_reference'] ?? $payload['tracking_id'] ?? null;
        if (!$trackingId) {
            throw new RuntimeException('Pesapal webhook missing tracking id');
        }

        $statusResponse = $this->postJson(
            $this->baseUrl() . '/Transactions/GetTransactionStatus',
            ['tracking_id' => $trackingId],
            ['Authorization: Bearer ' . $token]
        );

        $status = strtolower($statusResponse['status_code'] ?? 'pending');
        $mappedStatus = match ($status) {
            'completed', '200' => 'completed',
            'failed', '400', '500' => 'failed',
            default => 'pending',
        };

        return new PaymentGatewayWebhookResult([
            'status' => $mappedStatus,
            'reference' => $statusResponse['merchant_reference'] ?? $trackingId,
            'provider_reference' => $trackingId,
            'message' => $statusResponse['description'] ?? null,
            'meta' => $statusResponse,
        ]);
    }

    /**
     * Register IPN URL with PesaPal
     * This should be done once during setup
     */
    public function registerIpn(string $ipnUrl, string $ipnNotificationType = 'GET'): array
    {
        $token = $this->issueToken();
        
        $payload = [
            'url' => $ipnUrl,
            'ipn_notification_type' => $ipnNotificationType, // GET or POST
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/URLSetup/RegisterIPN',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        return [
            'success' => !empty($response['ipn_id']),
            'ipn_id' => $response['ipn_id'] ?? null,
            'url' => $response['url'] ?? $ipnUrl,
            'raw' => $response,
        ];
    }

    /**
     * Get list of registered IPN URLs
     */
    public function getRegisteredIpns(): array
    {
        $token = $this->issueToken();
        
        return $this->getJson(
            $this->baseUrl() . '/URLSetup/GetIpnList',
            ['Authorization: Bearer ' . $token]
        );
    }

    /**
     * Check transaction status
     */
    public function checkStatus(string $trackingId): array
    {
        $token = $this->issueToken();
        
        $response = $this->getJson(
            $this->baseUrl() . '/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($trackingId),
            ['Authorization: Bearer ' . $token]
        );

        $statusCode = strtolower($response['payment_status_description'] ?? 'pending');
        $mappedStatus = match($statusCode) {
            'completed' => 'completed',
            'failed' => 'failed',
            'reversed' => 'refunded',
            default => 'pending'
        };

        return [
            'status' => $mappedStatus,
            'tracking_id' => $trackingId,
            'merchant_reference' => $response['merchant_reference'] ?? null,
            'amount' => $response['amount'] ?? 0,
            'currency' => $response['currency'] ?? 'KES',
            'payment_method' => $response['payment_method'] ?? null,
            'description' => $response['description'] ?? null,
            'raw' => $response,
        ];
    }

    /**
     * Get auth token with caching
     */
    private function issueToken(): string
    {
        // Return cached token if still valid
        if ($this->cachedToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->cachedToken;
        }
        
        $consumerKey = $this->requireConfig('consumer_key', 'Pesapal Consumer Key');
        $consumerSecret = $this->requireConfig('consumer_secret', 'Pesapal Consumer Secret');

        $payload = [
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/Auth/RequestToken',
            $payload
        );

        if (empty($response['token'])) {
            throw new RuntimeException('Unable to obtain Pesapal auth token: ' . json_encode($response));
        }

        // Cache token (expires in 5 minutes by default, we cache for 4)
        $this->cachedToken = (string)$response['token'];
        $this->tokenExpiry = time() + 240; // 4 minutes

        return $this->cachedToken;
    }

    private function baseUrl(): string
    {
        $env = strtolower($this->config['environment'] ?? 'sandbox');
        return $env === 'sandbox' ? self::BASE_URL_SANDBOX : self::BASE_URL_LIVE;
    }

    private function resolveReference(PaymentGatewayRequest $request): string
    {
        if (!empty($request->metadata['reference'])) {
            return (string)$request->metadata['reference'];
        }

        return 'PES-' . strtoupper(substr(md5($request->contextType . '-' . $request->contextId . microtime(true)), 0, 12));
    }

    /**
     * Normalize phone number for East Africa
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Kenya
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['7', '1'])) {
            return '+254' . $phone;
        }
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '+254' . substr($phone, 1);
        }
        if (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return '+' . $phone;
        }
        
        // Tanzania
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['6', '7'])) {
            return '+255' . $phone;
        }
        
        // Uganda
        if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
            return '+256' . $phone;
        }
        
        return $phone;
    }
}
