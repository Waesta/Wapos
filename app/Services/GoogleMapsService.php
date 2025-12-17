<?php

namespace App\Services;

use Exception;
use PDO;

/**
 * Google Maps Service - Comprehensive integration for Places, Routes, and Maps JavaScript API
 * Handles address validation, geocoding, route optimization, and distance calculations
 */
class GoogleMapsService
{
    private PDO $db;
    private array $config;
    private string $placesApiKey;
    private string $routesApiKey;
    private string $mapsJsApiKey;
    private int $timeout;

    private const PLACES_API_URL = 'https://places.googleapis.com/v1';
    private const ROUTES_API_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';
    private const GEOCODING_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
        
        // Load API keys from settings if not provided
        $this->placesApiKey = $config['google_places_api_key'] ?? $this->getSetting('google_places_api_key', '');
        $this->routesApiKey = $config['google_routes_api_key'] ?? $this->getSetting('google_routes_api_key', '');
        $this->mapsJsApiKey = $config['google_maps_api_key'] ?? $this->getSetting('google_maps_api_key', '');
        $this->timeout = $config['timeout'] ?? 15;
    }

    /**
     * Places API: Autocomplete address suggestions
     */
    public function autocompleteAddress(string $input, array $options = []): array
    {
        if (empty($this->placesApiKey)) {
            throw new Exception('Google Places API key not configured');
        }

        $url = self::PLACES_API_URL . '/places:autocomplete';
        
        $payload = [
            'input' => $input,
            'languageCode' => $options['language'] ?? 'en',
        ];

        // Add location bias if provided
        if (!empty($options['location'])) {
            $payload['locationBias'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $options['location']['lat'],
                        'longitude' => $options['location']['lng']
                    ],
                    'radius' => $options['radius'] ?? 50000 // 50km default
                ]
            ];
        }

        // Restrict to specific country
        if (!empty($options['country'])) {
            $payload['includedRegionCodes'] = [$options['country']];
        }

        $response = $this->makeApiRequest($url, 'POST', $payload, [
            'X-Goog-Api-Key: ' . $this->placesApiKey,
            'Content-Type: application/json'
        ]);

        return $response['suggestions'] ?? [];
    }

    /**
     * Places API: Get place details including full address and coordinates
     */
    public function getPlaceDetails(string $placeId): array
    {
        if (empty($this->placesApiKey)) {
            throw new Exception('Google Places API key not configured');
        }

        $url = self::PLACES_API_URL . '/places/' . $placeId;
        
        $response = $this->makeApiRequest($url, 'GET', null, [
            'X-Goog-Api-Key: ' . $this->placesApiKey,
            'X-Goog-FieldMask: id,displayName,formattedAddress,location,addressComponents'
        ]);

        return [
            'place_id' => $response['id'] ?? $placeId,
            'formatted_address' => $response['formattedAddress'] ?? '',
            'latitude' => $response['location']['latitude'] ?? null,
            'longitude' => $response['location']['longitude'] ?? null,
            'address_components' => $response['addressComponents'] ?? []
        ];
    }

    /**
     * Geocoding API: Convert address to coordinates
     */
    public function geocodeAddress(string $address): array
    {
        if (empty($this->mapsJsApiKey)) {
            throw new Exception('Google Maps API key not configured');
        }

        $url = self::GEOCODING_API_URL . '?' . http_build_query([
            'address' => $address,
            'key' => $this->mapsJsApiKey
        ]);

        $response = $this->makeApiRequest($url, 'GET');

        if ($response['status'] !== 'OK' || empty($response['results'])) {
            throw new Exception('Geocoding failed: ' . ($response['status'] ?? 'Unknown error'));
        }

        $result = $response['results'][0];
        return [
            'formatted_address' => $result['formatted_address'],
            'latitude' => $result['geometry']['location']['lat'],
            'longitude' => $result['geometry']['location']['lng'],
            'place_id' => $result['place_id'] ?? null,
            'address_components' => $result['address_components'] ?? []
        ];
    }

    /**
     * Routes API: Compute optimized route for single delivery
     */
    public function computeRoute(array $origin, array $destination, array $options = []): array
    {
        if (empty($this->routesApiKey)) {
            throw new Exception('Google Routes API key not configured');
        }

        $payload = [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $origin['lat'],
                        'longitude' => $origin['lng']
                    ]
                ]
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $destination['lat'],
                        'longitude' => $destination['lng']
                    ]
                ]
            ],
            'travelMode' => $options['travel_mode'] ?? 'DRIVE',
            'routingPreference' => $options['routing_preference'] ?? 'TRAFFIC_AWARE',
            'computeAlternativeRoutes' => $options['alternatives'] ?? false,
            'routeModifiers' => [
                'avoidTolls' => $options['avoid_tolls'] ?? false,
                'avoidHighways' => $options['avoid_highways'] ?? false,
                'avoidFerries' => $options['avoid_ferries'] ?? false
            ],
            'languageCode' => 'en-US',
            'units' => 'METRIC'
        ];

        $response = $this->makeApiRequest(self::ROUTES_API_URL, 'POST', $payload, [
            'X-Goog-Api-Key: ' . $this->routesApiKey,
            'X-Goog-FieldMask: routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.legs',
            'Content-Type: application/json'
        ]);

        if (empty($response['routes'])) {
            throw new Exception('No routes found');
        }

        $route = $response['routes'][0];
        
        return [
            'distance_meters' => $route['distanceMeters'] ?? 0,
            'duration_seconds' => $this->parseDuration($route['duration'] ?? '0s'),
            'polyline' => $route['polyline']['encodedPolyline'] ?? '',
            'legs' => $route['legs'] ?? [],
            'bounds' => $this->calculateBounds($route['legs'] ?? [])
        ];
    }

    /**
     * Routes API: Optimize route for multiple deliveries (waypoint optimization)
     */
    public function optimizeMultipleDeliveries(array $origin, array $deliveries, array $options = []): array
    {
        if (empty($this->routesApiKey)) {
            throw new Exception('Google Routes API key not configured');
        }

        if (count($deliveries) > 25) {
            throw new Exception('Maximum 25 waypoints allowed');
        }

        $waypoints = [];
        foreach ($deliveries as $delivery) {
            $waypoints[] = [
                'location' => [
                    'latLng' => [
                        'latitude' => $delivery['lat'],
                        'longitude' => $delivery['lng']
                    ]
                ],
                'via' => false
            ];
        }

        $payload = [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $origin['lat'],
                        'longitude' => $origin['lng']
                    ]
                ]
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $origin['lat'], // Return to origin
                        'longitude' => $origin['lng']
                    ]
                ]
            ],
            'intermediates' => $waypoints,
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE_OPTIMAL',
            'optimizeWaypointOrder' => true,
            'computeAlternativeRoutes' => false,
            'languageCode' => 'en-US',
            'units' => 'METRIC'
        ];

        $response = $this->makeApiRequest(self::ROUTES_API_URL, 'POST', $payload, [
            'X-Goog-Api-Key: ' . $this->routesApiKey,
            'X-Goog-FieldMask: routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.legs,routes.optimizedIntermediateWaypointIndex',
            'Content-Type: application/json'
        ]);

        if (empty($response['routes'])) {
            throw new Exception('Route optimization failed');
        }

        $route = $response['routes'][0];
        $optimizedOrder = $route['optimizedIntermediateWaypointIndex'] ?? [];

        return [
            'total_distance_meters' => $route['distanceMeters'] ?? 0,
            'total_duration_seconds' => $this->parseDuration($route['duration'] ?? '0s'),
            'optimized_order' => $optimizedOrder,
            'polyline' => $route['polyline']['encodedPolyline'] ?? '',
            'legs' => $route['legs'] ?? []
        ];
    }

    /**
     * Routes API: Calculate distances and durations for multiple origins/destinations
     * Uses Routes API instead of deprecated Distance Matrix API
     */
    public function calculateDistanceMatrix(array $origins, array $destinations, array $options = []): array
    {
        if (empty($this->routesApiKey)) {
            throw new Exception('Google Routes API key not configured');
        }

        // Routes API processes one origin-destination pair at a time
        // For multiple pairs, we batch the requests
        $results = [];
        
        foreach ($origins as $originIndex => $origin) {
            $row = [];
            foreach ($destinations as $destIndex => $destination) {
                try {
                    $route = $this->computeRoute($origin, $destination, $options);
                    $row[] = [
                        'status' => 'OK',
                        'distance' => [
                            'value' => $route['distance_meters'],
                            'text' => round($route['distance_meters'] / 1000, 1) . ' km'
                        ],
                        'duration' => [
                            'value' => $route['duration_seconds'],
                            'text' => ceil($route['duration_seconds'] / 60) . ' mins'
                        ]
                    ];
                } catch (Exception $e) {
                    $row[] = [
                        'status' => 'NOT_FOUND',
                        'error' => $e->getMessage()
                    ];
                }
            }
            $results[] = ['elements' => $row];
        }

        return [
            'origin_addresses' => array_map(function($o) { 
                return isset($o['address']) ? $o['address'] : $o['lat'] . ',' . $o['lng']; 
            }, $origins),
            'destination_addresses' => array_map(function($d) { 
                return isset($d['address']) ? $d['address'] : $d['lat'] . ',' . $d['lng']; 
            }, $destinations),
            'rows' => $results
        ];
    }

    /**
     * Validate and standardize address using Places API
     */
    public function validateAddress(string $address): array
    {
        try {
            $geocoded = $this->geocodeAddress($address);
            
            return [
                'valid' => true,
                'formatted_address' => $geocoded['formatted_address'],
                'latitude' => $geocoded['latitude'],
                'longitude' => $geocoded['longitude'],
                'place_id' => $geocoded['place_id'],
                'confidence' => 'high'
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'confidence' => 'none'
            ];
        }
    }

    /**
     * Get estimated delivery time based on current traffic
     */
    public function getDeliveryETA(array $origin, array $destination): array
    {
        try {
            $route = $this->computeRoute($origin, $destination, [
                'routing_preference' => 'TRAFFIC_AWARE'
            ]);

            $eta = new \DateTime();
            $eta->modify('+' . $route['duration_seconds'] . ' seconds');

            return [
                'eta' => $eta->format('Y-m-d H:i:s'),
                'duration_minutes' => ceil($route['duration_seconds'] / 60),
                'distance_km' => round($route['distance_meters'] / 1000, 2),
                'traffic_aware' => true
            ];
        } catch (Exception $e) {
            // Fallback to simple calculation
            $distance = $this->haversineDistance(
                $origin['lat'], $origin['lng'],
                $destination['lat'], $destination['lng']
            );

            $avgSpeed = 30; // km/h average in city
            $durationMinutes = ceil(($distance / $avgSpeed) * 60);

            $eta = new \DateTime();
            $eta->modify('+' . $durationMinutes . ' minutes');

            return [
                'eta' => $eta->format('Y-m-d H:i:s'),
                'duration_minutes' => $durationMinutes,
                'distance_km' => round($distance, 2),
                'traffic_aware' => false,
                'fallback' => true
            ];
        }
    }

    /**
     * Helper: Make HTTP request to Google API
     */
    private function makeApiRequest(string $url, string $method = 'GET', ?array $payload = null, array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = [
            'Accept: application/json'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'HTTP ' . $httpCode;
            throw new Exception('API request failed: ' . $errorMessage);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }

        return $data;
    }

    /**
     * Helper: Parse duration string (e.g., "1234s" to seconds)
     */
    private function parseDuration(string $duration): int
    {
        if (preg_match('/^(\d+)s$/', $duration, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * Helper: Calculate bounds from route legs
     */
    private function calculateBounds(array $legs): array
    {
        $bounds = [
            'north' => -90,
            'south' => 90,
            'east' => -180,
            'west' => 180
        ];

        foreach ($legs as $leg) {
            if (isset($leg['startLocation']['latLng'])) {
                $lat = $leg['startLocation']['latLng']['latitude'];
                $lng = $leg['startLocation']['latLng']['longitude'];
                $bounds['north'] = max($bounds['north'], $lat);
                $bounds['south'] = min($bounds['south'], $lat);
                $bounds['east'] = max($bounds['east'], $lng);
                $bounds['west'] = min($bounds['west'], $lng);
            }
            if (isset($leg['endLocation']['latLng'])) {
                $lat = $leg['endLocation']['latLng']['latitude'];
                $lng = $leg['endLocation']['latLng']['longitude'];
                $bounds['north'] = max($bounds['north'], $lat);
                $bounds['south'] = min($bounds['south'], $lat);
                $bounds['east'] = max($bounds['east'], $lng);
                $bounds['west'] = min($bounds['west'], $lng);
            }
        }

        return $bounds;
    }


    /**
     * Helper: Haversine distance calculation (fallback)
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
     * Helper: Get setting from database
     */
    private function getSetting(string $key, $default = null)
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get JavaScript configuration for frontend
     */
    public function getJavaScriptConfig(): array
    {
        return [
            'mapsApiKey' => $this->mapsJsApiKey,
            'placesApiKey' => $this->placesApiKey,
            'routesApiKey' => $this->routesApiKey,
            'libraries' => ['places', 'geometry', 'drawing']
        ];
    }
}
