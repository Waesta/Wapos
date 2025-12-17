<?php
/**
 * Security Management API
 * Handles security personnel, scheduling, patrols, and incidents
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Services\SecurityService;

header('Content-Type: application/json');

$auth->requireRole(['admin', 'manager', 'security_manager', 'security_staff']);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$securityService = new SecurityService();
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        
        // ==================== PERSONNEL ====================
        
        case 'get_personnel':
            $filters = [
                'employment_status' => $_GET['employment_status'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            $personnel = $securityService->getPersonnel($filters);
            echo json_encode(['success' => true, 'data' => $personnel]);
            break;
            
        case 'get_personnel_by_id':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Personnel ID required');
            
            $personnel = $securityService->getPersonnelById($id);
            echo json_encode(['success' => true, 'data' => $personnel]);
            break;
            
        case 'create_personnel':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'security_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $securityService->createPersonnel($data);
            echo json_encode(['success' => true, 'message' => 'Personnel created successfully', 'id' => $result]);
            break;
            
        case 'update_personnel':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'security_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Personnel ID required');
            
            $securityService->updatePersonnel($id, $data);
            echo json_encode(['success' => true, 'message' => 'Personnel updated successfully']);
            break;
            
        // ==================== SHIFTS ====================
        
        case 'get_shifts':
            $shifts = $securityService->getShifts();
            echo json_encode(['success' => true, 'data' => $shifts]);
            break;
            
        case 'get_shift':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Shift ID required');
            
            $shift = $securityService->getShiftById($id);
            echo json_encode(['success' => true, 'data' => $shift]);
            break;
            
        // ==================== POSTS ====================
        
        case 'get_posts':
            $posts = $securityService->getPosts();
            echo json_encode(['success' => true, 'data' => $posts]);
            break;
            
        case 'get_post':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Post ID required');
            
            $post = $securityService->getPostById($id);
            echo json_encode(['success' => true, 'data' => $post]);
            break;
            
        // ==================== SCHEDULE ====================
        
        case 'get_schedule':
            $filters = [
                'date' => $_GET['date'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'personnel_id' => $_GET['personnel_id'] ?? null,
                'post_id' => $_GET['post_id'] ?? null,
                'status' => $_GET['status'] ?? null
            ];
            $schedule = $securityService->getSchedule($filters);
            echo json_encode(['success' => true, 'data' => $schedule]);
            break;
            
        case 'create_schedule':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'security_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $securityService->createSchedule($data, $userId);
            echo json_encode(['success' => true, 'message' => 'Schedule created successfully', 'id' => $result]);
            break;
            
        case 'check_in':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $scheduleId = $data['schedule_id'] ?? null;
            if (!$scheduleId) throw new Exception('Schedule ID required');
            
            $securityService->checkIn($scheduleId);
            echo json_encode(['success' => true, 'message' => 'Checked in successfully']);
            break;
            
        case 'check_out':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $scheduleId = $data['schedule_id'] ?? null;
            if (!$scheduleId) throw new Exception('Schedule ID required');
            
            $securityService->checkOut($scheduleId);
            echo json_encode(['success' => true, 'message' => 'Checked out successfully']);
            break;
            
        // ==================== PATROL ROUTES ====================
        
        case 'get_patrol_routes':
            $routes = $securityService->getPatrolRoutes();
            echo json_encode(['success' => true, 'data' => $routes]);
            break;
            
        case 'get_patrol_logs':
            $filters = [
                'personnel_id' => $_GET['personnel_id'] ?? null,
                'date' => $_GET['date'] ?? null
            ];
            $logs = $securityService->getPatrolLogs($filters);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;
            
        case 'start_patrol':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $scheduleId = $data['schedule_id'] ?? null;
            $routeId = $data['route_id'] ?? null;
            $personnelId = $data['personnel_id'] ?? null;
            
            if (!$scheduleId || !$routeId || !$personnelId) {
                throw new Exception('Schedule ID, route ID, and personnel ID required');
            }
            
            $result = $securityService->startPatrol($scheduleId, $routeId, $personnelId);
            echo json_encode(['success' => true, 'message' => 'Patrol started successfully', 'id' => $result]);
            break;
            
        case 'complete_patrol':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $patrolLogId = $data['patrol_log_id'] ?? null;
            $checkpointsCompleted = $data['checkpoints_completed'] ?? [];
            $observations = $data['observations'] ?? null;
            
            if (!$patrolLogId) throw new Exception('Patrol log ID required');
            
            $securityService->completePatrol($patrolLogId, $checkpointsCompleted, $observations);
            echo json_encode(['success' => true, 'message' => 'Patrol completed successfully']);
            break;
            
        // ==================== INCIDENTS ====================
        
        case 'get_incidents':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'severity' => $_GET['severity'] ?? null,
                'incident_type' => $_GET['incident_type'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            $incidents = $securityService->getIncidents($filters);
            echo json_encode(['success' => true, 'data' => $incidents]);
            break;
            
        case 'get_incident':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Incident ID required');
            
            $incident = $securityService->getIncidentById($id);
            echo json_encode(['success' => true, 'data' => $incident]);
            break;
            
        case 'create_incident':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $securityService->createIncident($data, $userId);
            echo json_encode([
                'success' => true, 
                'message' => 'Incident reported successfully',
                'data' => $result
            ]);
            break;
            
        case 'update_incident':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Incident ID required');
            
            $securityService->updateIncident($id, $data);
            echo json_encode(['success' => true, 'message' => 'Incident updated successfully']);
            break;
            
        case 'resolve_incident':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'security_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $resolution = $data['resolution'] ?? '';
            if (!$id) throw new Exception('Incident ID required');
            
            $securityService->resolveIncident($id, $resolution, $userId);
            echo json_encode(['success' => true, 'message' => 'Incident resolved successfully']);
            break;
            
        // ==================== VISITOR LOG ====================
        
        case 'get_visitor_log':
            $filters = [
                'date' => $_GET['date'] ?? null,
                'still_inside' => $_GET['still_inside'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            $visitors = $securityService->getVisitorLog($filters);
            echo json_encode(['success' => true, 'data' => $visitors]);
            break;
            
        case 'log_visitor_entry':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $personnelId = $data['personnel_id'] ?? null;
            $postId = $data['post_id'] ?? null;
            
            if (!$personnelId || !$postId) {
                throw new Exception('Personnel ID and post ID required');
            }
            
            $result = $securityService->logVisitorEntry($data, $personnelId, $postId);
            echo json_encode(['success' => true, 'message' => 'Visitor entry logged successfully', 'id' => $result]);
            break;
            
        case 'log_visitor_exit':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $visitorLogId = $data['visitor_log_id'] ?? null;
            $personnelId = $data['personnel_id'] ?? null;
            $postId = $data['post_id'] ?? null;
            $itemsTakenOut = $data['items_taken_out'] ?? null;
            
            if (!$visitorLogId || !$personnelId || !$postId) {
                throw new Exception('Visitor log ID, personnel ID, and post ID required');
            }
            
            $securityService->logVisitorExit($visitorLogId, $personnelId, $postId, $itemsTakenOut);
            echo json_encode(['success' => true, 'message' => 'Visitor exit logged successfully']);
            break;
            
        // ==================== DASHBOARD & ANALYTICS ====================
        
        case 'get_dashboard_stats':
            $date = $_GET['date'] ?? null;
            
            $stats = $securityService->getDashboardStats($date);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
