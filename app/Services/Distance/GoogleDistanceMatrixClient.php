<?php

namespace App\Services\Distance;

use Exception;

class GoogleDistanceMatrixClient
{
    private string $apiKey;
    private string $endpoint;
    private int $timeout;

    public function __construct(string $apiKey, string $endpoint = 'https://maps.googleapis.com/maps/api/distancematrix/json', int $timeout = 10)
    {
        if (empty($apiKey)) {
            throw new Exception('Google Maps API key is required for distance calculations.');
        }

        $this->apiKey = $apiKey;
        $this->endpoint = rtrim($endpoint, '/');
        $this->timeout = max(5, $timeout);
    }

    /**
     * Fetch driving distance and duration between origin and destination.
     *
     * @throws Exception on API failure or invalid response
     */
    public function fetchMetrics(float $originLat, float $originLng, float $destinationLat, float $destinationLng): array
    {
        $params = http_build_query([
            'origins' => sprintf('%F,%F', $originLat, $originLng),
            'destinations' => sprintf('%F,%F', $destinationLat, $destinationLng),
            'mode' => 'driving',
            'units' => 'metric',
            'key' => $this->apiKey,
        ]);

        $url = $this->endpoint . '?' . $params;
        $response = $this->performRequest($url);

        if (empty($response['rows'][0]['elements'][0])) {
            throw new Exception('Unexpected response structure from Google Distance Matrix API');
        }

        $element = $response['rows'][0]['elements'][0];
        if (($element['status'] ?? '') !== 'OK') {
            throw new Exception('Google Distance Matrix returned status: ' . ($element['status'] ?? 'UNKNOWN'));
        }

        $distanceMeters = (int)($element['distance']['value'] ?? 0);
        $durationSeconds = (int)($element['duration']['value'] ?? 0);

        if ($distanceMeters <= 0) {
            throw new Exception('Google Distance Matrix returned an invalid distance.');
        }

        return [
            'provider' => 'google_distance_matrix',
            'distance_m' => $distanceMeters,
            'duration_s' => $durationSeconds > 0 ? $durationSeconds : null,
            'raw' => $element,
        ];
    }

    private function performRequest(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Failed to call Google Distance Matrix API: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            throw new Exception('Google Distance Matrix API returned HTTP ' . $status);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new Exception('Failed to decode Google Distance Matrix response');
        }

        $apiStatus = $decoded['status'] ?? 'UNKNOWN';
        if ($apiStatus !== 'OK') {
            throw new Exception('Google Distance Matrix API status: ' . $apiStatus);
        }

        return $decoded;
    }
}
