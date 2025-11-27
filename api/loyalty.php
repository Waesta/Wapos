<?php
require_once '../includes/bootstrap.php';

use App\Services\LoyaltyService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_REQUEST;
}

$action = isset($input['action']) ? strtolower(trim((string)$input['action'])) : '';
if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $loyaltyService = new LoyaltyService($pdo);

    switch ($action) {
        case 'link':
            $identifier = trim((string)($input['identifier'] ?? ''));
            if ($identifier === '') {
                throw new RuntimeException('Loyalty identifier is required.');
            }

            $createIfMissing = filter_var($input['create_if_missing'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $createIfMissing = $createIfMissing !== false;

            $customer = $loyaltyService->findCustomerByIdentifier($identifier);
            if (!$customer && $createIfMissing) {
                $customer = $loyaltyService->createCustomer([
                    'name' => preg_match('/^[\d\+]+$/', $identifier) ? 'Loyalty Customer' : $identifier,
                    'phone' => preg_match('/^[\d\+]+$/', $identifier) ? $identifier : null,
                    'email' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : null,
                ]);
            }

            if (!$customer) {
                throw new RuntimeException('Customer not found for the provided identifier.');
            }

            $program = $loyaltyService->getActiveProgram() ?? $loyaltyService->ensureDefaultProgram();
            $enrollment = $loyaltyService->ensureEnrollment((int)$customer['id'], (int)$program['id']);

            $historyStmt = $pdo->prepare(
                "SELECT lt.id, lt.transaction_type, lt.points, lt.description, lt.created_at, s.sale_number
                 FROM loyalty_transactions lt
                 LEFT JOIN sales s ON lt.sale_id = s.id
                 WHERE lt.customer_id = :customer AND lt.program_id = :program
                 ORDER BY lt.created_at DESC
                 LIMIT 10"
            );
            $historyStmt->execute([
                ':customer' => $customer['id'],
                ':program' => $program['id'],
            ]);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'customer' => $customer,
                'program' => $program,
                'enrollment' => $enrollment,
                'history' => $history,
            ]);
            break;

        case 'preview':
            $customerId = (int)($input['customer_id'] ?? 0);
            $points = (int)($input['points'] ?? 0);
            $programId = isset($input['program_id']) ? (int)$input['program_id'] : null;

            if ($customerId <= 0 || $points <= 0) {
                throw new RuntimeException('customer_id and points are required for redemption preview.');
            }

            $program = $programId ? $loyaltyService->getProgramById($programId) : null;
            if (!$program) {
                $program = $loyaltyService->getActiveProgram() ?? $loyaltyService->ensureDefaultProgram();
            }

            $result = $loyaltyService->validateRedemption($customerId, (int)$program['id'], $points);
            echo json_encode([
                'success' => true,
                'program' => $program,
                'preview' => $result,
            ]);
            break;

        case 'history':
            $customerId = (int)($input['customer_id'] ?? 0);
            $programId = isset($input['program_id']) ? (int)$input['program_id'] : null;
            if ($customerId <= 0) {
                throw new RuntimeException('customer_id is required for history.');
            }

            $program = $programId ? $loyaltyService->getProgramById($programId) : null;
            if (!$program) {
                $program = $loyaltyService->getActiveProgram() ?? $loyaltyService->ensureDefaultProgram();
            }

            $historyStmt = $pdo->prepare(
                "SELECT lt.id, lt.transaction_type, lt.points, lt.description, lt.created_at, s.sale_number
                 FROM loyalty_transactions lt
                 LEFT JOIN sales s ON lt.sale_id = s.id
                 WHERE lt.customer_id = :customer AND lt.program_id = :program
                 ORDER BY lt.created_at DESC
                 LIMIT 25"
            );
            $historyStmt->execute([
                ':customer' => $customerId,
                ':program' => $program['id'],
            ]);

            echo json_encode([
                'success' => true,
                'program' => $program,
                'history' => $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown loyalty action.']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
