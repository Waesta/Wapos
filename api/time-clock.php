<?php
/**
 * Time Clock API
 * Handles clock in/out, breaks, and time tracking
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$locationId = $_SESSION['location_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'clock_in':
            // Check if already clocked in
            $existing = $db->fetchOne("
                SELECT id FROM employee_time_clock 
                WHERE user_id = ? AND status = 'active'
            ", [$userId]);
            
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Already clocked in']);
                exit;
            }
            
            $db->insert('employee_time_clock', [
                'user_id' => $userId,
                'location_id' => $locationId,
                'clock_in_at' => date('Y-m-d H:i:s'),
                'clock_in_note' => $input['note'] ?? null,
                'status' => 'active'
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Clocked in successfully']);
            break;
            
        case 'clock_out':
            $shift = $db->fetchOne("
                SELECT * FROM employee_time_clock 
                WHERE user_id = ? AND status = 'active'
                ORDER BY clock_in_at DESC LIMIT 1
            ", [$userId]);
            
            if (!$shift) {
                echo json_encode(['success' => false, 'message' => 'Not clocked in']);
                exit;
            }
            
            // End any active break
            if ($shift['break_start_at'] && !$shift['break_end_at']) {
                $breakMinutes = (int)((time() - strtotime($shift['break_start_at'])) / 60);
                $totalBreak = ($shift['total_break_minutes'] ?? 0) + $breakMinutes;
            } else {
                $totalBreak = $shift['total_break_minutes'] ?? 0;
            }
            
            // Calculate hours
            $clockIn = strtotime($shift['clock_in_at']);
            $clockOut = time();
            $totalMinutes = ($clockOut - $clockIn) / 60;
            $workMinutes = $totalMinutes - $totalBreak;
            $actualHours = $workMinutes / 60;
            
            // Calculate overtime (over 8 hours)
            $regularHours = min($actualHours, 8);
            $overtimeHours = max(0, $actualHours - 8);
            
            $db->update('employee_time_clock', [
                'clock_out_at' => date('Y-m-d H:i:s'),
                'clock_out_note' => $input['note'] ?? null,
                'break_end_at' => $shift['break_start_at'] && !$shift['break_end_at'] ? date('Y-m-d H:i:s') : $shift['break_end_at'],
                'total_break_minutes' => $totalBreak,
                'actual_hours' => round($actualHours, 2),
                'overtime_hours' => round($overtimeHours, 2),
                'status' => 'completed'
            ], 'id = ?', [$shift['id']]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Clocked out successfully',
                'hours' => round($actualHours, 2),
                'overtime' => round($overtimeHours, 2)
            ]);
            break;
            
        case 'start_break':
            $shift = $db->fetchOne("
                SELECT * FROM employee_time_clock 
                WHERE user_id = ? AND status = 'active'
            ", [$userId]);
            
            if (!$shift) {
                echo json_encode(['success' => false, 'message' => 'Not clocked in']);
                exit;
            }
            
            if ($shift['break_start_at'] && !$shift['break_end_at']) {
                echo json_encode(['success' => false, 'message' => 'Already on break']);
                exit;
            }
            
            $db->update('employee_time_clock', [
                'break_start_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$shift['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Break started']);
            break;
            
        case 'end_break':
            $shift = $db->fetchOne("
                SELECT * FROM employee_time_clock 
                WHERE user_id = ? AND status = 'active'
            ", [$userId]);
            
            if (!$shift || !$shift['break_start_at'] || $shift['break_end_at']) {
                echo json_encode(['success' => false, 'message' => 'Not on break']);
                exit;
            }
            
            $breakMinutes = (int)((time() - strtotime($shift['break_start_at'])) / 60);
            $totalBreak = ($shift['total_break_minutes'] ?? 0) + $breakMinutes;
            
            $db->update('employee_time_clock', [
                'break_end_at' => date('Y-m-d H:i:s'),
                'total_break_minutes' => $totalBreak
            ], 'id = ?', [$shift['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Break ended', 'break_minutes' => $breakMinutes]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Time Clock API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
