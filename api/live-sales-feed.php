<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();

$afterId = isset($_GET['after_id']) ? max(0, (int) $_GET['after_id']) : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
$limit = max(1, min(20, $limit));

try {
    if ($afterId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, sale_number, total_amount, payment_method, created_at
             FROM sales
             WHERE id > ?
             ORDER BY id ASC
             LIMIT ?'
        );
        $stmt->execute([$afterId, $limit]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, sale_number, total_amount, payment_method, created_at
             FROM sales
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        $sales = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $latestIdRow = $pdo->query('SELECT id FROM sales ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $latestId = $latestIdRow ? (int) $latestIdRow['id'] : 0;

    $todayStatsStmt = $pdo->query(
        "SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS total_amount
         FROM sales
         WHERE DATE(created_at) = CURDATE()"
    );
    $todayStats = $todayStatsStmt ? $todayStatsStmt->fetch(PDO::FETCH_ASSOC) : ['sale_count' => 0, 'total_amount' => 0];

    echo json_encode([
        'success' => true,
        'sales' => array_map(static function ($row) {
            return [
                'id' => (int) $row['id'],
                'sale_number' => $row['sale_number'],
                'total_amount' => isset($row['total_amount']) ? (float) $row['total_amount'] : null,
                'payment_method' => $row['payment_method'],
                'created_at' => $row['created_at'],
            ];
        }, $sales),
        'latest_id' => $latestId,
        'today' => [
            'count' => (int) ($todayStats['sale_count'] ?? 0),
            'total_amount' => (float) ($todayStats['total_amount'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load live sales feed',
        'error' => $e->getMessage(),
    ]);
}
