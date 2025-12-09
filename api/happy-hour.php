<?php
/**
 * Happy Hour API
 * Manages time-based pricing rules
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$userRole = strtolower($auth->getRole() ?? '');

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_active';
    
    switch ($action) {
        case 'get_active':
            $now = date('H:i:s');
            $today = strtolower(date('l'));
            
            $activeHH = $db->fetchOne("
                SELECT * FROM happy_hour_rules 
                WHERE is_active = 1 
                AND start_time <= ? 
                AND end_time >= ?
                AND (days_of_week LIKE ? OR days_of_week = 'all')
                AND (valid_from IS NULL OR valid_from <= CURDATE())
                AND (valid_until IS NULL OR valid_until >= CURDATE())
            ", [$now, $now, '%' . $today . '%']);
            
            echo json_encode(['success' => true, 'happy_hour' => $activeHH]);
            break;
            
        case 'get_all':
            $rules = $db->fetchAll("SELECT * FROM happy_hour_rules ORDER BY start_time");
            echo json_encode(['success' => true, 'rules' => $rules]);
            break;
            
        case 'check_product':
            $productId = (int)($_GET['product_id'] ?? 0);
            $now = date('H:i:s');
            $today = strtolower(date('l'));
            
            // Check if product has active happy hour
            $discount = $db->fetchOne("
                SELECT hh.*, hhp.special_price
                FROM happy_hour_rules hh
                LEFT JOIN happy_hour_products hhp ON hh.id = hhp.happy_hour_id AND hhp.product_id = ?
                LEFT JOIN happy_hour_categories hhc ON hh.id = hhc.happy_hour_id
                LEFT JOIN products p ON p.id = ?
                WHERE hh.is_active = 1 
                AND hh.start_time <= ? 
                AND hh.end_time >= ?
                AND (hh.days_of_week LIKE ? OR hh.days_of_week = 'all')
                AND (
                    hhp.product_id IS NOT NULL 
                    OR hhc.category_id = p.category_id
                    OR (SELECT COUNT(*) FROM happy_hour_products WHERE happy_hour_id = hh.id) = 0 
                       AND (SELECT COUNT(*) FROM happy_hour_categories WHERE happy_hour_id = hh.id) = 0
                )
                LIMIT 1
            ", [$productId, $productId, $now, $now, '%' . $today . '%']);
            
            echo json_encode(['success' => true, 'discount' => $discount]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// POST requests - require manager role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($userRole, ['admin', 'manager', 'super_admin', 'developer'])) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_happy_hour':
                $days = is_array($input['days']) ? implode(',', $input['days']) : 'all';
                
                $db->insert('happy_hour_rules', [
                    'name' => $input['name'],
                    'description' => $input['description'] ?? null,
                    'start_time' => $input['start_time'],
                    'end_time' => $input['end_time'],
                    'days_of_week' => $days,
                    'discount_type' => $input['discount_type'] ?? 'percent',
                    'discount_percent' => $input['discount_percent'] ?? 0,
                    'display_message' => $input['description'] ?? null,
                    'created_by' => $userId,
                    'is_active' => 1
                ]);
                
                $hhId = $db->lastInsertId();
                
                // Add categories
                if (!empty($input['categories'])) {
                    foreach ($input['categories'] as $catId) {
                        $db->insert('happy_hour_categories', [
                            'happy_hour_id' => $hhId,
                            'category_id' => (int)$catId
                        ]);
                    }
                }
                
                // Add products
                if (!empty($input['products'])) {
                    foreach ($input['products'] as $prodId) {
                        $db->insert('happy_hour_products', [
                            'happy_hour_id' => $hhId,
                            'product_id' => (int)$prodId
                        ]);
                    }
                }
                
                echo json_encode(['success' => true, 'id' => $hhId, 'message' => 'Happy hour created']);
                break;
                
            case 'toggle':
                $id = (int)($input['id'] ?? 0);
                $active = $input['active'] ? 1 : 0;
                
                $db->update('happy_hour_rules', ['is_active' => $active], 'id = ?', [$id]);
                echo json_encode(['success' => true, 'message' => 'Updated']);
                break;
                
            case 'delete':
                $id = (int)($input['id'] ?? 0);
                $db->delete('happy_hour_rules', 'id = ?', [$id]);
                echo json_encode(['success' => true, 'message' => 'Deleted']);
                break;
                
            case 'log_usage':
                $db->insert('happy_hour_usage', [
                    'happy_hour_id' => $input['happy_hour_id'],
                    'sale_id' => $input['sale_id'] ?? null,
                    'tab_id' => $input['tab_id'] ?? null,
                    'customer_id' => $input['customer_id'] ?? null,
                    'discount_applied' => $input['discount_applied'],
                    'original_amount' => $input['original_amount'],
                    'final_amount' => $input['final_amount']
                ]);
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Happy Hour API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
