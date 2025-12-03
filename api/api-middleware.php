<?php
/**
 * API Middleware
 * Include this at the top of API endpoints for:
 * - Rate limiting
 * - CORS headers
 * - JSON response setup
 * - Authentication check
 */

// Determine rate limit type based on request method and endpoint
function getApiRateLimitType() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Auth-related endpoints (stricter limits)
    $authEndpoints = ['login.php', 'reset-password.php', 'register.php'];
    if (in_array($script, $authEndpoints)) {
        return 'auth';
    }
    
    // Export endpoints (very limited)
    if (strpos($script, 'export') !== false || strpos($script, 'backup') !== false) {
        return 'export';
    }
    
    // Webhook endpoints (higher limits)
    if (strpos($script, 'webhook') !== false) {
        return 'webhook';
    }
    
    // Write operations
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return 'write';
    }
    
    // Read operations
    return 'read';
}

// Apply rate limiting
$apiRateLimitType = getApiRateLimitType();
apiRateLimit($apiRateLimitType);

// Set CORS headers for API responses
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: ' . (defined('APP_URL') ? APP_URL : '*'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
