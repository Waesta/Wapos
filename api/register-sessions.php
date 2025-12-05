<?php
/**
 * Register Sessions API
 * Handle opening/closing of register shifts
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'open':
            openSession($db, $data, $userId);
            break;
        case 'close':
            closeSession($db, $data, $userId);
            break;
        case 'get':
            getSession($db);
            break;
        case 'current':
            getCurrentSession($db, $userId);
            break;
        case 'cash_movement':
            recordCashMovement($db, $data, $userId);
            break;
        case 'history':
            getHistory($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function openSession($db, $data, $userId) {
    $registerId = $data['register_id'] ?? null;
    $openingBalance = $data['opening_balance'] ?? 0;
    
    if (!$registerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register ID required']);
        return;
    }
    
    // Check if register exists and is active
    $stmt = $db->prepare("SELECT * FROM registers WHERE id = ? AND is_active = 1");
    $stmt->execute([$registerId]);
    $register = $stmt->fetch();
    
    if (!$register) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Register not found or inactive']);
        return;
    }
    
    // Check for existing open session
    $stmt = $db->prepare("SELECT id FROM register_sessions WHERE register_id = ? AND status = 'open'");
    $stmt->execute([$registerId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register already has an open session']);
        return;
    }
    
    // Generate session number
    $sessionNumber = 'SES-' . date('Ymd') . '-' . str_pad($registerId, 3, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);
    
    $db->beginTransaction();
    
    try {
        // Create session
        $stmt = $db->prepare("
            INSERT INTO register_sessions 
            (register_id, user_id, session_number, opening_balance, opened_at, status)
            VALUES (?, ?, ?, ?, NOW(), 'open')
        ");
        $stmt->execute([$registerId, $userId, $sessionNumber, $openingBalance]);
        $sessionId = $db->lastInsertId();
        
        // Update register
        $stmt = $db->prepare("
            UPDATE registers 
            SET current_balance = ?, last_opened_at = NOW(), last_opened_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$openingBalance, $userId, $registerId]);
        
        $db->commit();
        
        // Store session in user's session
        $_SESSION['register_id'] = $registerId;
        $_SESSION['register_session_id'] = $sessionId;
        
        echo json_encode([
            'success' => true,
            'message' => 'Session opened successfully',
            'data' => [
                'session_id' => $sessionId,
                'session_number' => $sessionNumber,
                'register_id' => $registerId
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function closeSession($db, $data, $userId) {
    $registerId = $data['register_id'] ?? null;
    $closingBalance = $data['closing_balance'] ?? 0;
    $closingNotes = $data['closing_notes'] ?? '';
    
    if (!$registerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register ID required']);
        return;
    }
    
    // Get open session
    $stmt = $db->prepare("
        SELECT rs.*, r.current_balance
        FROM register_sessions rs
        JOIN registers r ON rs.register_id = r.id
        WHERE rs.register_id = ? AND rs.status = 'open'
    ");
    $stmt->execute([$registerId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No open session found']);
        return;
    }
    
    // Calculate session totals
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END), 0) as card_sales,
            COALESCE(SUM(CASE WHEN payment_method IN ('mobile_money', 'mpesa') THEN total_amount ELSE 0 END), 0) as mobile_sales,
            COALESCE(SUM(total_amount), 0) as total_sales,
            COUNT(*) as transaction_count
        FROM sales
        WHERE session_id = ? AND status != 'voided'
    ");
    $stmt->execute([$session['id']]);
    $salesData = $stmt->fetch();
    
    // Get cash movements
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN movement_type IN ('cash_in', 'float') THEN amount ELSE 0 END), 0) as cash_in,
            COALESCE(SUM(CASE WHEN movement_type IN ('cash_out', 'pickup') THEN amount ELSE 0 END), 0) as cash_out
        FROM register_cash_movements
        WHERE session_id = ?
    ");
    $stmt->execute([$session['id']]);
    $movements = $stmt->fetch();
    
    // Calculate expected balance
    $expectedBalance = $session['opening_balance'] 
        + $salesData['cash_sales'] 
        + $movements['cash_in'] 
        - $movements['cash_out'];
    
    $variance = $closingBalance - $expectedBalance;
    
    $db->beginTransaction();
    
    try {
        // Update session
        $stmt = $db->prepare("
            UPDATE register_sessions SET
                closing_balance = ?,
                expected_balance = ?,
                variance = ?,
                cash_sales = ?,
                card_sales = ?,
                mobile_sales = ?,
                total_sales = ?,
                cash_in = ?,
                cash_out = ?,
                transaction_count = ?,
                closed_at = NOW(),
                status = 'closed',
                closing_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $closingBalance,
            $expectedBalance,
            $variance,
            $salesData['cash_sales'],
            $salesData['card_sales'],
            $salesData['mobile_sales'],
            $salesData['total_sales'],
            $movements['cash_in'],
            $movements['cash_out'],
            $salesData['transaction_count'],
            $closingNotes,
            $session['id']
        ]);
        
        // Update register
        $stmt = $db->prepare("
            UPDATE registers 
            SET current_balance = 0, last_closed_at = NOW(), last_closed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$userId, $registerId]);
        
        $db->commit();
        
        // Clear session
        unset($_SESSION['register_id']);
        unset($_SESSION['register_session_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Session closed successfully',
            'data' => [
                'session_id' => $session['id'],
                'expected_balance' => $expectedBalance,
                'closing_balance' => $closingBalance,
                'variance' => $variance,
                'total_sales' => $salesData['total_sales'],
                'transaction_count' => $salesData['transaction_count']
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function getSession($db) {
    $sessionId = $_GET['id'] ?? null;
    
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    $stmt = $db->prepare("
        SELECT rs.*, r.name as register_name, r.register_number, u.full_name as user_name
        FROM register_sessions rs
        JOIN registers r ON rs.register_id = r.id
        JOIN users u ON rs.user_id = u.id
        WHERE rs.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if ($session) {
        echo json_encode(['success' => true, 'data' => $session]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found']);
    }
}

function getCurrentSession($db, $userId) {
    $registerId = $_GET['register_id'] ?? null;
    
    if ($registerId) {
        // Get session for specific register
        $stmt = $db->prepare("
            SELECT rs.*, r.name as register_name, r.register_number
            FROM register_sessions rs
            JOIN registers r ON rs.register_id = r.id
            WHERE rs.register_id = ? AND rs.status = 'open'
            ORDER BY rs.opened_at DESC
            LIMIT 1
        ");
        $stmt->execute([$registerId]);
    } else {
        // Get user's current session
        $stmt = $db->prepare("
            SELECT rs.*, r.name as register_name, r.register_number
            FROM register_sessions rs
            JOIN registers r ON rs.register_id = r.id
            WHERE rs.user_id = ? AND rs.status = 'open'
            ORDER BY rs.opened_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
    }
    
    $session = $stmt->fetch();
    echo json_encode(['success' => true, 'data' => $session ?: null]);
}

function recordCashMovement($db, $data, $userId) {
    $sessionId = $data['session_id'] ?? $_SESSION['register_session_id'] ?? null;
    $movementType = $data['movement_type'] ?? null;
    $amount = $data['amount'] ?? 0;
    $reason = $data['reason'] ?? '';
    
    if (!$sessionId || !$movementType || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid cash movement data']);
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO register_cash_movements 
        (session_id, movement_type, amount, reason, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $movementType,
        $amount,
        $reason,
        $data['notes'] ?? null,
        $userId
    ]);
    
    // Update register balance
    $multiplier = in_array($movementType, ['cash_in', 'float']) ? 1 : -1;
    $stmt = $db->prepare("
        UPDATE registers r
        JOIN register_sessions rs ON r.id = rs.register_id
        SET r.current_balance = r.current_balance + (? * ?)
        WHERE rs.id = ?
    ");
    $stmt->execute([$amount, $multiplier, $sessionId]);
    
    echo json_encode(['success' => true, 'message' => 'Cash movement recorded']);
}

function getHistory($db) {
    $registerId = $_GET['register_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    
    if (!$registerId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register ID required']);
        return;
    }
    
    $stmt = $db->prepare("
        SELECT rs.*, u.full_name as user_name
        FROM register_sessions rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.register_id = ?
        ORDER BY rs.opened_at DESC
        LIMIT ?
    ");
    $stmt->execute([$registerId, (int)$limit]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $sessions]);
}
