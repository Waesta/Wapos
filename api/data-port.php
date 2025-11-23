<?php

require_once '../includes/bootstrap.php';

use App\Services\DataPortService;

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
$service = new DataPortService($db->getConnection());

$action = strtolower($_GET['action'] ?? $_POST['action'] ?? '');
$entity = strtolower($_GET['entity'] ?? $_POST['entity'] ?? '');

if (!in_array($action, ['template', 'export', 'import'], true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
    exit;
}

try {
    if ($action === 'import') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh.']);
            exit;
        }

        if (!$entity) {
            throw new RuntimeException('Missing entity parameter.');
        }

        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please select a valid CSV file to upload.');
        }

        $tmpPath = $_FILES['file']['tmp_name'];
        $mode = strtolower($_POST['mode'] ?? 'validate');
        $validateOnly = $mode !== 'import';

        $result = $service->importFromCsv($entity, $tmpPath, $validateOnly);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if (!$entity) {
        throw new RuntimeException('Missing entity parameter.');
    }

    $timestamp = date('Ymd_His');
    $filenameBase = $entity . '_' . $timestamp;
    $output = fopen('php://output', 'wb');
    if (!$output) {
        throw new RuntimeException('Unable to open output stream.');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if ($action === 'template') {
        $service->streamTemplate($entity, $output);
    } else {
        $service->streamExport($entity, $output);
    }
    fclose($output);
    exit;
} catch (Throwable $e) {
    if ($action === 'template' || $action === 'export') {
        if (!headers_sent()) {
            header('Content-Type: application/json', true, 400);
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
