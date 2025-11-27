<?php
require_once '../includes/bootstrap.php';

use App\Services\RestaurantReservationService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'manager', 'waiter'];
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
$service = new RestaurantReservationService($pdo);

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
                if (!empty($_GET['table_id'])) {
                    $filters['table_id'] = (int)$_GET['table_id'];
                }
                if (!empty($_GET['date'])) {
                    $filters['date'] = $_GET['date'];
                }
                if (!empty($_GET['from_date'])) {
                    $filters['from_date'] = $_GET['from_date'];
                }
                if (!empty($_GET['to_date'])) {
                    $filters['to_date'] = $_GET['to_date'];
                }
                if (!empty($_GET['limit'])) {
                    $filters['limit'] = (int)$_GET['limit'];
                }
                $reservations = $service->getReservations($filters);
                echo json_encode(['success' => true, 'reservations' => $reservations]);
                return;

            case 'summary':
                $date = $_GET['date'] ?? date('Y-m-d');
                $summary = $service->getSummary($date);
                echo json_encode(['success' => true, 'summary' => $summary]);
                return;

            case 'availability':
                $date = $_GET['date'] ?? date('Y-m-d');
                $availability = $service->getTableAvailability($date);
                echo json_encode(['success' => true, 'availability' => $availability]);
                return;

            case 'get':
                $reservationId = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : 0;
                if ($reservationId <= 0) {
                    throw new Exception('reservation_id is required.');
                }
                $reservation = $service->getReservation($reservationId);
                if (!$reservation) {
                    throw new Exception('Reservation not found.');
                }
                echo json_encode(['success' => true, 'reservation' => $reservation]);
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
            $reservation = $service->createReservation($input, (int)$auth->getUserId());
            echo json_encode(['success' => true, 'reservation' => $reservation, 'message' => 'Reservation created successfully.']);
            return;

        case 'update':
            $reservationId = isset($input['reservation_id']) ? (int)$input['reservation_id'] : 0;
            if ($reservationId <= 0) {
                throw new Exception('reservation_id is required.');
            }
            $reservation = $service->updateReservation($reservationId, $input);
            echo json_encode(['success' => true, 'reservation' => $reservation, 'message' => 'Reservation updated successfully.']);
            return;

        case 'update_status':
            $reservationId = isset($input['reservation_id']) ? (int)$input['reservation_id'] : 0;
            $status = $input['status'] ?? '';
            if ($reservationId <= 0 || $status === '') {
                throw new Exception('reservation_id and status are required.');
            }
            $reservation = $service->updateStatus($reservationId, $status, $input['notes'] ?? null, (int)$auth->getUserId());
            echo json_encode(['success' => true, 'reservation' => $reservation, 'message' => 'Status updated successfully.']);
            return;

        case 'record_deposit_payment':
            $reservationId = isset($input['reservation_id']) ? (int)$input['reservation_id'] : 0;
            if ($reservationId <= 0) {
                throw new Exception('reservation_id is required.');
            }

            $paymentPayload = [
                'amount' => $input['amount'] ?? null,
                'method' => $input['method'] ?? null,
                'reference' => $input['reference'] ?? null,
                'notes' => $input['notes'] ?? null,
            ];

            $reservation = $service->recordDepositPayment($reservationId, $paymentPayload, (int)$auth->getUserId());
            echo json_encode([
                'success' => true,
                'reservation' => $reservation,
                'message' => 'Deposit payment recorded successfully.',
            ]);
            return;

        default:
            throw new Exception('Unsupported action.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    return;
}
