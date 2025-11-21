<?php
require_once '../includes/bootstrap.php';

use App\Services\HousekeepingService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'manager', 'housekeeping_manager', 'housekeeping_staff', 'frontdesk'];
$authorized = false;
foreach ($allowedRoles as $role) {
    if ($auth->hasRole($role)) {
        $authorized = true;
        break;
    }
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$service = new HousekeepingService($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    try {
        switch ($action) {
            case 'list':
                $filters = [];
                if (!empty($_GET['status'])) {
                    $filters['status'] = $_GET['status'];
                }
                if (!empty($_GET['scheduled_date'])) {
                    $filters['scheduled_date'] = $_GET['scheduled_date'];
                }
                if (isset($_GET['assigned_to']) && $_GET['assigned_to'] !== '') {
                    $filters['assigned_to'] = (int)$_GET['assigned_to'];
                }
                if (isset($_GET['room_id']) && $_GET['room_id'] !== '') {
                    $filters['room_id'] = (int)$_GET['room_id'];
                }
                if (!empty($_GET['limit'])) {
                    $filters['limit'] = (int)$_GET['limit'];
                }

                $tasks = $service->getTasks($filters);
                echo json_encode(['success' => true, 'tasks' => $tasks]);
                return;

            case 'logs':
                $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
                if ($taskId <= 0) {
                    throw new Exception('task_id is required.');
                }
                $logs = $service->getTaskLogs($taskId);
                echo json_encode(['success' => true, 'logs' => $logs]);
                return;

            case 'summary':
                $summary = $service->getDashboardSummary();
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

$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    if (empty($input)) {
        $input = $_POST;
    }

    if (!empty($input['action'])) {
        $action = $input['action'];
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
            $task = $service->createTask($input, (int)$auth->getUserId());
            echo json_encode(['success' => true, 'task' => $task, 'message' => 'Task created successfully.']);
            return;

        case 'update':
            $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
            if ($taskId <= 0) {
                throw new Exception('task_id is required.');
            }
            $task = $service->updateTask($taskId, $input, (int)$auth->getUserId());
            echo json_encode(['success' => true, 'task' => $task, 'message' => 'Task updated successfully.']);
            return;

        case 'update_status':
            $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
            $status = $input['status'] ?? '';
            if ($taskId <= 0 || $status === '') {
                throw new Exception('task_id and status are required.');
            }
            $task = $service->updateStatus($taskId, $status, $input['notes'] ?? null, (int)$auth->getUserId());
            echo json_encode(['success' => true, 'task' => $task, 'message' => 'Status updated successfully.']);
            return;

        case 'assign':
            $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
            if ($taskId <= 0) {
                throw new Exception('task_id is required.');
            }
            $assignee = ($input['assigned_to'] ?? '') === '' ? null : (int)$input['assigned_to'];
            $task = $service->assignTask($taskId, $assignee, (int)$auth->getUserId(), $input['notes'] ?? null);
            echo json_encode(['success' => true, 'task' => $task, 'message' => 'Assignment updated successfully.']);
            return;

        default:
            throw new Exception('Unsupported action.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    return;
}
