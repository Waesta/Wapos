<?php

namespace App\Services\Payments\Providers;

use RuntimeException;

abstract class AbstractGateway
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    protected function requireConfig(string $key, string $label = null): string
    {
        if (empty($this->config[$key])) {
            $label = $label ?? $key;
            throw new RuntimeException("Missing gateway configuration: {$label}");
        }

        return (string) $this->config[$key];
    }

    protected function postJson(string $url, array $payload, array $headers = []): array
    {
        return $this->sendHttpRequest('POST', $url, $payload, $headers);
    }

    protected function getJson(string $url, array $headers = []): array
    {
        return $this->sendHttpRequest('GET', $url, null, $headers);
    }

    private function sendHttpRequest(string $method, string $url, ?array $payload = null, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        $defaultHeaders = ['Accept: application/json'];
        if ($payload !== null) {
            $defaultHeaders[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON response: ' . $response);
        }

        if ($status >= 400) {
            $message = is_array($decoded) && isset($decoded['message'])
                ? $decoded['message']
                : $response;
            throw new RuntimeException("Gateway HTTP {$status}: {$message}");
        }

        return is_array($decoded) ? $decoded : [];
    }
}
