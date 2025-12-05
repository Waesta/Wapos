<?php
/**
 * Simple ping endpoint for connectivity check
 * Returns 200 OK if server is reachable
 */

// No session needed, just a quick response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Return simple OK response
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'timestamp' => time()
]);
