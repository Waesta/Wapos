<?php
/**
 * API Rate Limiter Middleware
 * Protects API endpoints from abuse with configurable limits
 * Implements sliding window algorithm for accurate rate limiting
 */

class ApiRateLimiter {
    private $storageDir;
    private $limits;
    private $clientIdentifier;
    
    // Default rate limits per endpoint type
    private static $defaultLimits = [
        'default' => ['requests' => 60, 'window' => 60],      // 60 requests per minute
        'auth' => ['requests' => 5, 'window' => 900],          // 5 requests per 15 minutes
        'write' => ['requests' => 30, 'window' => 60],         // 30 writes per minute
        'read' => ['requests' => 120, 'window' => 60],         // 120 reads per minute
        'export' => ['requests' => 5, 'window' => 3600],       // 5 exports per hour
        'webhook' => ['requests' => 100, 'window' => 60],      // 100 webhooks per minute
    ];
    
    public function __construct() {
        $this->storageDir = ROOT_PATH . '/cache/api_rate_limits';
        $this->ensureStorageDir();
        $this->clientIdentifier = $this->getClientIdentifier();
    }
    
    /**
     * Check and enforce rate limit for an API endpoint
     * Returns true if request is allowed, sends 429 response and exits if not
     */
    public function check($endpointType = 'default', $customKey = null) {
        $limits = self::$defaultLimits[$endpointType] ?? self::$defaultLimits['default'];
        $key = $customKey ?? $this->clientIdentifier . ':' . $endpointType;
        
        $data = $this->getData($key);
        $now = time();
        $windowStart = $now - $limits['window'];
        
        // Clean old requests outside the window
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        $requestCount = count($data['requests']);
        
        // Check if limit exceeded
        if ($requestCount >= $limits['requests']) {
            $this->sendRateLimitResponse($limits, $data['requests']);
            exit;
        }
        
        // Record this request
        $data['requests'][] = $now;
        $this->saveData($key, $data);
        
        // Set rate limit headers
        $this->setRateLimitHeaders($limits['requests'], $requestCount + 1, $limits['window']);
        
        return true;
    }
    
    /**
     * Get remaining requests for a key
     */
    public function remaining($endpointType = 'default', $customKey = null) {
        $limits = self::$defaultLimits[$endpointType] ?? self::$defaultLimits['default'];
        $key = $customKey ?? $this->clientIdentifier . ':' . $endpointType;
        
        $data = $this->getData($key);
        $windowStart = time() - $limits['window'];
        
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        return max(0, $limits['requests'] - count($data['requests']));
    }
    
    /**
     * Clear rate limit for a key (e.g., after successful auth)
     */
    public function clear($endpointType = 'default', $customKey = null) {
        $key = $customKey ?? $this->clientIdentifier . ':' . $endpointType;
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    
    /**
     * Set custom rate limit for specific endpoint
     */
    public static function setLimit($type, $requests, $windowSeconds) {
        self::$defaultLimits[$type] = [
            'requests' => $requests,
            'window' => $windowSeconds
        ];
    }
    
    private function getClientIdentifier() {
        // Use combination of IP and user agent for better identification
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Take first IP if multiple (proxy chain)
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return hash('sha256', $ip);
    }
    
    private function ensureStorageDir() {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }
    
    private function getFilePath($key) {
        return $this->storageDir . '/' . md5($key) . '.json';
    }
    
    private function getData($key) {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return ['requests' => []];
        }
        
        $content = @file_get_contents($file);
        $data = json_decode($content, true);
        
        return is_array($data) && isset($data['requests']) ? $data : ['requests' => []];
    }
    
    private function saveData($key, $data) {
        $file = $this->getFilePath($key);
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    private function setRateLimitHeaders($limit, $used, $window) {
        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: ' . max(0, $limit - $used));
            header('X-RateLimit-Reset: ' . (time() + $window));
        }
    }
    
    private function sendRateLimitResponse($limits, $requests) {
        if (!headers_sent()) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $limits['window']);
            $this->setRateLimitHeaders($limits['requests'], count($requests), $limits['window']);
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $limits['window']
        ]);
    }
    
    /**
     * Cleanup old rate limit files (call periodically)
     */
    public function cleanup() {
        $files = glob($this->storageDir . '/*.json');
        $cutoff = time() - 7200; // 2 hours
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}

/**
 * Helper function for quick rate limit check in API files
 */
function apiRateLimit($type = 'default') {
    static $limiter = null;
    if ($limiter === null) {
        $limiter = new ApiRateLimiter();
    }
    return $limiter->check($type);
}
