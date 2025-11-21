<?php

namespace App\Services;

use App\Services\Distance\GoogleDistanceMatrixClient;
use Database;
use Exception;

class DeliveryPricingService
{
    private Database $db;
    private array $settings;
    private ?GoogleDistanceMatrixClient $distanceClient = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->settings = $this->loadSettings();
    }

    /**
     * Calculate delivery fee using live distance metrics, pricing rules, and fallbacks.
     */
    public function calculateFee(array $orderData, ?float $deliveryLat = null, ?float $deliveryLng = null): array
    {
        $origin = $this->getOriginCoordinates();

        if (!$origin['lat'] || !$origin['lng'] || !$deliveryLat || !$deliveryLng) {
            return $this->buildFallbackResponse(null, null, $orderData['delivery_fee'] ?? 0, null, [
                'reason' => 'missing_coordinates',
            ]);
        }

        $distanceMetrics = $this->resolveDistanceMetrics($origin['lat'], $origin['lng'], $deliveryLat, $deliveryLng);
        $distanceKm = round($distanceMetrics['distance_m'] / 1000, 2);
        $rule = $this->resolvePricingRule($distanceKm);

        $feeBreakdown = $this->computeFee($distanceKm, $rule, $orderData);

        $requestId = $this->logAudit(
            $distanceMetrics,
            $rule,
            $feeBreakdown,
            $orderData['order_id'] ?? null
        );

        return [
            'distance_km' => $distanceKm,
            'duration_minutes' => $distanceMetrics['duration_s'] ? (int)ceil($distanceMetrics['duration_s'] / 60) : null,
            'calculated_fee' => $feeBreakdown['total_fee'],
            'base_fee' => $feeBreakdown['base_fee'],
            'fee_components' => $feeBreakdown,
            'rule' => $rule,
            'provider' => $distanceMetrics['provider'],
            'cache_hit' => $distanceMetrics['cache_hit'],
            'fallback_used' => $distanceMetrics['fallback_used'],
            'audit_request_id' => $requestId,
            'metadata' => [
                'soft_cache_expired' => $distanceMetrics['soft_cache_expired'] ?? false,
            ],
            'zone' => $rule,
        ];
    }

    /**
     * Link an audit record (by request id) to a confirmed order id once available.
     */
    public function attachAuditToOrder(string $requestId, int $orderId): void
    {
        if (!$requestId || !$orderId) {
            return;
        }

        $this->db->update(
            'delivery_pricing_audit',
            ['order_id' => $orderId],
            'request_id = :request_id',
            ['request_id' => $requestId]
        );
    }

    private function resolveDistanceMetrics(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $originHash = $this->hashCoordinates($originLat, $originLng);
        $destinationHash = $this->hashCoordinates($destLat, $destLng);

        $cacheTtlMinutes = (int)($this->settings['delivery_cache_ttl_minutes'] ?? 1440);
        $softTtlMinutes = (int)($this->settings['delivery_cache_soft_ttl_minutes'] ?? 180);

        $cached = $this->fetchCachedDistance($originHash, $destinationHash, $cacheTtlMinutes, $softTtlMinutes);
        if ($cached && empty($cached['soft_cache_expired'])) {
            return $cached;
        }

        // Attempt live refresh when cache is missing or softly expired
        try {
            $client = $this->getGoogleClient();
            if (!$client) {
                throw new Exception('Google client unavailable');
            }

            $metrics = $client->fetchMetrics($originLat, $originLng, $destLat, $destLng);
            $metrics['cache_hit'] = false;
            $metrics['fallback_used'] = false;
            $metrics['soft_cache_expired'] = false;

            $this->storeDistanceCache(
                $originHash,
                $destinationHash,
                $originLat,
                $originLng,
                $destLat,
                $destLng,
                $metrics,
                $cacheTtlMinutes
            );

            return $metrics;
        } catch (Exception $e) {
            if ($cached) {
                // Return cached metrics and flag as stale if live refresh failed
                $cached['soft_cache_expired'] = true;
                $cached['fallback_used'] = false;
                $cached['provider'] = $cached['provider'] ?? 'cache';
                return $cached;
            }

            $fallbackProvider = $this->settings['delivery_distance_fallback_provider'] ?? 'haversine';
            if ($fallbackProvider === 'manual') {
                return [
                    'provider' => 'manual_fallback',
                    'distance_m' => 0,
                    'duration_s' => null,
                    'cache_hit' => false,
                    'fallback_used' => true,
                    'soft_cache_expired' => false,
                ];
            }

            $fallbackDistance = $this->haversine($originLat, $originLng, $destLat, $destLng);

            return [
                'provider' => 'haversine_fallback',
                'distance_m' => (int)round($fallbackDistance * 1000),
                'duration_s' => null,
                'cache_hit' => false,
                'fallback_used' => true,
                'soft_cache_expired' => false,
            ];
        }
    }

    private function fetchCachedDistance(string $originHash, string $destinationHash, int $ttlMinutes, int $softTtlMinutes): ?array
    {
        $record = $this->db->fetchOne(
            'SELECT * FROM delivery_distance_cache WHERE origin_hash = ? AND destination_hash = ? ORDER BY id DESC LIMIT 1',
            [$originHash, $destinationHash]
        );

        if (!$record) {
            return null;
        }

        $now = new \DateTimeImmutable('now');
        $expiresAt = $record['expires_at'] ? new \DateTimeImmutable($record['expires_at']) : null;
        if ($expiresAt && $expiresAt < $now) {
            return null;
        }

        $cachedAt = new \DateTimeImmutable($record['cached_at']);
        $softExpired = false;
        if ($softTtlMinutes > 0) {
            $softExpiry = $cachedAt->modify('+' . $softTtlMinutes . ' minutes');
            $softExpired = $softExpiry < $now;
        }

        return [
            'provider' => $record['provider'] ?? 'cache',
            'distance_m' => (int)$record['distance_m'],
            'duration_s' => $record['duration_s'] !== null ? (int)$record['duration_s'] : null,
            'cache_hit' => true,
            'fallback_used' => false,
            'soft_cache_expired' => $softExpired,
        ];
    }

    private function storeDistanceCache(
        string $originHash,
        string $destinationHash,
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        array $metrics,
        int $ttlMinutes
    ): void {
        $expiresAt = (new \DateTimeImmutable('now'))
            ->modify('+' . max(1, $ttlMinutes) . ' minutes')
            ->format('Y-m-d H:i:s');

        $this->db->insert('delivery_distance_cache', [
            'origin_hash' => $originHash,
            'destination_hash' => $destinationHash,
            'origin_lat' => $originLat,
            'origin_lng' => $originLng,
            'destination_lat' => $destinationLat,
            'destination_lng' => $destinationLng,
            'provider' => $metrics['provider'] ?? 'google_distance_matrix',
            'distance_m' => $metrics['distance_m'],
            'duration_s' => $metrics['duration_s'] ?? null,
            'response_payload' => json_encode($metrics['raw'] ?? []),
            'expires_at' => $expiresAt,
        ]);
    }

    private function resolvePricingRule(float $distanceKm): ?array
    {
        $rules = $this->db->fetchAll(
            'SELECT * FROM delivery_pricing_rules WHERE is_active = 1 ORDER BY priority ASC, distance_min_km ASC'
        );

        foreach ($rules as $rule) {
            $min = (float)$rule['distance_min_km'];
            $max = $rule['distance_max_km'] !== null ? (float)$rule['distance_max_km'] : null;

            if ($distanceKm >= $min && ($max === null || $distanceKm <= $max)) {
                return [
                    'id' => (int)$rule['id'],
                    'name' => $rule['rule_name'],
                    'distance_min_km' => $min,
                    'distance_max_km' => $max,
                    'base_fee' => (float)$rule['base_fee'],
                    'per_km_fee' => (float)$rule['per_km_fee'],
                    'surcharge_percent' => (float)$rule['surcharge_percent'],
                    'notes' => $rule['notes'],
                ];
            }
        }

        return null;
    }

    private function computeFee(float $distanceKm, ?array $rule, array $orderData): array
    {
        $baseFee = $rule['base_fee'] ?? (float)($this->settings['delivery_base_fee'] ?? 0);
        $perKmFee = $rule['per_km_fee'] ?? (float)($this->settings['delivery_per_km_rate'] ?? 0);
        $surchargePercent = $rule['surcharge_percent'] ?? 0.0;

        $distanceOverMin = 0.0;
        if ($rule) {
            $distanceOverMin = max(0, $distanceKm - $rule['distance_min_km']);
            if ($rule['distance_max_km'] !== null) {
                $distanceOverMin = min($distanceOverMin, max(0, $rule['distance_max_km'] - $rule['distance_min_km']));
            }
        } elseif ($distanceKm > 0) {
            $distanceOverMin = $distanceKm;
        }

        $perKmComponent = round($perKmFee * $distanceOverMin, 2);
        $subtotalComponent = $baseFee + $perKmComponent;
        $surchargeComponent = round(($subtotalComponent * $surchargePercent) / 100, 2);

        $totalFee = round($subtotalComponent + $surchargeComponent, 2);

        // Allow manual override preference
        if (isset($orderData['delivery_fee']) && $orderData['delivery_fee'] !== null && $orderData['delivery_fee'] !== '') {
            $totalFee = (float)$orderData['delivery_fee'];
        }

        return [
            'base_fee' => $baseFee,
            'distance_component' => $perKmComponent,
            'surcharge_component' => $surchargeComponent,
            'total_fee' => max(0, $totalFee),
        ];
    }

    private function getOriginCoordinates(): array
    {
        $lat = $this->settings['business_latitude'] ?? null;
        $lng = $this->settings['business_longitude'] ?? null;

        if ($lat && $lng) {
            return ['lat' => (float)$lat, 'lng' => (float)$lng];
        }

        $location = $this->db->fetchOne('SELECT latitude, longitude FROM locations WHERE is_active = 1 ORDER BY id LIMIT 1');
        if ($location && !empty($location['latitude']) && !empty($location['longitude'])) {
            return ['lat' => (float)$location['latitude'], 'lng' => (float)$location['longitude']];
        }

        return ['lat' => null, 'lng' => null];
    }

    private function getGoogleClient(): ?GoogleDistanceMatrixClient
    {
        if ($this->distanceClient !== null) {
            return $this->distanceClient;
        }

        $apiKey = $this->settings['google_maps_api_key'] ?? null;
        if (!$apiKey) {
            return null;
        }

        $endpoint = $this->settings['google_distance_matrix_endpoint'] ?? 'https://maps.googleapis.com/maps/api/distancematrix/json';
        $timeout = (int)($this->settings['google_distance_matrix_timeout'] ?? 10);

        $this->distanceClient = new GoogleDistanceMatrixClient($apiKey, $endpoint, $timeout);
        return $this->distanceClient;
    }

    private function loadSettings(): array
    {
        $allSettings = settings();
        $settings = [];
        foreach ($allSettings as $key => $value) {
            if (strpos($key, 'delivery_') === 0) {
                $settings[$key] = $value;
            }
        }

        $extraKeys = ['business_latitude', 'business_longitude', 'google_maps_api_key', 'google_distance_matrix_endpoint', 'google_distance_matrix_timeout'];
        foreach ($extraKeys as $key) {
            if (!array_key_exists($key, $settings) && array_key_exists($key, $allSettings)) {
                $settings[$key] = $allSettings[$key];
            }
        }

        return $settings;
    }

    private function buildFallbackResponse(?float $distanceKm, ?int $durationMinutes, float $fee, ?array $rule, array $reason): array
    {
        $feeComponents = [
            'base_fee' => $fee,
            'distance_component' => 0,
            'surcharge_component' => 0,
            'total_fee' => $fee,
        ];

        return [
            'distance_km' => $distanceKm,
            'duration_minutes' => $durationMinutes,
            'calculated_fee' => $fee,
            'base_fee' => $fee,
            'fee_components' => $feeComponents,
            'rule' => $rule,
            'provider' => $reason['provider'] ?? 'fallback',
            'cache_hit' => false,
            'fallback_used' => true,
            'audit_request_id' => $this->logAudit([
                'provider' => $reason['provider'] ?? 'fallback',
                'distance_m' => $distanceKm !== null ? (int)round($distanceKm * 1000) : null,
                'duration_s' => $durationMinutes !== null ? $durationMinutes * 60 : null,
                'cache_hit' => false,
                'fallback_used' => true,
                'soft_cache_expired' => false,
            ], $rule, $feeComponents, null),
            'metadata' => $reason,
            'zone' => $rule,
        ];
    }

    private function haversine(float $latFrom, float $lngFrom, float $latTo, float $lngTo): float
    {
        $earthRadius = 6371;
        $latFromRad = deg2rad($latFrom);
        $lngFromRad = deg2rad($lngFrom);
        $latToRad = deg2rad($latTo);
        $lngToRad = deg2rad($lngTo);

        $latDelta = $latToRad - $latFromRad;
        $lngDelta = $lngToRad - $lngFromRad;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFromRad) * cos($latToRad) * pow(sin($lngDelta / 2), 2)));
        return round($earthRadius * $angle, 2);
    }

    private function hashCoordinates(float $lat, float $lng): string
    {
        return hash('sha256', sprintf('%.5F,%.5F', $lat, $lng));
    }

    private function logAudit(array $metrics, ?array $rule, array $feeBreakdown, ?int $orderId): string
    {
        $requestId = $this->generateRequestId();

        $this->db->insert('delivery_pricing_audit', [
            'order_id' => $orderId,
            'request_id' => $requestId,
            'provider' => $metrics['provider'] ?? 'unknown',
            'rule_id' => $rule['id'] ?? null,
            'distance_m' => $metrics['distance_m'] ?? null,
            'duration_s' => $metrics['duration_s'] ?? null,
            'fee_applied' => $feeBreakdown['total_fee'],
            'api_calls' => !empty($metrics['cache_hit']) ? 0 : 1,
            'cache_hit' => !empty($metrics['cache_hit']) ? 1 : 0,
        ]);

        return $requestId;
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
