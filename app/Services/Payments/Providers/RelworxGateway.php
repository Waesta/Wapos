<?php
/**
 * Relworx Payment Gateway Integration
 * 
 * Supports:
 * - M-Pesa (Kenya) - STK Push, Paybill, Till, Pochi La Biashara
 * - Airtel Money (Kenya, Uganda, Rwanda, Tanzania)
 * - MTN Mobile Money (Uganda, Rwanda)
 * - Card Payments (Visa, Mastercard)
 * - Bank Transfers
 * 
 * Currencies: KES, UGX, RWF, TZS, USD
 * 
 * Documentation: https://docs.relworx.com
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

namespace App\Services\Payments\Providers;

use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentGatewayResponse;
use App\Services\Payments\PaymentGatewayWebhookResult;
use RuntimeException;

class RelworxGateway extends AbstractGateway implements PaymentGatewayInterface
{
    private const BASE_URL_LIVE = 'https://payments.relworx.com/api';
    private const BASE_URL_SANDBOX = 'https://sandbox.relworx.com/api';

    // Supported payment channels
    public const CHANNEL_MPESA_STK = 'mpesa_stk';           // M-Pesa STK Push
    public const CHANNEL_MPESA_PAYBILL = 'mpesa_paybill';   // M-Pesa Paybill (C2B)
    public const CHANNEL_MPESA_TILL = 'mpesa_till';         // M-Pesa Buy Goods (Till)
    public const CHANNEL_MPESA_POCHI = 'mpesa_pochi';       // Pochi La Biashara
    public const CHANNEL_AIRTEL_KE = 'airtel_ke';           // Airtel Kenya
    public const CHANNEL_AIRTEL_UG = 'airtel_ug';           // Airtel Uganda
    public const CHANNEL_AIRTEL_RW = 'airtel_rw';           // Airtel Rwanda
    public const CHANNEL_AIRTEL_TZ = 'airtel_tz';           // Airtel Tanzania
    public const CHANNEL_MTN_UG = 'mtn_ug';                 // MTN Uganda
    public const CHANNEL_MTN_RW = 'mtn_rw';                 // MTN Rwanda
    public const CHANNEL_CARD = 'card';                     // Card payments

    public function getName(): string
    {
        return 'relworx';
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
     * Initiate a mobile money payment (STK Push)
     */
    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $accountNumber = $this->config['account_number'] ?? $this->config['merchant_id'] ?? '';

        $reference = $this->resolveReference($request);
        $msisdn = $this->resolveMsisdn($request);
        
        $payload = [
            'account_no' => $accountNumber,
            'reference' => $reference,
            'msisdn' => $msisdn,
            'currency' => strtoupper($this->config['currency'] ?? $request->currency ?? 'KES'),
            'amount' => round($request->amount, 2),
            'description' => $request->metadata['description'] ?? 'Payment for Order ' . $reference,
            'callback_url' => $this->config['callback_url'] ?? null,
        ];

        // Remove null values
        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->postJson(
            $this->baseUrl() . '/mobile-money/request-payment',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Relworx payment request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['internal_reference'] ?? $response['transaction_id'] ?? null,
            'instructions' => 'A payment prompt has been sent to ' . $this->maskPhone($msisdn) . '. Please enter your PIN to complete.',
            'meta' => $response,
        ]);
    }

    /**
     * Initiate M-Pesa Paybill payment (C2B)
     * Customer pays to your Paybill number with account number
     */
    public function initiatePaybill(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $paybillNumber = $this->config['paybill_number'] ?? $this->config['shortcode'] ?? '';
        
        if (empty($paybillNumber)) {
            throw new RuntimeException('Paybill number is required for M-Pesa Paybill payments');
        }

        $reference = $this->resolveReference($request);
        $msisdn = $this->resolveMsisdn($request);
        
        $payload = [
            'paybill_number' => $paybillNumber,
            'account_number' => $request->metadata['account_number'] ?? $reference,
            'reference' => $reference,
            'msisdn' => $msisdn,
            'amount' => round($request->amount, 2),
            'currency' => 'KES',
            'description' => $request->metadata['description'] ?? 'Paybill Payment',
            'callback_url' => $this->config['callback_url'] ?? null,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/paybill/stk-push',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'M-Pesa Paybill request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['checkout_request_id'] ?? $response['internal_reference'] ?? null,
            'instructions' => 'M-Pesa prompt sent to ' . $this->maskPhone($msisdn) . '. Enter PIN to pay to Paybill ' . $paybillNumber,
            'meta' => $response,
        ]);
    }

    /**
     * Initiate M-Pesa Till/Buy Goods payment
     * Customer pays to your Till number
     */
    public function initiateTill(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $tillNumber = $this->config['till_number'] ?? $this->config['shortcode'] ?? '';
        
        if (empty($tillNumber)) {
            throw new RuntimeException('Till number is required for M-Pesa Buy Goods payments');
        }

        $reference = $this->resolveReference($request);
        $msisdn = $this->resolveMsisdn($request);
        
        $payload = [
            'till_number' => $tillNumber,
            'reference' => $reference,
            'msisdn' => $msisdn,
            'amount' => round($request->amount, 2),
            'currency' => 'KES',
            'description' => $request->metadata['description'] ?? 'Till Payment',
            'callback_url' => $this->config['callback_url'] ?? null,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/till/stk-push',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'M-Pesa Till request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['checkout_request_id'] ?? $response['internal_reference'] ?? null,
            'instructions' => 'M-Pesa prompt sent to ' . $this->maskPhone($msisdn) . '. Enter PIN to pay to Till ' . $tillNumber,
            'meta' => $response,
        ]);
    }

    /**
     * Initiate Pochi La Biashara payment
     * M-Pesa merchant wallet for small businesses
     */
    public function initiatePochi(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $pochiNumber = $this->config['pochi_number'] ?? $this->config['merchant_phone'] ?? '';
        
        if (empty($pochiNumber)) {
            throw new RuntimeException('Pochi La Biashara number is required');
        }

        $reference = $this->resolveReference($request);
        $msisdn = $this->resolveMsisdn($request);
        
        $payload = [
            'pochi_number' => $this->normalizePhone($pochiNumber, 'KE'),
            'reference' => $reference,
            'msisdn' => $msisdn,
            'amount' => round($request->amount, 2),
            'currency' => 'KES',
            'description' => $request->metadata['description'] ?? 'Pochi Payment',
            'callback_url' => $this->config['callback_url'] ?? null,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->postJson(
            $this->baseUrl() . '/mpesa/pochi/stk-push',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Pochi La Biashara request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['checkout_request_id'] ?? $response['internal_reference'] ?? null,
            'instructions' => 'M-Pesa prompt sent to ' . $this->maskPhone($msisdn) . '. Enter PIN to send to Pochi.',
            'meta' => $response,
        ]);
    }

    /**
     * Initiate Airtel Money payment
     * Supports Kenya, Uganda, Rwanda, Tanzania
     */
    public function initiateAirtel(PaymentGatewayRequest $request, string $country = 'KE'): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $accountNumber = $this->config['account_number'] ?? $this->config['merchant_id'] ?? '';

        $reference = $this->resolveReference($request);
        $msisdn = $this->resolveMsisdn($request);
        
        // Map country to currency
        $currencyMap = [
            'KE' => 'KES',
            'UG' => 'UGX',
            'RW' => 'RWF',
            'TZ' => 'TZS',
        ];
        
        $currency = $currencyMap[strtoupper($country)] ?? 'KES';
        
        $payload = [
            'account_no' => $accountNumber,
            'reference' => $reference,
            'msisdn' => $msisdn,
            'country' => strtoupper($country),
            'currency' => $currency,
            'amount' => round($request->amount, 2),
            'description' => $request->metadata['description'] ?? 'Airtel Money Payment',
            'callback_url' => $this->config['callback_url'] ?? null,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->postJson(
            $this->baseUrl() . '/airtel/request-payment',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Airtel Money request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['internal_reference'] ?? $response['transaction_id'] ?? null,
            'instructions' => 'Airtel Money prompt sent to ' . $this->maskPhone($msisdn) . '. Enter PIN to complete.',
            'meta' => $response,
        ]);
    }

    /**
     * Initiate MTN Mobile Money payment
     * Supports Uganda, Rwanda
     */
    public function initiateMtn(PaymentGatewayRequest $request, string $country = 'UG'): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $accountNumber = $this->config['account_number'] ?? $this->config['merchant_id'] ?? '';

        $reference = $this->resolveReference($request);
        $msisdn = $this->resolveMsisdn($request);
        
        // Map country to currency
        $currencyMap = [
            'UG' => 'UGX',
            'RW' => 'RWF',
        ];
        
        $currency = $currencyMap[strtoupper($country)] ?? 'UGX';
        
        $payload = [
            'account_no' => $accountNumber,
            'reference' => $reference,
            'msisdn' => $msisdn,
            'country' => strtoupper($country),
            'currency' => $currency,
            'amount' => round($request->amount, 2),
            'description' => $request->metadata['description'] ?? 'MTN MoMo Payment',
            'callback_url' => $this->config['callback_url'] ?? null,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->postJson(
            $this->baseUrl() . '/mtn/request-payment',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'MTN MoMo request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['internal_reference'] ?? $response['transaction_id'] ?? null,
            'instructions' => 'MTN MoMo prompt sent to ' . $this->maskPhone($msisdn) . '. Approve to complete.',
            'meta' => $response,
        ]);
    }

    /**
     * Initiate a card payment (redirect to hosted page)
     */
    public function initiateCardPayment(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $merchantId = $this->config['merchant_id'] ?? '';
        
        $reference = $this->resolveReference($request);
        
        $payload = [
            'merchant_id' => $merchantId,
            'reference' => $reference,
            'amount' => round($request->amount, 2),
            'currency' => strtoupper($this->config['currency'] ?? $request->currency ?? 'KES'),
            'description' => $request->metadata['description'] ?? 'Payment',
            'callback_url' => $this->config['callback_url'] ?? null,
            'redirect_url' => $this->config['redirect_url'] ?? $this->config['callback_url'] ?? null,
            'customer' => [
                'email' => $request->customerEmail,
                'phone' => $request->customerPhone,
                'name' => $request->customerName,
            ],
        ];

        $response = $this->postJson(
            $this->baseUrl() . '/card/initiate',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Relworx card payment failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => $response['transaction_id'] ?? null,
            'redirect_url' => $response['payment_url'] ?? $response['redirect_url'] ?? null,
            'instructions' => 'Redirect customer to complete card payment.',
            'meta' => $response,
        ]);
    }

    /**
     * Universal payment initiator - auto-detects channel based on config or request
     */
    public function initiateByChannel(PaymentGatewayRequest $request, string $channel = null): PaymentGatewayResponse
    {
        $channel = $channel ?? $request->metadata['channel'] ?? $this->config['default_channel'] ?? self::CHANNEL_MPESA_STK;
        
        return match($channel) {
            self::CHANNEL_MPESA_STK => $this->initiate($request),
            self::CHANNEL_MPESA_PAYBILL => $this->initiatePaybill($request),
            self::CHANNEL_MPESA_TILL => $this->initiateTill($request),
            self::CHANNEL_MPESA_POCHI => $this->initiatePochi($request),
            self::CHANNEL_AIRTEL_KE => $this->initiateAirtel($request, 'KE'),
            self::CHANNEL_AIRTEL_UG => $this->initiateAirtel($request, 'UG'),
            self::CHANNEL_AIRTEL_RW => $this->initiateAirtel($request, 'RW'),
            self::CHANNEL_AIRTEL_TZ => $this->initiateAirtel($request, 'TZ'),
            self::CHANNEL_MTN_UG => $this->initiateMtn($request, 'UG'),
            self::CHANNEL_MTN_RW => $this->initiateMtn($request, 'RW'),
            self::CHANNEL_CARD => $this->initiateCardPayment($request),
            default => $this->initiate($request),
        };
    }

    /**
     * Check payment status
     */
    public function checkStatus(string $reference): array
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        
        $response = $this->getJson(
            $this->baseUrl() . '/transactions/status?reference=' . urlencode($reference),
            $this->authHeaders($apiKey)
        );

        $status = strtolower($response['status'] ?? 'pending');
        $mappedStatus = match($status) {
            'success', 'successful', 'paid', 'completed' => 'completed',
            'failed', 'error', 'declined', 'cancelled' => 'failed',
            default => 'pending'
        };

        return [
            'status' => $mappedStatus,
            'reference' => $reference,
            'provider_reference' => $response['internal_reference'] ?? null,
            'amount' => $response['amount'] ?? 0,
            'message' => $response['message'] ?? null,
            'raw' => $response,
        ];
    }

    public function handleWebhook(array $payload, array $headers = []): PaymentGatewayWebhookResult
    {
        $status = strtolower($payload['status'] ?? 'pending');
        $mappedStatus = match ($status) {
            'success', 'paid', 'completed' => 'completed',
            'failed', 'error', 'declined' => 'failed',
            default => 'pending',
        };

        return new PaymentGatewayWebhookResult([
            'status' => $mappedStatus,
            'reference' => $payload['reference'] ?? '',
            'provider_reference' => $payload['internal_reference'] ?? null,
            'message' => $payload['message'] ?? null,
            'meta' => $payload,
        ]);
    }

    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/vnd.relworx.v2',
        ];
    }

    private function resolveReference(PaymentGatewayRequest $request): string
    {
        if (!empty($request->metadata['reference'])) {
            return (string) $request->metadata['reference'];
        }

        return 'POS-' . strtoupper(substr(md5(json_encode([
            $request->contextType,
            $request->contextId,
            microtime(true),
        ])), 0, 12));
    }

    private function resolveMsisdn(PaymentGatewayRequest $request): string
    {
        $msisdn = $request->customerPhone ?? ($request->metadata['msisdn'] ?? null);
        if (!$msisdn) {
            throw new RuntimeException('Customer phone number (msisdn) is required for Relworx payments.');
        }

        $country = $request->metadata['country'] ?? $this->config['default_country'] ?? 'KE';
        return $this->normalizePhone($msisdn, $country);
    }

    /**
     * Normalize phone number for East African countries
     * Supports: Kenya (254), Uganda (256), Rwanda (250), Tanzania (255)
     */
    private function normalizePhone(string $phone, string $country = 'KE'): string
    {
        // Strip all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Country code mapping
        $countryCodes = [
            'KE' => '254',  // Kenya
            'UG' => '256',  // Uganda
            'RW' => '250',  // Rwanda
            'TZ' => '255',  // Tanzania
        ];
        
        $countryCode = $countryCodes[strtoupper($country)] ?? '254';
        
        // Already has full international format
        if (strlen($phone) >= 12 && in_array(substr($phone, 0, 3), array_values($countryCodes))) {
            return '+' . $phone;
        }
        
        // Kenya specific patterns
        if ($countryCode === '254') {
            // 7XXXXXXXX or 1XXXXXXXX (9 digits)
            if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['7', '1'])) {
                return '+254' . $phone;
            }
            // 07XXXXXXXX or 01XXXXXXXX (10 digits with leading 0)
            if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                return '+254' . substr($phone, 1);
            }
        }
        
        // Uganda specific patterns
        if ($countryCode === '256') {
            // 7XXXXXXXX (9 digits)
            if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
                return '+256' . $phone;
            }
            // 07XXXXXXXX (10 digits)
            if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                return '+256' . substr($phone, 1);
            }
        }
        
        // Rwanda specific patterns
        if ($countryCode === '250') {
            // 7XXXXXXXX (9 digits)
            if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
                return '+250' . $phone;
            }
            // 07XXXXXXXX (10 digits)
            if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                return '+250' . substr($phone, 1);
            }
        }
        
        // Tanzania specific patterns
        if ($countryCode === '255') {
            // 6XXXXXXXX or 7XXXXXXXX (9 digits)
            if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['6', '7'])) {
                return '+255' . $phone;
            }
            // 06XXXXXXXX or 07XXXXXXXX (10 digits)
            if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                return '+255' . substr($phone, 1);
            }
        }
        
        // Fallback: assume it needs the country code
        if (strlen($phone) === 9) {
            return '+' . $countryCode . $phone;
        }
        
        // Already formatted or unknown format
        if (!str_starts_with($phone, '+')) {
            return '+' . $phone;
        }
        
        return $phone;
    }

    /**
     * Mask phone number for display
     */
    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 6) return $phone;
        return substr($phone, 0, 4) . '****' . substr($phone, -3);
    }

    /**
     * Get supported payment channels
     */
    public static function getSupportedChannels(): array
    {
        return [
            self::CHANNEL_MPESA_STK => 'M-Pesa STK Push (Kenya)',
            self::CHANNEL_MPESA_PAYBILL => 'M-Pesa Paybill (Kenya)',
            self::CHANNEL_MPESA_TILL => 'M-Pesa Till/Buy Goods (Kenya)',
            self::CHANNEL_MPESA_POCHI => 'Pochi La Biashara (Kenya)',
            self::CHANNEL_AIRTEL_KE => 'Airtel Money (Kenya)',
            self::CHANNEL_AIRTEL_UG => 'Airtel Money (Uganda)',
            self::CHANNEL_AIRTEL_RW => 'Airtel Money (Rwanda)',
            self::CHANNEL_AIRTEL_TZ => 'Airtel Money (Tanzania)',
            self::CHANNEL_MTN_UG => 'MTN Mobile Money (Uganda)',
            self::CHANNEL_MTN_RW => 'MTN Mobile Money (Rwanda)',
            self::CHANNEL_CARD => 'Card Payment (Visa/Mastercard)',
        ];
    }

    /**
     * Get supported currencies
     */
    public static function getSupportedCurrencies(): array
    {
        return [
            'KES' => 'Kenyan Shilling',
            'UGX' => 'Ugandan Shilling',
            'RWF' => 'Rwandan Franc',
            'TZS' => 'Tanzanian Shilling',
            'USD' => 'US Dollar',
        ];
    }
}
