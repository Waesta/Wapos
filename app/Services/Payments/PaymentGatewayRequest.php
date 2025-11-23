<?php

namespace App\Services\Payments;

class PaymentGatewayRequest
{
    public string $contextType;
    public int $contextId;
    public float $amount;
    public string $currency;
    public ?string $customerPhone;
    public ?string $customerEmail;
    public ?string $customerName;
    public array $metadata;

    public function __construct(array $payload)
    {
        $this->contextType = $payload['context_type'] ?? 'pos_sale';
        $this->contextId = (int)($payload['context_id'] ?? 0);
        $this->amount = (float)($payload['amount'] ?? 0);
        $this->currency = $payload['currency'] ?? (settings('currency_code') ?? 'KES');
        $this->customerPhone = $payload['customer_phone'] ?? null;
        $this->customerEmail = $payload['customer_email'] ?? null;
        $this->customerName = $payload['customer_name'] ?? null;
        $this->metadata = $payload['metadata'] ?? [];
    }
}
