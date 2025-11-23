<?php

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    public function getName(): string;

    /**
     * Initiates a payment request with the remote gateway.
     */
    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse;

    /**
     * Handles webhook/callback payloads coming from the gateway provider.
     */
    public function handleWebhook(array $payload, array $headers = []): PaymentGatewayWebhookResult;
}
