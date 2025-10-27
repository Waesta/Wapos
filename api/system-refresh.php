<?php
require_once '../includes/bootstrap.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'force_refresh':
            // Force SystemManager refresh
            $systemManager->forceRefresh();
            
            // Clear any PHP opcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'System refreshed successfully',
                'status' => $systemManager->getSystemStatus()
            ]);
            break;
            
        case 'clear_cache':
            // Clear SystemManager cache
            $systemManager->clearCache();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
            break;
            
        case 'system_status':
            echo json_encode([
                'success' => true,
                'status' => $systemManager->getSystemStatus()
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
