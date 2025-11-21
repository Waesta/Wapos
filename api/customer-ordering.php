<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unsupported request method']);
    exit;
}

if (!$auth->isLoggedIn() && !($auth->hasRole('admin') || $auth->hasRole('manager') || $auth->hasRole('waiter'))) {
    // Customer portal can be anonymous; roles only enforced for backend operations
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $payload['action'] ?? '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    switch ($action) {
        case 'list_menu':
            require_once 'customer-ordering/list-menu.php';
            break;
        case 'quote':
            require_once 'customer-ordering/quote.php';
            break;
        case 'place_order':
            require_once 'customer-ordering/place-order.php';
            break;
        case 'status':
            require_once 'customer-ordering/status.php';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    error_log('Customer ordering API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
