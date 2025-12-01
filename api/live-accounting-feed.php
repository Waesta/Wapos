<?php
require_once '../includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
$limit = max(1, min($limit, 30));

try {
    $summary = $ledgerDataService->getLiveFinancialSnapshot();
    $recentEntries = $ledgerDataService->getRecentLedgerEntries($limit);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'recent_entries' => $recentEntries,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load accounting feed',
        'error' => $e->getMessage(),
    ]);
}
