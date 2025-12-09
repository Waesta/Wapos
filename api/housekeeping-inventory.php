<?php
/**
 * Housekeeping Inventory API
 * 
 * Handles CRUD operations for housekeeping inventory
 */

use App\Services\HousekeepingInventoryService;

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    $auth->requireRole(['admin', 'manager', 'housekeeping_manager', 'housekeeping_staff']);
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$hkInventory = new HousekeepingInventoryService($pdo);
$hkInventory->ensureSchema();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $auth->getUserId();
$userRole = strtolower($auth->getRole() ?? '');
$canManage = in_array($userRole, ['admin', 'manager', 'housekeeping_manager']);

// Verify CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($action) {
        case 'add_item':
            if (!$canManage) {
                throw new Exception('Permission denied');
            }
            
            $data = [
                'section' => $_POST['section'] ?? '',
                'item_name' => $_POST['item_name'] ?? '',
                'item_code' => $_POST['item_code'] ?? null,
                'description' => $_POST['description'] ?? null,
                'unit' => $_POST['unit'] ?? 'pcs',
                'quantity_on_hand' => (float)($_POST['quantity_on_hand'] ?? 0),
                'reorder_level' => (float)($_POST['reorder_level'] ?? 0),
                'cost_price' => (float)($_POST['cost_price'] ?? 0),
                'supplier' => $_POST['supplier'] ?? null,
                'location' => $_POST['location'] ?? null
            ];
            
            if (empty($data['section']) || empty($data['item_name'])) {
                throw new Exception('Section and item name are required');
            }
            
            $id = $hkInventory->addInventoryItem($data);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'update_item':
            if (!$canManage) {
                throw new Exception('Permission denied');
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception('Item ID required');
            }
            
            $data = [];
            $allowedFields = ['item_name', 'item_code', 'description', 'unit', 'reorder_level', 'cost_price', 'supplier', 'location', 'section'];
            foreach ($allowedFields as $field) {
                if (isset($_POST[$field])) {
                    $data[$field] = $_POST[$field];
                }
            }
            
            $hkInventory->updateInventoryItem($id, $data);
            echo json_encode(['success' => true]);
            break;

        case 'adjust_stock':
            if (!$canManage) {
                throw new Exception('Permission denied');
            }
            
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            $quantity = (float)($_POST['quantity'] ?? 0);
            $type = $_POST['type'] ?? 'adjustment';
            $notes = $_POST['notes'] ?? null;
            
            if (!$inventoryId || $quantity <= 0) {
                throw new Exception('Valid inventory ID and quantity required');
            }
            
            $validTypes = ['receipt', 'issue', 'adjustment', 'transfer', 'damage', 'return'];
            if (!in_array($type, $validTypes)) {
                $type = 'adjustment';
            }
            
            $hkInventory->adjustInventory($inventoryId, $quantity, $type, $userId, $notes);
            echo json_encode(['success' => true]);
            break;

        case 'get_inventory':
            $section = $_GET['section'] ?? null;
            if ($section && $section !== 'all') {
                $items = $hkInventory->getInventoryBySection($section);
            } else {
                $items = $hkInventory->getAllInventory();
            }
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'get_low_stock':
            $items = $hkInventory->getLowStockItems();
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'get_linen_summary':
            $summary = $hkInventory->getLinenSummary();
            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        case 'get_linen_by_status':
            $status = $_GET['status'] ?? 'clean';
            $items = $hkInventory->getLinenByStatus($status);
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'add_linen':
            if (!$canManage) {
                throw new Exception('Permission denied');
            }
            
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            $linenCode = $_POST['linen_code'] ?? null;
            $acquiredDate = $_POST['acquired_date'] ?? null;
            
            if (!$inventoryId) {
                throw new Exception('Inventory ID required');
            }
            
            $id = $hkInventory->addLinenItem($inventoryId, $linenCode, $acquiredDate);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'update_linen_status':
            $linenId = (int)($_POST['linen_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            $notes = $_POST['notes'] ?? null;
            
            if (!$linenId || !$status) {
                throw new Exception('Linen ID and status required');
            }
            
            $hkInventory->updateLinenStatus($linenId, $status, $roomId, $notes);
            echo json_encode(['success' => true]);
            break;

        case 'create_laundry_batch':
            if (!$canManage) {
                throw new Exception('Permission denied');
            }
            
            $linenIds = $_POST['linen_ids'] ?? [];
            if (is_string($linenIds)) {
                $linenIds = json_decode($linenIds, true) ?: [];
            }
            $notes = $_POST['notes'] ?? null;
            
            if (empty($linenIds)) {
                throw new Exception('At least one linen item required');
            }
            
            $batchId = $hkInventory->createLaundryBatch($linenIds, $userId, $notes);
            echo json_encode(['success' => true, 'batch_id' => $batchId]);
            break;

        case 'update_laundry_batch':
            if (!$canManage) {
                throw new Exception('Permission denied');
            }
            
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            if (!$batchId || !$status) {
                throw new Exception('Batch ID and status required');
            }
            
            $hkInventory->updateLaundryBatchStatus($batchId, $status);
            echo json_encode(['success' => true]);
            break;

        case 'get_laundry_batches':
            $status = $_GET['status'] ?? null;
            $batches = $hkInventory->getLaundryBatches($status);
            echo json_encode(['success' => true, 'batches' => $batches]);
            break;

        case 'record_minibar':
            $roomId = (int)($_POST['room_id'] ?? 0);
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);
            $unitPrice = (float)($_POST['unit_price'] ?? 0);
            $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;
            $notes = $_POST['notes'] ?? null;
            
            if (!$roomId || !$inventoryId || $quantity <= 0) {
                throw new Exception('Room ID, inventory ID, and quantity required');
            }
            
            $logId = $hkInventory->recordMinibarConsumption($roomId, $inventoryId, $quantity, $unitPrice, $userId, $bookingId, $notes);
            echo json_encode(['success' => true, 'log_id' => $logId]);
            break;

        case 'get_minibar_consumption':
            $bookingId = (int)($_GET['booking_id'] ?? 0);
            if (!$bookingId) {
                throw new Exception('Booking ID required');
            }
            
            $items = $hkInventory->getMinibarConsumption($bookingId);
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'get_minibar_items':
            $roomTypeId = isset($_GET['room_type_id']) ? (int)$_GET['room_type_id'] : null;
            $items = $hkInventory->getMinibarItems($roomTypeId);
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'get_dashboard':
            $summary = $hkInventory->getDashboardSummary();
            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
