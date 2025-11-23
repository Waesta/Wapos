<?php

namespace App\Services\Payments\Providers;

use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentGatewayResponse;
use App\Services\Payments\PaymentGatewayWebhookResult;
use RuntimeException;

class RelworxGateway extends AbstractGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://payments.relworx.com/api';

    public function getName(): string
    {
        return 'relworx';
    }

    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $apiKey = $this->requireConfig('api_key', 'Relworx API Key');
        $accountNumber = $this->requireConfig('account_number', 'Relworx Account');

        $payload = [
            'account_no' => $accountNumber,
            'reference' => $this->resolveReference($request),
            'msisdn' => $this->resolveMsisdn($request),
            'currency' => strtoupper($this->config['currency'] ?? $request->currency ?? 'KES'),
            'amount' => round($request->amount, 2),
            'description' => $this->config['description'] ?? 'Payment Request',
        ];

        $response = $this->postJson(
            self::BASE_URL . '/mobile-money/request-payment',
            $payload,
            $this->authHeaders($apiKey)
        );

        if (!($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Relworx request failed');
        }

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $payload['reference'],
            'provider_reference' => $response['internal_reference'] ?? null,
            'instructions' => 'Prompt sent to customer phone. Await confirmation.',
            'meta' => $response,
        ]);
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

        if (!str_starts_with($msisdn, '+')) {
            $msisdn = '+' . preg_replace('/[^0-9]/', '', $msisdn);
        }

        return $msisdn;
    }
}
