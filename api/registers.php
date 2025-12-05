<?php
/**
 * Registers/Tills API
 * Manage POS terminals within locations
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($db) {
    $locationId = $_GET['location_id'] ?? null;
    $registerId = $_GET['id'] ?? null;
    
    if ($registerId) {
        // Get single register
        $stmt = $db->prepare("
            SELECT r.*, l.name as location_name
            FROM registers r
            JOIN locations l ON r.location_id = l.id
            WHERE r.id = ?
        ");
        $stmt->execute([$registerId]);
        $register = $stmt->fetch();
        
        if ($register) {
            echo json_encode(['success' => true, 'data' => $register]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Register not found']);
        }
    } else {
        // Get all registers
        $sql = "
            SELECT r.*, l.name as location_name,
                   (SELECT COUNT(*) FROM register_sessions WHERE register_id = r.id AND status = 'open') as has_open_session
            FROM registers r
            JOIN locations l ON r.location_id = l.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($locationId) {
            $sql .= " AND r.location_id = ?";
            $params[] = $locationId;
        }
        
        $sql .= " ORDER BY l.name, r.register_number";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $registers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $registers]);
    }
}

function handlePost($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['location_id', 'register_number', 'name', 'register_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Check for duplicate register number in location
    $stmt = $db->prepare("SELECT id FROM registers WHERE location_id = ? AND register_number = ?");
    $stmt->execute([$data['location_id'], $data['register_number']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register number already exists in this location']);
        return;
    }
    
    // Insert register
    $stmt = $db->prepare("
        INSERT INTO registers (location_id, register_number, name, register_type, description, opening_balance, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([
        $data['location_id'],
        $data['register_number'],
        $data['name'],
        $data['register_type'],
        $data['description'] ?? null,
        $data['opening_balance'] ?? 0
    ]);
    
    $registerId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Register created successfully',
        'data' => ['id' => $registerId]
    ]);
}

function handlePut($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register ID required']);
        return;
    }
    
    $updates = [];
    $params = [];
    
    $allowedFields = ['name', 'register_type', 'description', 'opening_balance', 'is_active'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $data['id'];
    $sql = "UPDATE registers SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Register updated successfully']);
}

function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Register ID required']);
        return;
    }
    
    // Check for open sessions
    $stmt = $db->prepare("SELECT COUNT(*) FROM register_sessions WHERE register_id = ? AND status = 'open'");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete register with open session']);
        return;
    }
    
    // Soft delete (deactivate)
    $stmt = $db->prepare("UPDATE registers SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Register deactivated']);
}
