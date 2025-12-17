<?php
/**
 * HR & Employee Management API
 * Handles employee records, payroll, leave, and performance management
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Services\HRService;

header('Content-Type: application/json');

$auth->requireRole(['admin', 'manager', 'hr_manager', 'hr_staff']);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$hrService = new HRService();
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        
        // ==================== DEPARTMENTS ====================
        
        case 'get_departments':
            $departments = $hrService->getDepartments();
            echo json_encode(['success' => true, 'data' => $departments]);
            break;
            
        case 'get_department':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Department ID required');
            
            $department = $hrService->getDepartmentById($id);
            echo json_encode(['success' => true, 'data' => $department]);
            break;
            
        // ==================== POSITIONS ====================
        
        case 'get_positions':
            $filters = [
                'department_id' => $_GET['department_id'] ?? null,
                'is_active' => $_GET['is_active'] ?? true
            ];
            $positions = $hrService->getPositions($filters);
            echo json_encode(['success' => true, 'data' => $positions]);
            break;
            
        // ==================== EMPLOYEES ====================
        
        case 'get_employees':
            $filters = [
                'employment_status' => $_GET['employment_status'] ?? null,
                'department_id' => $_GET['department_id'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            $employees = $hrService->getEmployees($filters);
            echo json_encode(['success' => true, 'data' => $employees]);
            break;
            
        case 'get_employee':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Employee ID required');
            
            $employee = $hrService->getEmployeeById($id);
            echo json_encode(['success' => true, 'data' => $employee]);
            break;
            
        case 'get_employee_by_user':
            $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
            
            $employee = $hrService->getEmployeeByUserId($userId);
            echo json_encode(['success' => true, 'data' => $employee]);
            break;
            
        case 'create_employee':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'hr_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $hrService->createEmployee($data);
            echo json_encode(['success' => true, 'message' => 'Employee created successfully', 'id' => $result]);
            break;
            
        case 'update_employee':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'hr_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Employee ID required');
            
            $hrService->updateEmployee($id, $data);
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
            break;
            
        // ==================== PAYROLL ====================
        
        case 'get_payroll_structure':
            $employeeId = $_GET['employee_id'] ?? null;
            if (!$employeeId) throw new Exception('Employee ID required');
            
            $structure = $hrService->getPayrollStructure($employeeId);
            echo json_encode(['success' => true, 'data' => $structure]);
            break;
            
        case 'create_payroll_structure':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'hr_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $employeeId = $data['employee_id'] ?? null;
            if (!$employeeId) throw new Exception('Employee ID required');
            
            $result = $hrService->createPayrollStructure($employeeId, $data, $userId);
            echo json_encode(['success' => true, 'message' => 'Payroll structure created successfully', 'id' => $result]);
            break;
            
        case 'get_payroll_runs':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'year' => $_GET['year'] ?? null
            ];
            $runs = $hrService->getPayrollRuns($filters);
            echo json_encode(['success' => true, 'data' => $runs]);
            break;
            
        case 'create_payroll_run':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'hr_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $hrService->createPayrollRun($data, $userId);
            echo json_encode([
                'success' => true, 
                'message' => 'Payroll run created successfully',
                'data' => $result
            ]);
            break;
            
        case 'generate_payroll_details':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'hr_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $payrollRunId = $data['payroll_run_id'] ?? null;
            if (!$payrollRunId) throw new Exception('Payroll run ID required');
            
            $hrService->generatePayrollDetails($payrollRunId);
            echo json_encode(['success' => true, 'message' => 'Payroll details generated successfully']);
            break;
            
        case 'approve_payroll_run':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $payrollRunId = $data['payroll_run_id'] ?? null;
            if (!$payrollRunId) throw new Exception('Payroll run ID required');
            
            $hrService->approvePayrollRun($payrollRunId, $userId);
            echo json_encode(['success' => true, 'message' => 'Payroll run approved successfully']);
            break;
            
        // ==================== LEAVE MANAGEMENT ====================
        
        case 'get_leave_types':
            $leaveTypes = $hrService->getLeaveTypes();
            echo json_encode(['success' => true, 'data' => $leaveTypes]);
            break;
            
        case 'get_leave_balance':
            $employeeId = $_GET['employee_id'] ?? null;
            $leaveTypeId = $_GET['leave_type_id'] ?? null;
            $year = $_GET['year'] ?? null;
            
            if (!$employeeId || !$leaveTypeId) {
                throw new Exception('Employee ID and leave type ID required');
            }
            
            $balance = $hrService->getLeaveBalance($employeeId, $leaveTypeId, $year);
            echo json_encode(['success' => true, 'data' => $balance]);
            break;
            
        case 'get_leave_applications':
            $filters = [
                'employee_id' => $_GET['employee_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'year' => $_GET['year'] ?? null
            ];
            $applications = $hrService->getLeaveApplications($filters);
            echo json_encode(['success' => true, 'data' => $applications]);
            break;
            
        case 'apply_for_leave':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $employeeId = $data['employee_id'] ?? null;
            if (!$employeeId) throw new Exception('Employee ID required');
            
            $result = $hrService->applyForLeave($employeeId, $data);
            echo json_encode([
                'success' => true, 
                'message' => 'Leave application submitted successfully',
                'data' => $result
            ]);
            break;
            
        case 'review_leave_application':
            validateCSRFToken();
            $auth->requireRole(['admin', 'manager', 'hr_manager']);
            $data = json_decode(file_get_contents('php://input'), true);
            $applicationId = $data['application_id'] ?? null;
            $status = $data['status'] ?? null;
            $comments = $data['comments'] ?? '';
            
            if (!$applicationId || !$status) {
                throw new Exception('Application ID and status required');
            }
            
            $hrService->reviewLeaveApplication($applicationId, $status, $comments, $userId);
            echo json_encode(['success' => true, 'message' => 'Leave application reviewed successfully']);
            break;
            
        // ==================== PERFORMANCE REVIEWS ====================
        
        case 'get_performance_cycles':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'year' => $_GET['year'] ?? null
            ];
            $cycles = $hrService->getPerformanceCycles($filters);
            echo json_encode(['success' => true, 'data' => $cycles]);
            break;
            
        case 'get_performance_reviews':
            $filters = [
                'employee_id' => $_GET['employee_id'] ?? null,
                'cycle_id' => $_GET['cycle_id'] ?? null,
                'status' => $_GET['status'] ?? null
            ];
            $reviews = $hrService->getPerformanceReviews($filters);
            echo json_encode(['success' => true, 'data' => $reviews]);
            break;
            
        // ==================== DASHBOARD & ANALYTICS ====================
        
        case 'get_dashboard_stats':
            $stats = $hrService->getDashboardStats();
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
