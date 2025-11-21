<?php
require_once __DIR__ . '/../includes/bootstrap.php';

use App\Services\RegisterReportService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Allowed roles: admin, manager, cashier, developer
$role = strtolower($auth->getRole() ?? '');
if (!in_array($role, ['admin', 'manager', 'cashier', 'developer'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$token = $payload['csrf_token'] ?? '';
if (!validateCSRFToken($token)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $payload['action'] ?? '';
if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$reportService = new RegisterReportService($pdo);

try {
    $userId = (int)$auth->getUserId();
    $locationId = array_key_exists('location_id', $payload) ? (int)$payload['location_id'] : null;
    $response = null;

    switch ($action) {
        case 'open_session':
            $openingAmount = isset($payload['opening_amount']) ? (float)$payload['opening_amount'] : 0.0;
            $note = $payload['note'] ?? null;
            $session = $reportService->openSession($userId, $openingAmount, $note, $locationId);
            $response = ['success' => true, 'session' => $session];
            break;

        case 'close_session':
            $sessionId = (int)($payload['session_id'] ?? 0);
            if ($sessionId <= 0) {
                throw new Exception('Invalid session identifier');
            }
            $closingAmount = isset($payload['closing_amount']) ? (float)$payload['closing_amount'] : null;
            $note = $payload['note'] ?? null;
            $session = $reportService->closeSession($sessionId, $userId, $closingAmount, $note);
            $response = ['success' => true, 'session' => $session];
            break;

        case 'list_sessions':
            $status = $payload['status'] ?? null;
            $limit = isset($payload['limit']) ? max(1, min(100, (int)$payload['limit'])) : 25;
            $sessions = $reportService->listSessions($status, $locationId, $limit);
            $response = ['success' => true, 'sessions' => $sessions];
            break;

        case 'list_closures':
            $limit = isset($payload['limit']) ? max(1, min(100, (int)$payload['limit'])) : 25;
            $closures = $reportService->getRecentClosures($limit, $locationId);
            $response = ['success' => true, 'closures' => $closures];
            break;

        case 'generate_x':
            $report = $reportService->generateXReport($locationId);
            $response = ['success' => true, 'report' => $report];
            break;

        case 'generate_y':
            $sessionId = (int)($payload['session_id'] ?? 0);
            if ($sessionId <= 0) {
                throw new Exception('session_id is required for Y report');
            }
            $report = $reportService->generateYReport($sessionId);
            $response = ['success' => true, 'report' => $report];
            break;

        case 'generate_z':
            $finalize = !empty($payload['finalize']);
            $report = $reportService->generateZReport($userId, $locationId, $finalize);
            $response = ['success' => true, 'report' => $report];
            break;

        case 'record_closure':
            $type = strtoupper((string)($payload['closure_type'] ?? ''));
            if (!in_array($type, ['X', 'Y', 'Z'], true)) {
                throw new Exception('Invalid closure type');
            }
            $report = $payload['report'] ?? null;
            if (!$report || !is_array($report)) {
                throw new Exception('Report payload required for manual closure');
            }
            $session = $payload['session'] ?? null;
            $reset = !empty($payload['reset']);
            $closureId = $reportService->recordClosure($type, $report, $session, $locationId, $userId, $reset);
            $response = ['success' => true, 'closure_id' => $closureId];
            break;

        default:
            throw new Exception('Unsupported action');
    }

    http_response_code(200);
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
