<?php
/**
 * Bar Tabs API
 * Handles tab management, items, payments, and BOT generation
 */

use App\Services\BarTabService;

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$tabService = new BarTabService($pdo);

$userId = $_SESSION['user_id'];

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_tab':
            $tabId = (int)($_GET['tab_id'] ?? 0);
            if (!$tabId) {
                echo json_encode(['success' => false, 'message' => 'Tab ID required']);
                exit;
            }
            $tab = $tabService->getTab($tabId);
            echo json_encode(['success' => true, 'tab' => $tab]);
            break;
            
        case 'get_open_tabs':
            $locationId = $_GET['location_id'] ?? null;
            $station = $_GET['station'] ?? null;
            $waiterId = $_GET['waiter_id'] ?? null;
            $tabs = $tabService->getOpenTabs($locationId, $station, $waiterId);
            echo json_encode(['success' => true, 'tabs' => $tabs]);
            break;
            
        case 'get_pending_bots':
            $station = $_GET['station'] ?? null;
            $bots = $tabService->getPendingBots($station);
            echo json_encode(['success' => true, 'bots' => $bots]);
            break;
            
        case 'get_stations':
            $stations = $tabService->getStations();
            echo json_encode(['success' => true, 'stations' => $stations]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    // CSRF validation
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'open_tab':
                // Use assigned waiter if provided, otherwise use logged-in user
                $serverId = !empty($input['assigned_waiter_id']) ? (int)$input['assigned_waiter_id'] : $userId;
                
                $data = [
                    'tab_name' => $input['tab_name'] ?? 'Guest',
                    'tab_type' => $input['tab_type'] ?? 'name',
                    'customer_id' => $input['customer_id'] ?? null,
                    'room_booking_id' => $input['room_booking_id'] ?? null,
                    'member_id' => $input['member_id'] ?? null,
                    'preauth_amount' => $input['preauth_amount'] ?? null,
                    'preauth_reference' => $input['preauth_reference'] ?? null,
                    'card_last_four' => $input['card_last_four'] ?? null,
                    'card_type' => $input['card_type'] ?? null,
                    'location_id' => $input['location_id'] ?? ($_SESSION['location_id'] ?? null),
                    'bar_station' => $input['bar_station'] ?? 'Main Bar',
                    'table_id' => $input['table_id'] ?? null,
                    'server_id' => $serverId,
                    'opened_by' => $userId, // Track who actually opened the tab (cashier)
                    'guest_count' => $input['guest_count'] ?? 1,
                    'notes' => $input['notes'] ?? null
                ];
                
                $tab = $tabService->openTab($data);
                echo json_encode(['success' => true, 'tab' => $tab, 'message' => 'Tab opened successfully']);
                break;
                
            case 'add_item':
                $tabId = (int)($input['tab_id'] ?? 0);
                if (!$tabId) {
                    echo json_encode(['success' => false, 'message' => 'Tab ID required']);
                    exit;
                }
                
                $item = [
                    'product_id' => $input['product_id'] ?? null,
                    'portion_id' => $input['portion_id'] ?? null,
                    'recipe_id' => $input['recipe_id'] ?? null,
                    'item_name' => $input['item_name'],
                    'portion_name' => $input['portion_name'] ?? null,
                    'unit_price' => (float)$input['unit_price'],
                    'quantity' => (int)($input['quantity'] ?? 1),
                    'modifiers' => $input['modifiers'] ?? null,
                    'special_instructions' => $input['special_instructions'] ?? null,
                    'send_to_bar' => $input['send_to_bar'] ?? false,
                    'added_by' => $userId
                ];
                
                $result = $tabService->addItem($tabId, $item);
                echo json_encode(['success' => true, 'item' => $result, 'message' => 'Item added']);
                break;
                
            case 'void_item':
                $itemId = (int)($input['item_id'] ?? 0);
                $reason = $input['reason'] ?? 'No reason provided';
                
                if (!$itemId) {
                    echo json_encode(['success' => false, 'message' => 'Item ID required']);
                    exit;
                }
                
                $tabService->voidItem($itemId, $userId, $reason);
                echo json_encode(['success' => true, 'message' => 'Item voided']);
                break;
                
            case 'transfer_tab':
                $tabId = (int)($input['tab_id'] ?? 0);
                $fromWaiterId = (int)($input['from_waiter_id'] ?? 0);
                $toWaiterId = (int)($input['to_waiter_id'] ?? 0);
                $reason = $input['reason'] ?? null;
                
                if (!$tabId || !$toWaiterId) {
                    echo json_encode(['success' => false, 'message' => 'Tab ID and target waiter required']);
                    exit;
                }
                
                $tabService->transferTab($tabId, $fromWaiterId, $toWaiterId, $reason);
                echo json_encode(['success' => true, 'message' => 'Tab transferred successfully']);
                break;
                
            case 'apply_discount':
                $tabId = (int)($input['tab_id'] ?? 0);
                $amount = (float)($input['amount'] ?? 0);
                $reason = $input['reason'] ?? null;
                
                $tabService->applyDiscount($tabId, $amount, $reason);
                echo json_encode(['success' => true, 'message' => 'Discount applied']);
                break;
                
            case 'add_tip':
                $tabId = (int)($input['tab_id'] ?? 0);
                $amount = (float)($input['amount'] ?? 0);
                
                $tabService->addTip($tabId, $amount);
                echo json_encode(['success' => true, 'message' => 'Tip added']);
                break;
                
            case 'process_payment':
                $tabId = (int)($input['tab_id'] ?? 0);
                if (!$tabId) {
                    echo json_encode(['success' => false, 'message' => 'Tab ID required']);
                    exit;
                }
                
                $payment = [
                    'method' => $input['method'] ?? 'cash',
                    'amount' => (float)($input['amount'] ?? 0),
                    'tip_amount' => (float)($input['tip_amount'] ?? 0),
                    'reference' => $input['reference'] ?? null,
                    'card_last_four' => $input['card_last_four'] ?? null,
                    'phone_number' => $input['phone_number'] ?? null,
                    'room_number' => $input['room_number'] ?? null,
                    'split_guest_name' => $input['split_guest_name'] ?? null,
                    'split_portion_percent' => $input['split_portion_percent'] ?? null,
                    'processed_by' => $userId
                ];
                
                // Handle room charge
                if ($payment['method'] === 'room_charge' && !empty($input['room_booking_id'])) {
                    $tabService->transferToRoom($tabId, (int)$input['room_booking_id'], $userId);
                    echo json_encode(['success' => true, 'message' => 'Charged to room']);
                    exit;
                }
                
                $result = $tabService->processPayment($tabId, $payment);
                echo json_encode(['success' => true, 'payment' => $result, 'message' => 'Payment processed']);
                break;
                
            case 'transfer_tab':
                $fromTabId = (int)($input['from_tab_id'] ?? 0);
                $toTabId = (int)($input['to_tab_id'] ?? 0);
                $itemIds = $input['item_ids'] ?? null;
                
                $tabService->transferToTab($fromTabId, $toTabId, $userId, $itemIds);
                echo json_encode(['success' => true, 'message' => 'Transfer completed']);
                break;
                
            case 'transfer_to_room':
                $tabId = (int)($input['tab_id'] ?? 0);
                $roomBookingId = (int)($input['room_booking_id'] ?? 0);
                
                $tabService->transferToRoom($tabId, $roomBookingId, $userId);
                echo json_encode(['success' => true, 'message' => 'Charged to room']);
                break;
                
            case 'close_tab':
                $tabId = (int)($input['tab_id'] ?? 0);
                $status = $input['status'] ?? 'paid';
                
                $tabService->closeTab($tabId, $status);
                echo json_encode(['success' => true, 'message' => 'Tab closed']);
                break;
                
            case 'create_bot':
                $tabId = (int)($input['tab_id'] ?? 0);
                $itemIds = $input['item_ids'] ?? [];
                $station = $input['station'] ?? 'Main Bar';
                
                if (empty($itemIds)) {
                    echo json_encode(['success' => false, 'message' => 'No items to send']);
                    exit;
                }
                
                $result = $tabService->createBot($tabId, $itemIds, $station, $userId);
                echo json_encode(['success' => true, 'bot_number' => $result['bot_number'], 'message' => 'BOT created']);
                break;
                
            case 'update_bot_status':
                $botId = (int)($input['bot_id'] ?? 0);
                $status = $input['status'] ?? '';
                
                $tabService->updateBotStatus($botId, $status, $userId);
                echo json_encode(['success' => true, 'message' => 'BOT status updated']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Bar Tabs API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
