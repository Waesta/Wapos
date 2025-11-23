<?php
require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentRequestStore;

header('Content-Type: application/json');

function respondStatus(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (!$auth->isLoggedIn()) {
    respondStatus(401, ['success' => false, 'message' => 'Unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondStatus(405, ['success' => false, 'message' => 'Invalid request method']);
}

$reference = isset($_GET['reference']) ? trim((string)$_GET['reference']) : '';
$contextType = isset($_GET['context_type']) ? trim((string)$_GET['context_type']) : null;
$contextId = isset($_GET['context_id']) ? (int)$_GET['context_id'] : null;

if ($reference === '' && (!$contextType || !$contextId)) {
    respondStatus(422, [
        'success' => false,
        'message' => 'reference or (context_type + context_id) is required',
    ]);
}

$store = new PaymentRequestStore();
$record = null;

if ($reference !== '') {
    $record = $store->findByReference($reference);
} else {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM payment_requests WHERE context_type = :context_type AND context_id = :context_id ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute([
        ':context_type' => $contextType,
        ':context_id' => $contextId,
    ]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($record && !empty($record['meta'])) {
        $decoded = json_decode($record['meta'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $record['meta'] = $decoded;
        }
    }
}

if (!$record) {
    respondStatus(404, ['success' => false, 'message' => 'Payment request not found']);
}

respondStatus(200, [
    'success' => true,
    'data' => $record,
]);
