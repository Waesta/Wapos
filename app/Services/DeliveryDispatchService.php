<?php

namespace App\Services;

use Exception;
use PDO;

/**
 * Delivery Dispatch Service - Intelligent rider assignment and dispatch optimization
 * Uses Google Routes API for traffic-aware rider selection
 */
class DeliveryDispatchService
{
    private PDO $db;
    private GoogleMapsService $mapsService;
    private array $config;

    public function __construct(PDO $db, GoogleMapsService $mapsService, array $config = [])
    {
        $this->db = $db;
        $this->mapsService = $mapsService;
        $this->config = $config;
    }

    /**
     * Find the optimal rider for a delivery using traffic-aware route calculation
     * Supports manual mode when Google Maps API is unavailable
     * 
     * @param float $deliveryLat Delivery destination latitude
     * @param float $deliveryLng Delivery destination longitude
     * @param array $options Additional options (priority, max_distance, etc.)
     * @return array Optimal rider details with route information
     * @throws Exception When no suitable riders available
     */
    public function findOptimalRider(float $deliveryLat, float $deliveryLng, array $options = []): array
    {
        // Get business location as fallback origin
        $businessLocation = $this->getBusinessLocation();
        
        // Get all active riders with their current locations
        $activeRiders = $this->getActiveRiders($options);
        
        if (empty($activeRiders)) {
            throw new Exception('no_riders_available');
        }

        // Check if manual mode is enabled (no Google Maps API)
        $manualMode = !empty($options['manual_mode']) || $this->isManualModeEnabled();

        // Calculate routes from each rider to delivery destination
        $riderRoutes = [];
        $errors = [];

        foreach ($activeRiders as $rider) {
            try {
                // Use rider's current location if available, otherwise use business location
                $origin = [
                    'lat' => $rider['current_latitude'] ?? $businessLocation['lat'],
                    'lng' => $rider['current_longitude'] ?? $businessLocation['lng']
                ];

                $destination = [
                    'lat' => $deliveryLat,
                    'lng' => $deliveryLng
                ];

                if ($manualMode) {
                    // Manual mode: use simple distance calculation
                    $route = $this->calculateSimpleRoute($origin, $destination);
                } else {
                    // API mode: use Google Maps Routes API
                    $route = $this->mapsService->computeRoute($origin, $destination, [
                        'routing_preference' => 'TRAFFIC_AWARE_OPTIMAL',
                        'travel_mode' => 'DRIVE'
                    ]);
                }

                $riderRoutes[] = [
                    'rider_id' => $rider['id'],
                    'rider_name' => $rider['name'],
                    'rider_phone' => $rider['phone'],
                    'vehicle_type' => $rider['vehicle_type'],
                    'vehicle_number' => $rider['vehicle_number'],
                    'current_deliveries' => $rider['active_deliveries'],
                    'max_capacity' => $rider['max_active_deliveries'] ?? 3,
                    'distance_meters' => $route['distance_meters'],
                    'duration_seconds' => $route['duration_seconds'],
                    'duration_minutes' => ceil($route['duration_seconds'] / 60),
                    'distance_km' => round($route['distance_meters'] / 1000, 2),
                    'polyline' => $route['polyline'] ?? '',
                    'has_gps_location' => !empty($rider['current_latitude']),
                    'location_updated_at' => $rider['location_updated_at'],
                    'score' => $this->calculateRiderScore($route, $rider, $options),
                    'manual_mode' => $manualMode
                ];

            } catch (Exception $e) {
                $errors[] = [
                    'rider_id' => $rider['id'],
                    'rider_name' => $rider['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        if (empty($riderRoutes)) {
            throw new Exception('route_calculation_failed');
        }

        // Sort by score (lower is better - shortest duration with capacity consideration)
        usort($riderRoutes, function($a, $b) {
            return $a['score'] <=> $b['score'];
        });

        $optimalRider = $riderRoutes[0];
        $alternatives = array_slice($riderRoutes, 1, 2); // Top 3 alternatives

        return [
            'optimal_rider' => $optimalRider,
            'alternatives' => $alternatives,
            'total_candidates' => count($activeRiders),
            'successful_calculations' => count($riderRoutes),
            'errors' => $errors,
            'selection_criteria' => [
                'primary' => $manualMode ? 'straight_line_distance' : 'traffic_aware_duration',
                'secondary' => 'current_capacity',
                'tertiary' => 'distance'
            ],
            'manual_mode' => $manualMode
        ];
    }

    /**
     * Calculate simple route using Haversine distance (manual mode)
     */
    private function calculateSimpleRoute(array $origin, array $destination): array
    {
        $distanceKm = $this->haversineDistance(
            $origin['lat'], $origin['lng'],
            $destination['lat'], $destination['lng']
        );

        $distanceMeters = (int)($distanceKm * 1000);
        
        // Estimate duration: assume 30 km/h average speed in city
        $avgSpeedKmh = 30;
        $durationHours = $distanceKm / $avgSpeedKmh;
        $durationSeconds = (int)($durationHours * 3600);

        return [
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'polyline' => '',
            'manual_calculation' => true
        ];
    }

    /**
     * Check if manual mode is enabled in settings
     */
    private function isManualModeEnabled(): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT setting_value 
                FROM settings 
                WHERE setting_key = 'delivery_manual_pricing_mode'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result['setting_value']) && $result['setting_value'] !== '0';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Haversine distance calculation
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * Calculate rider score for optimal selection
     * Lower score = better candidate
     */
    private function calculateRiderScore(array $route, array $rider, array $options): float
    {
        $durationMinutes = ceil($route['duration_seconds'] / 60);
        $currentLoad = $rider['active_deliveries'];
        $maxCapacity = $rider['max_active_deliveries'] ?? 3;

        // Base score is duration in minutes
        $score = $durationMinutes;

        // Penalty for riders near capacity (add 5 minutes per active delivery)
        $capacityPenalty = $currentLoad * 5;
        $score += $capacityPenalty;

        // Penalty for riders without GPS location (add 10 minutes)
        if (empty($rider['current_latitude'])) {
            $score += 10;
        }

        // Penalty for stale GPS data (older than 5 minutes)
        if (!empty($rider['location_updated_at'])) {
            $locationAge = time() - strtotime($rider['location_updated_at']);
            if ($locationAge > 300) { // 5 minutes
                $score += 5;
            }
        }

        // Priority deliveries get preference (reduce score by 20%)
        if (!empty($options['priority']) && $options['priority'] === 'high') {
            $score *= 0.8;
        }

        return round($score, 2);
    }

    /**
     * Get all active riders who can accept new deliveries
     */
    private function getActiveRiders(array $options = []): array
    {
        $maxActiveDeliveries = $options['max_active_deliveries'] ?? 3;
        $maxDistanceKm = $options['max_distance_km'] ?? 50;

        $sql = "
            SELECT 
                r.id,
                r.name,
                r.phone,
                r.vehicle_type,
                r.vehicle_number,
                r.current_latitude,
                r.current_longitude,
                r.location_updated_at,
                r.is_active,
                r.max_active_deliveries,
                COUNT(d.id) as active_deliveries
            FROM riders r
            LEFT JOIN deliveries d ON r.id = d.rider_id 
                AND d.status IN ('assigned', 'picked-up', 'in-transit')
            WHERE r.is_active = 1
            GROUP BY r.id
            HAVING active_deliveries < :max_active
            ORDER BY active_deliveries ASC, r.location_updated_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['max_active' => $maxActiveDeliveries]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get business location coordinates
     */
    private function getBusinessLocation(): array
    {
        $stmt = $this->db->prepare("
            SELECT setting_value 
            FROM settings 
            WHERE setting_key IN ('business_latitude', 'business_longitude')
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'lat' => (float)($settings['business_latitude'] ?? -1.286389),
            'lng' => (float)($settings['business_longitude'] ?? 36.817223)
        ];
    }

    /**
     * Assign delivery to optimal rider with automatic selection
     * 
     * @param int $deliveryId Delivery ID to assign
     * @param array $options Assignment options
     * @return array Assignment result with rider details
     */
    public function autoAssignDelivery(int $deliveryId, array $options = []): array
    {
        // Get delivery details
        $stmt = $this->db->prepare("
            SELECT d.*, o.delivery_latitude, o.delivery_longitude, o.customer_name
            FROM deliveries d
            JOIN orders o ON d.order_id = o.id
            WHERE d.id = ?
        ");
        $stmt->execute([$deliveryId]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$delivery) {
            throw new Exception('Delivery not found');
        }

        if (empty($delivery['delivery_latitude']) || empty($delivery['delivery_longitude'])) {
            throw new Exception('Delivery coordinates missing');
        }

        // Find optimal rider
        try {
            $result = $this->findOptimalRider(
                (float)$delivery['delivery_latitude'],
                (float)$delivery['delivery_longitude'],
                $options
            );

            $optimalRider = $result['optimal_rider'];

            // Assign delivery to rider
            $updateStmt = $this->db->prepare("
                UPDATE deliveries 
                SET 
                    rider_id = ?,
                    status = 'assigned',
                    assigned_at = NOW(),
                    estimated_delivery_time = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $optimalRider['rider_id'],
                $optimalRider['duration_minutes'],
                $deliveryId
            ]);

            // Log assignment
            $this->logDispatchDecision($deliveryId, $result);

            return [
                'success' => true,
                'delivery_id' => $deliveryId,
                'assigned_rider' => $optimalRider,
                'estimated_duration' => $optimalRider['duration_minutes'],
                'alternatives_count' => count($result['alternatives']),
                'auto_assigned' => true
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'delivery_id' => $deliveryId,
                'error' => $e->getMessage(),
                'requires_manual_assignment' => true
            ];
        }
    }

    /**
     * Log dispatch decision for analytics and auditing
     */
    private function logDispatchDecision(int $deliveryId, array $dispatchResult): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO delivery_dispatch_log 
                (delivery_id, selected_rider_id, duration_seconds, distance_meters, 
                 candidates_evaluated, selection_score, dispatch_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $optimalRider = $dispatchResult['optimal_rider'];
            
            $stmt->execute([
                $deliveryId,
                $optimalRider['rider_id'],
                $optimalRider['duration_seconds'],
                $optimalRider['distance_meters'],
                $dispatchResult['total_candidates'],
                $optimalRider['score'],
                json_encode($dispatchResult)
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the assignment
            error_log("Failed to log dispatch decision: " . $e->getMessage());
        }
    }

    /**
     * Get rider availability status with current load
     */
    public function getRiderAvailability(): array
    {
        $sql = "
            SELECT 
                r.id,
                r.name,
                r.phone,
                r.vehicle_type,
                r.is_active,
                r.current_latitude,
                r.current_longitude,
                r.location_updated_at,
                r.max_active_deliveries,
                COUNT(d.id) as active_deliveries,
                (r.max_active_deliveries - COUNT(d.id)) as available_capacity,
                CASE 
                    WHEN COUNT(d.id) >= r.max_active_deliveries THEN 'full'
                    WHEN COUNT(d.id) >= (r.max_active_deliveries * 0.7) THEN 'busy'
                    WHEN COUNT(d.id) > 0 THEN 'active'
                    ELSE 'available'
                END as status
            FROM riders r
            LEFT JOIN deliveries d ON r.id = d.rider_id 
                AND d.status IN ('assigned', 'picked-up', 'in-transit')
            WHERE r.is_active = 1
            GROUP BY r.id
            ORDER BY active_deliveries ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate if address is routable
     */
    public function validateDeliveryAddress(float $lat, float $lng): array
    {
        try {
            $businessLocation = $this->getBusinessLocation();
            
            $route = $this->mapsService->computeRoute(
                $businessLocation,
                ['lat' => $lat, 'lng' => $lng],
                ['routing_preference' => 'TRAFFIC_AWARE']
            );

            return [
                'routable' => true,
                'distance_km' => round($route['distance_meters'] / 1000, 2),
                'estimated_duration_minutes' => ceil($route['duration_seconds'] / 60),
                'within_service_area' => $route['distance_meters'] <= 50000 // 50km default
            ];

        } catch (Exception $e) {
            return [
                'routable' => false,
                'error' => $e->getMessage(),
                'requires_manual_review' => true
            ];
        }
    }
}
