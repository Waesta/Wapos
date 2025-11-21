<?php
require_once '../includes/bootstrap.php';

use App\Services\MaintenanceService;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

$raw = $method === 'POST' ? file_get_contents('php://input') : '';
$input = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

if ($method === 'POST' && empty($input)) {
    $input = $_POST;
}

$action = $_GET['action'] ?? ($input['action'] ?? null);

$publicToken = getenv('MAINTENANCE_PUBLIC_TOKEN') ?: ($_ENV['MAINTENANCE_PUBLIC_TOKEN'] ?? $_SERVER['MAINTENANCE_PUBLIC_TOKEN'] ?? null);
$isGuestCreate = $method === 'POST' && ($action === 'create') && isset($input['public_key']) && $publicToken && hash_equals($publicToken, (string)$input['public_key']);

$isLoggedIn = $auth->isLoggedIn();

if (!$isLoggedIn && !$isGuestCreate) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'manager', 'frontdesk', 'maintenance_manager', 'maintenance_staff', 'maintenance', 'technician', 'engineer'];
$authorized = false;
if ($isLoggedIn) {
    foreach ($allowedRoles as $role) {
        if ($auth->hasRole($role)) {
            $authorized = true;
            break;
        }
    }
}

if (!$authorized && !$isGuestCreate && $method !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$service = new MaintenanceService($pdo);

if ($method === 'GET') {
    try {
        switch ($action) {
            case 'list':
                $filters = [];
                if (!empty($_GET['status'])) {
                    $filters['status'] = $_GET['status'];
                }
                if (!empty($_GET['priority'])) {
                    $filters['priority'] = $_GET['priority'];
                }
                if (isset($_GET['assigned_to']) && $_GET['assigned_to'] !== '') {
                    $filters['assigned_to'] = (int)$_GET['assigned_to'];
                }
                if (isset($_GET['room_id']) && $_GET['room_id'] !== '') {
                    $filters['room_id'] = (int)$_GET['room_id'];
                }
                if (!empty($_GET['reporter_type'])) {
                    $filters['reporter_type'] = $_GET['reporter_type'];
                }
                if (!empty($_GET['limit'])) {
                    $filters['limit'] = (int)$_GET['limit'];
                }

                $requests = $service->getRequests($filters);
                echo json_encode(['success' => true, 'requests' => $requests]);
                return;

            case 'logs':
                $requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
                if ($requestId <= 0) {
                    throw new Exception('request_id is required.');
                }
                $logs = $service->getLogs($requestId);
                echo json_encode(['success' => true, 'logs' => $logs]);
                return;

            case 'summary':
                $summary = $service->getSummary();
                echo json_encode(['success' => true, 'summary' => $summary]);
                return;

            default:
                echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
                return;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        return;
    }
}

if ($method !== 'POST' || !$action) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Unsupported request.']);
    exit;
}

try {
    switch ($action) {
        case 'create':
            unset($input['public_key']);
            if ($isGuestCreate && empty($input['reporter_type'])) {
                $input['reporter_type'] = 'guest';
            }
            $request = $service->createRequest($input, $isGuestCreate ? null : (int)$auth->getUserId());
            echo json_encode(['success' => true, 'request' => $request, 'message' => 'Maintenance request created successfully.']);
            return;

        case 'update':
            $requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
            if ($requestId <= 0) {
                throw new Exception('request_id is required.');
            }
            $request = $service->updateRequest($requestId, $input);
            echo json_encode(['success' => true, 'request' => $request, 'message' => 'Maintenance request updated successfully.']);
            return;

        case 'update_status':
            $requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
            $status = $input['status'] ?? '';
            if ($requestId <= 0 || $status === '') {
                throw new Exception('request_id and status are required.');
            }
            $request = $service->updateStatus($requestId, $status, $input['notes'] ?? null, (int)$auth->getUserId());
            echo json_encode(['success' => true, 'request' => $request, 'message' => 'Status updated successfully.']);
            return;

        case 'assign':
            $requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;
            if ($requestId <= 0) {
                throw new Exception('request_id is required.');
            }
            $assignee = ($input['assigned_to'] ?? '') === '' ? null : (int)$input['assigned_to'];
            $request = $service->assignRequest($requestId, $assignee, (int)$auth->getUserId(), $input['notes'] ?? null);
            echo json_encode(['success' => true, 'request' => $request, 'message' => 'Assignment updated successfully.']);
            return;

        default:
            throw new Exception('Unsupported action.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    return;
}
