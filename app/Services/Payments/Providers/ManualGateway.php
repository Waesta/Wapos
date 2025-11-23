<?php

namespace App\Services\Payments\Providers;

use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentGatewayResponse;
use App\Services\Payments\PaymentGatewayWebhookResult;

class ManualGateway extends AbstractGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'manual';
    }

    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        $reference = $request->metadata['internal_reference'] ?? ('manual-' . uniqid());

        return new PaymentGatewayResponse([
            'status' => 'pending',
            'reference' => $reference,
            'provider_reference' => null,
            'instructions' => 'Record the mobile money transaction ID and confirm once received.',
            'meta' => [
                'amount' => $request->amount,
                'currency' => $request->currency,
                'customer_phone' => $request->customerPhone,
                'note' => 'Manual mobile money entry',
            ],
        ]);
    }

    public function handleWebhook(array $payload, array $headers = []): PaymentGatewayWebhookResult
    {
        return new PaymentGatewayWebhookResult([
            'status' => $payload['status'] ?? 'pending',
            'reference' => $payload['reference'] ?? '',
            'provider_reference' => $payload['provider_reference'] ?? null,
            'message' => $payload['message'] ?? 'Manual update',
            'meta' => $payload,
        ]);
    }
}
