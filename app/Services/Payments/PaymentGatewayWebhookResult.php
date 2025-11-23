<?php

namespace App\Services\Payments;

class PaymentGatewayWebhookResult
{
    public string $status; // pending|completed|failed
    public string $reference; // internal reference/id
    public ?string $providerReference;
    public ?string $message;
    public array $meta;

    public function __construct(array $data)
    {
        $this->status = $data['status'] ?? 'pending';
        $this->reference = $data['reference'] ?? '';
        $this->providerReference = $data['provider_reference'] ?? null;
        $this->message = $data['message'] ?? null;
        $this->meta = $data['meta'] ?? [];
    }
}
