<?php

namespace App\Services\Payments;

use App\Services\Payments\Providers\ManualGateway;
use App\Services\Payments\Providers\PesapalGateway;
use App\Services\Payments\Providers\RelworxGateway;

class PaymentGatewayManager
{
    private ?PaymentGatewayInterface $activeGateway = null;
    private ?string $activeProvider = null;
    private array $resolvedGateways = [];
    private array $options;

    public function __construct(?array $options = null)
    {
        $this->options = $options ?? [];
    }

    public function initiate(PaymentGatewayRequest $request): PaymentGatewayResponse
    {
        return $this->getActiveGateway()->initiate($request);
    }

    public function handleWebhook(?string $provider, array $payload, array $headers = []): PaymentGatewayWebhookResult
    {
        $gateway = $this->getGateway($provider ?: $this->getActiveProviderKey());
        return $gateway->handleWebhook($payload, $headers);
    }

    public function getActiveProviderKey(): string
    {
        if ($this->activeProvider !== null) {
            return $this->activeProvider;
        }

        if (!empty($this->options['provider'])) {
            $this->activeProvider = strtolower($this->options['provider']);
            return $this->activeProvider;
        }

        $provider = 'manual';
        if (function_exists('settings')) {
            $configured = settings('payments_gateway_provider');
            if (!empty($configured)) {
                $provider = strtolower($configured);
            }
        }

        $this->activeProvider = $provider;
        return $provider;
    }

    private function getActiveGateway(): PaymentGatewayInterface
    {
        if ($this->activeGateway !== null) {
            return $this->activeGateway;
        }

        $this->activeGateway = $this->getGateway($this->getActiveProviderKey());
        return $this->activeGateway;
    }

    private function getGateway(string $provider): PaymentGatewayInterface
    {
        $provider = strtolower($provider);

        if (isset($this->resolvedGateways[$provider])) {
            return $this->resolvedGateways[$provider];
        }

        $config = $this->resolveConfigFor($provider);

        switch ($provider) {
            case 'relworx':
                $gateway = new RelworxGateway($config);
                break;
            case 'pesapal':
                $gateway = new PesapalGateway($config);
                break;
            case 'manual':
            default:
                $gateway = new ManualGateway($config);
                break;
        }

        $this->resolvedGateways[$provider] = $gateway;
        return $gateway;
    }

    private function resolveConfigFor(string $provider): array
    {
        $provider = strtolower($provider);
        $config = $this->options['config'][$provider] ?? [];

        if (!empty($config)) {
            return $config;
        }

        $fetch = function (array $keys): array {
            if (function_exists('settings_many')) {
                return settings_many($keys);
            }

            $results = [];
            foreach ($keys as $key) {
                $results[$key] = function_exists('settings') ? settings($key) : null;
            }
            return $results;
        };

        switch ($provider) {
            case 'relworx':
                $values = $fetch([
                    'relworx_api_key',
                    'relworx_account_number',
                    'relworx_default_currency',
                    'payments_gateway_description',
                    'currency_code',
                ]);
                return [
                    'api_key' => $values['relworx_api_key'] ?? null,
                    'account_number' => $values['relworx_account_number'] ?? null,
                    'currency' => $values['relworx_default_currency'] ?? ($values['currency_code'] ?? 'KES'),
                    'description' => $values['payments_gateway_description'] ?? 'Mobile money request via WAPOS',
                ];

            case 'pesapal':
                $values = $fetch([
                    'pesapal_consumer_key',
                    'pesapal_consumer_secret',
                    'pesapal_ipn_id',
                    'pesapal_callback_url',
                    'pesapal_environment',
                ]);
                return [
                    'consumer_key' => $values['pesapal_consumer_key'] ?? null,
                    'consumer_secret' => $values['pesapal_consumer_secret'] ?? null,
                    'ipn_id' => $values['pesapal_ipn_id'] ?? null,
                    'callback_url' => $values['pesapal_callback_url'] ?? null,
                    'environment' => strtolower($values['pesapal_environment'] ?? 'live'),
                ];

            case 'manual':
            default:
                return [];
        }
    }
}
