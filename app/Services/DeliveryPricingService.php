<?php

namespace App\Services;

use Database;

class DeliveryPricingService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function calculateFee(array $orderData, ?float $deliveryLat = null, ?float $deliveryLng = null): array
    {
        $origin = $this->getOriginCoordinates();
        if (!$origin['lat'] || !$origin['lng'] || !$deliveryLat || !$deliveryLng) {
            return [
                'distance_km' => null,
                'base_fee' => $orderData['delivery_fee'] ?? 0,
                'calculated_fee' => $orderData['delivery_fee'] ?? 0,
                'zone' => null,
            ];
        }

        $distanceKm = $this->haversine($origin['lat'], $origin['lng'], $deliveryLat, $deliveryLng);
        $zone = $this->matchZone($deliveryLat, $deliveryLng);

        $base = $zone['base_fee'] ?? (float)($this->getSetting('delivery_base_fee') ?? 0);
        $perKm = $zone['per_km_rate'] ?? (float)($this->getSetting('delivery_per_km_rate') ?? 0);

        $calculatedFee = round($base + ($perKm * max(0, $distanceKm - ($zone['base_distance'] ?? 0))), 2);

        return [
            'distance_km' => $distanceKm,
            'base_fee' => $base,
            'calculated_fee' => $calculatedFee,
            'zone' => $zone,
        ];
    }

    private function getOriginCoordinates(): array
    {
        $lat = $this->getSetting('business_latitude');
        $lng = $this->getSetting('business_longitude');

        if ($lat && $lng) {
            return ['lat' => (float)$lat, 'lng' => (float)$lng];
        }

        $location = $this->db->fetchOne("SELECT latitude, longitude FROM locations WHERE is_active = 1 ORDER BY id LIMIT 1");
        if ($location && !empty($location['latitude']) && !empty($location['longitude'])) {
            return ['lat' => (float)$location['latitude'], 'lng' => (float)$location['longitude']];
        }

        return ['lat' => null, 'lng' => null];
    }

    private function matchZone(float $lat, float $lng): ?array
    {
        $zone = $this->db->fetchOne(
            "SELECT id, zone_name, zone_code, base_delivery_fee, per_km_rate, estimated_delivery_time_minutes
             FROM delivery_zones
             WHERE is_active = 1
             ORDER BY priority_level ASC"
        );

        if ($zone) {
            return [
                'id' => $zone['id'],
                'name' => $zone['zone_name'],
                'code' => $zone['zone_code'],
                'base_fee' => (float)$zone['base_delivery_fee'],
                'per_km_rate' => (float)$zone['per_km_rate'],
                'base_distance' => 0,
            ];
        }

        return null;
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

    private function getSetting(string $key): ?string
    {
        $row = $this->db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $row['setting_value'] ?? null;
    }
}
