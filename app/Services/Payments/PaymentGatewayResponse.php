<?php

namespace App\Services\Payments;

class PaymentGatewayResponse
{
    public string $status; // pending|required_action|completed
    public string $reference; // internal reference
    public ?string $providerReference;
    public ?string $redirectUrl;
    public ?string $instructions;
    public array $meta;

    public function __construct(array $data)
    {
        $this->status = $data['status'] ?? 'pending';
        $this->reference = $data['reference'] ?? '';
        $this->providerReference = $data['provider_reference'] ?? null;
        $this->redirectUrl = $data['redirect_url'] ?? null;
        $this->instructions = $data['instructions'] ?? null;
        $this->meta = $data['meta'] ?? [];
    }
}
