<?php
/**
 * GDPR Data Export API
 * Allows users to export all their personal data
 * Compliant with GDPR Article 20 (Right to Data Portability)
 */

require_once '../includes/bootstrap.php';
require_once __DIR__ . '/api-middleware.php';

header('Content-Type: application/json');

// Require authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'export';
$userId = $auth->getUserId();
$user = $auth->getUser();

// Rate limit exports more strictly
$exportLimiter = new RateLimiter(3, 1440); // 3 exports per day
$exportKey = 'gdpr_export:' . $userId;

if ($exportLimiter->tooManyAttempts($exportKey)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Export limit reached. You can export your data 3 times per day.'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'export':
            $exportLimiter->hit($exportKey);
            $data = exportUserData($db, $userId, $user);
            
            // Log the export for audit
            logGdprAction($db, $userId, 'data_export', 'User exported personal data');
            
            echo json_encode([
                'success' => true,
                'message' => 'Data export successful',
                'export_date' => date('Y-m-d H:i:s'),
                'data' => $data
            ]);
            break;
            
        case 'download':
            $exportLimiter->hit($exportKey);
            $data = exportUserData($db, $userId, $user);
            
            // Log the export
            logGdprAction($db, $userId, 'data_download', 'User downloaded personal data');
            
            // Send as downloadable JSON file
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="wapos_data_export_' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            
        case 'delete_request':
            // Create a deletion request (requires admin approval for full deletion)
            $requestId = createDeletionRequest($db, $userId, $user);
            
            logGdprAction($db, $userId, 'deletion_request', 'User requested account deletion');
            
            echo json_encode([
                'success' => true,
                'message' => 'Deletion request submitted. An administrator will process your request within 30 days.',
                'request_id' => $requestId
            ]);
            break;
            
        case 'anonymize':
            // Anonymize user data (keeps records but removes PII)
            anonymizeUserData($db, $userId);
            
            logGdprAction($db, $userId, 'data_anonymized', 'User data anonymized');
            
            // Force logout after anonymization
            $auth->logout();
            
            echo json_encode([
                'success' => true,
                'message' => 'Your personal data has been anonymized. You have been logged out.'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('GDPR Export Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred processing your request']);
}

/**
 * Export all user data
 */
function exportUserData($db, $userId, $user) {
    $data = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'format_version' => '1.0',
            'system' => 'WAPOS'
        ],
        'personal_information' => [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? null,
            'role' => $user['role'],
            'account_created' => $user['created_at'],
            'last_login' => $user['last_login']
        ],
        'activity_data' => [],
        'transaction_data' => [],
        'preferences' => []
    ];
    
    // Get user's sales/transactions
    $sales = $db->fetchAll("
        SELECT id, sale_number, total_amount, payment_method, created_at 
        FROM sales 
        WHERE user_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1000
    ", [$userId]);
    $data['transaction_data']['sales'] = $sales ?: [];
    
    // Get user's orders
    $orders = $db->fetchAll("
        SELECT id, order_number, status, total_amount, created_at 
        FROM orders 
        WHERE user_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1000
    ", [$userId]);
    $data['transaction_data']['orders'] = $orders ?: [];
    
    // Get audit log entries for this user
    $auditLogs = $db->fetchAll("
        SELECT action, details, ip_address, created_at 
        FROM audit_logs 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 500
    ", [$userId]);
    $data['activity_data']['audit_logs'] = $auditLogs ?: [];
    
    // Get user sessions
    $sessions = $db->fetchAll("
        SELECT ip_address, user_agent, created_at, expires_at 
        FROM user_sessions 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ", [$userId]);
    $data['activity_data']['sessions'] = $sessions ?: [];
    
    // Get user permissions
    $permissions = $db->fetchAll("
        SELECT sm.module_key, sa.action_key, up.is_granted, up.granted_at 
        FROM user_permissions up
        JOIN system_modules sm ON up.module_id = sm.id
        JOIN system_actions sa ON up.action_id = sa.id
        WHERE up.user_id = ?
    ", [$userId]);
    $data['preferences']['permissions'] = $permissions ?: [];
    
    // Get group memberships
    $groups = $db->fetchAll("
        SELECT pg.group_name, ugm.assigned_at 
        FROM user_group_memberships ugm
        JOIN permission_groups pg ON ugm.group_id = pg.id
        WHERE ugm.user_id = ?
    ", [$userId]);
    $data['preferences']['groups'] = $groups ?: [];
    
    return $data;
}

/**
 * Create a deletion request
 */
function createDeletionRequest($db, $userId, $user) {
    // Check if request already exists
    $existing = $db->fetchOne("
        SELECT id FROM gdpr_deletion_requests 
        WHERE user_id = ? AND status = 'pending'
    ", [$userId]);
    
    if ($existing) {
        return $existing['id'];
    }
    
    // Create the table if it doesn't exist
    $db->execute("
        CREATE TABLE IF NOT EXISTS gdpr_deletion_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            processed_by INT UNSIGNED NULL,
            notes TEXT,
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB
    ");
    
    $db->insert('gdpr_deletion_requests', [
        'user_id' => $userId,
        'reason' => $_POST['reason'] ?? 'User requested deletion',
        'status' => 'pending'
    ]);
    
    return $db->lastInsertId();
}

/**
 * Anonymize user data
 */
function anonymizeUserData($db, $userId) {
    $anonymousId = 'ANON_' . bin2hex(random_bytes(8));
    
    // Anonymize user record
    $db->update('users', [
        'username' => $anonymousId,
        'full_name' => 'Anonymous User',
        'email' => $anonymousId . '@anonymized.local',
        'phone' => null,
        'is_active' => 0,
        'deleted_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $userId]);
    
    // Clear sessions
    $db->execute("DELETE FROM user_sessions WHERE user_id = ?", [$userId]);
    
    // Anonymize audit logs (keep for compliance but remove PII)
    $db->execute("
        UPDATE audit_logs 
        SET ip_address = '0.0.0.0', 
            user_agent = 'anonymized'
        WHERE user_id = ?
    ", [$userId]);
}

/**
 * Log GDPR action for compliance
 */
function logGdprAction($db, $userId, $action, $details) {
    // Create GDPR log table if not exists
    $db->execute("
        CREATE TABLE IF NOT EXISTS gdpr_audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB
    ");
    
    $db->insert('gdpr_audit_log', [
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
