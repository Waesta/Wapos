<?php
require_once '../includes/bootstrap.php';

use App\Services\SystemBackupService;

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['super_admin', 'developer', 'admin', 'accountant'];
if (!$auth->hasRole($allowedRoles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$backupService = new SystemBackupService($pdo);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === 'download') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo 'Missing backup id';
        exit;
    }

    $record = $backupService->getBackupById($id);
    if (!$record) {
        http_response_code(404);
        echo 'Backup not found';
        exit;
    }

    $fullPath = rtrim($record['storage_path'], "\\/") . DIRECTORY_SEPARATOR . $record['backup_file'];
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo 'Backup file missing on disk';
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($record['backup_file']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($fullPath);
    exit;
}

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload = [];
if ($rawInput !== false && $rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (empty($payload)) {
    $payload = $_POST;
}

function ensureCsrfValid(array $payload): void
{
    if (!validateCSRFToken($payload['csrf_token'] ?? '')) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh and try again.']);
        exit;
    }
}

switch ($action) {
    case 'run':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Unsupported method']);
            exit;
        }
        ensureCsrfValid($payload);

        try {
            $result = $backupService->runBackup(['backup_type' => 'manual'], (int)$auth->getUserId());
            echo json_encode([
                'success' => true,
                'message' => 'Backup completed successfully.',
                'backup' => [
                    'id' => $result['id'],
                    'file' => basename((string)($result['file'] ?? '')),
                    'size' => $result['size'] ?? 0,
                ],
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Unsupported method']);
            exit;
        }
        ensureCsrfValid($payload);
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid backup id']);
            exit;
        }

        $record = $backupService->getBackupById($id);
        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Backup not found']);
            exit;
        }

        if ($backupService->deleteBackup($id)) {
            echo json_encode(['success' => true, 'message' => 'Backup removed successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete backup record.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
}
