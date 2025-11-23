<?php

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

    private function issueToken(): string
    {
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
            throw new RuntimeException('Unable to obtain Pesapal auth token');
        }

        return (string)$response['token'];
    }

    private function baseUrl(): string
    {
        $env = strtolower($this->config['environment'] ?? 'live');
        return $env === 'sandbox' ? self::BASE_URL_SANDBOX : self::BASE_URL_LIVE;
    }

    private function resolveReference(PaymentGatewayRequest $request): string
    {
        if (!empty($request->metadata['reference'])) {
            return (string)$request->metadata['reference'];
        }

        return 'PES-' . strtoupper(substr(md5($request->contextType . '-' . $request->contextId . microtime(true)), 0, 12));
    }
}
