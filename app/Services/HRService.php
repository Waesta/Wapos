<?php
namespace App\Services;

use Database;
use Exception;

class HRService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ==================== DEPARTMENTS ====================
    
    public function getDepartments($activeOnly = true) {
        $sql = "SELECT d.*, u.full_name as manager_name, 
                pd.department_name as parent_department_name
                FROM hr_departments d
                LEFT JOIN users u ON d.manager_user_id = u.id
                LEFT JOIN hr_departments pd ON d.parent_department_id = pd.id
                WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND d.is_active = 1";
        }
        
        $sql .= " ORDER BY d.department_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getDepartmentById($id) {
        return $this->db->fetchOne("
            SELECT d.*, u.full_name as manager_name
            FROM hr_departments d
            LEFT JOIN users u ON d.manager_user_id = u.id
            WHERE d.id = ?
        ", [$id]);
    }
    
    // ==================== POSITIONS ====================
    
    public function getPositions($filters = []) {
        $sql = "SELECT p.*, d.department_name
                FROM hr_positions p
                LEFT JOIN hr_departments d ON p.department_id = d.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['department_id'])) {
            $sql .= " AND p.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['is_active'])) {
            $sql .= " AND p.is_active = 1";
        }
        
        $sql .= " ORDER BY d.department_name, p.position_title";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // ==================== EMPLOYEES ====================
    
    public function getEmployees($filters = []) {
        $sql = "SELECT e.*, u.full_name, u.email, u.role,
                d.department_name, p.position_title,
                m.full_name as manager_name
                FROM hr_employees e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN hr_departments d ON e.department_id = d.id
                LEFT JOIN hr_positions p ON e.position_id = p.id
                LEFT JOIN users m ON e.reports_to_user_id = m.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['employment_status'])) {
            $sql .= " AND e.employment_status = ?";
            $params[] = $filters['employment_status'];
        }
        
        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (u.full_name LIKE ? OR e.employee_number LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY u.full_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getEmployeeById($id) {
        return $this->db->fetchOne("
            SELECT e.*, u.full_name, u.email, u.username, u.role,
            d.department_name, p.position_title,
            m.full_name as manager_name
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_positions p ON e.position_id = p.id
            LEFT JOIN users m ON e.reports_to_user_id = m.id
            WHERE e.id = ?
        ", [$id]);
    }
    
    public function getEmployeeByUserId($userId) {
        return $this->db->fetchOne("
            SELECT e.*, d.department_name, p.position_title
            FROM hr_employees e
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_positions p ON e.position_id = p.id
            WHERE e.user_id = ?
        ", [$userId]);
    }
    
    public function createEmployee($data) {
        $sql = "INSERT INTO hr_employees (
            user_id, employee_number, department_id, position_id, reports_to_user_id,
            hire_date, probation_end_date, employment_status, employment_type,
            work_location, work_schedule, id_number, passport_number, tax_pin,
            social_security_number, bank_name, bank_account_number, bank_branch,
            emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
            date_of_birth, gender, marital_status, nationality, address, city,
            postal_code, country, personal_email, personal_phone, photo_path, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['user_id'],
            $data['employee_number'],
            $data['department_id'] ?? null,
            $data['position_id'] ?? null,
            $data['reports_to_user_id'] ?? null,
            $data['hire_date'],
            $data['probation_end_date'] ?? null,
            $data['employment_status'] ?? 'probation',
            $data['employment_type'],
            $data['work_location'] ?? null,
            $data['work_schedule'] ?? null,
            $data['id_number'] ?? null,
            $data['passport_number'] ?? null,
            $data['tax_pin'] ?? null,
            $data['social_security_number'] ?? null,
            $data['bank_name'] ?? null,
            $data['bank_account_number'] ?? null,
            $data['bank_branch'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_relationship'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['marital_status'] ?? null,
            $data['nationality'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['country'] ?? null,
            $data['personal_email'] ?? null,
            $data['personal_phone'] ?? null,
            $data['photo_path'] ?? null,
            $data['notes'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function updateEmployee($id, $data) {
        $sql = "UPDATE hr_employees SET
            department_id = ?, position_id = ?, reports_to_user_id = ?,
            employment_status = ?, employment_type = ?, work_location = ?,
            work_schedule = ?, id_number = ?, passport_number = ?, tax_pin = ?,
            social_security_number = ?, bank_name = ?, bank_account_number = ?,
            bank_branch = ?, emergency_contact_name = ?, emergency_contact_relationship = ?,
            emergency_contact_phone = ?, date_of_birth = ?, gender = ?, marital_status = ?,
            nationality = ?, address = ?, city = ?, postal_code = ?, country = ?,
            personal_email = ?, personal_phone = ?, photo_path = ?, notes = ?
        WHERE id = ?";
        
        $params = [
            $data['department_id'] ?? null,
            $data['position_id'] ?? null,
            $data['reports_to_user_id'] ?? null,
            $data['employment_status'],
            $data['employment_type'],
            $data['work_location'] ?? null,
            $data['work_schedule'] ?? null,
            $data['id_number'] ?? null,
            $data['passport_number'] ?? null,
            $data['tax_pin'] ?? null,
            $data['social_security_number'] ?? null,
            $data['bank_name'] ?? null,
            $data['bank_account_number'] ?? null,
            $data['bank_branch'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_relationship'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['marital_status'] ?? null,
            $data['nationality'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['country'] ?? null,
            $data['personal_email'] ?? null,
            $data['personal_phone'] ?? null,
            $data['photo_path'] ?? null,
            $data['notes'] ?? null,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    // ==================== PAYROLL ====================
    
    public function getPayrollStructure($employeeId) {
        return $this->db->fetchOne("
            SELECT * FROM hr_payroll_structure 
            WHERE employee_id = ? AND is_active = 1
            ORDER BY effective_date DESC
            LIMIT 1
        ", [$employeeId]);
    }
    
    public function createPayrollStructure($employeeId, $data, $userId) {
        // Deactivate previous structures
        $this->db->execute("UPDATE hr_payroll_structure SET is_active = 0 WHERE employee_id = ?", [$employeeId]);
        
        $sql = "INSERT INTO hr_payroll_structure (
            employee_id, effective_date, basic_salary, housing_allowance, transport_allowance,
            meal_allowance, medical_allowance, other_allowances, gross_salary,
            tax_deduction, pension_deduction, insurance_deduction, loan_deduction,
            other_deductions, net_salary, payment_frequency, payment_method,
            currency, is_active, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";
        
        $params = [
            $employeeId,
            $data['effective_date'],
            $data['basic_salary'],
            $data['housing_allowance'] ?? 0,
            $data['transport_allowance'] ?? 0,
            $data['meal_allowance'] ?? 0,
            $data['medical_allowance'] ?? 0,
            $data['other_allowances'] ?? 0,
            $data['gross_salary'],
            $data['tax_deduction'] ?? 0,
            $data['pension_deduction'] ?? 0,
            $data['insurance_deduction'] ?? 0,
            $data['loan_deduction'] ?? 0,
            $data['other_deductions'] ?? 0,
            $data['net_salary'],
            $data['payment_frequency'] ?? 'monthly',
            $data['payment_method'] ?? 'bank_transfer',
            $data['currency'] ?? 'KES',
            $data['notes'] ?? null,
            $userId
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function getPayrollRuns($filters = []) {
        $sql = "SELECT * FROM hr_payroll_runs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(period_start_date) = ?";
            $params[] = $filters['year'];
        }
        
        $sql .= " ORDER BY period_start_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function createPayrollRun($data, $userId) {
        $payrollNumber = $this->generatePayrollNumber();
        
        $sql = "INSERT INTO hr_payroll_runs (
            payroll_number, period_start_date, period_end_date, payment_date,
            status, notes, created_by
        ) VALUES (?, ?, ?, ?, 'draft', ?, ?)";
        
        $params = [
            $payrollNumber,
            $data['period_start_date'],
            $data['period_end_date'],
            $data['payment_date'],
            $data['notes'] ?? null,
            $userId
        ];
        
        $payrollRunId = $this->db->execute($sql, $params);
        
        return ['id' => $payrollRunId, 'payroll_number' => $payrollNumber];
    }
    
    public function generatePayrollDetails($payrollRunId) {
        $payrollRun = $this->db->fetchOne("SELECT * FROM hr_payroll_runs WHERE id = ?", [$payrollRunId]);
        
        $employees = $this->db->fetchAll("
            SELECT e.id, e.user_id, ps.*
            FROM hr_employees e
            JOIN hr_payroll_structure ps ON e.id = ps.employee_id
            WHERE e.employment_status IN ('probation', 'confirmed', 'contract')
            AND ps.is_active = 1
        ");
        
        foreach ($employees as $employee) {
            $this->db->execute("
                INSERT INTO hr_payroll_details (
                    payroll_run_id, employee_id, basic_salary, housing_allowance,
                    transport_allowance, meal_allowance, gross_pay, tax_deduction,
                    pension_deduction, insurance_deduction, loan_deduction,
                    other_deductions, total_deductions, net_pay, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ", [
                $payrollRunId,
                $employee['id'],
                $employee['basic_salary'],
                $employee['housing_allowance'],
                $employee['transport_allowance'],
                $employee['meal_allowance'],
                $employee['gross_salary'],
                $employee['tax_deduction'],
                $employee['pension_deduction'],
                $employee['insurance_deduction'],
                $employee['loan_deduction'],
                $employee['other_deductions'],
                $employee['tax_deduction'] + $employee['pension_deduction'] + $employee['insurance_deduction'] + $employee['loan_deduction'] + $employee['other_deductions'],
                $employee['net_salary']
            ]);
        }
        
        $this->updatePayrollRunTotals($payrollRunId);
        
        return true;
    }
    
    private function updatePayrollRunTotals($payrollRunId) {
        $totals = $this->db->fetchOne("
            SELECT 
                COUNT(*) as employee_count,
                SUM(gross_pay) as total_gross,
                SUM(total_deductions) as total_deductions,
                SUM(net_pay) as total_net
            FROM hr_payroll_details
            WHERE payroll_run_id = ?
        ", [$payrollRunId]);
        
        $this->db->execute("
            UPDATE hr_payroll_runs SET
                employee_count = ?,
                total_gross = ?,
                total_deductions = ?,
                total_net = ?
            WHERE id = ?
        ", [
            $totals['employee_count'],
            $totals['total_gross'],
            $totals['total_deductions'],
            $totals['total_net'],
            $payrollRunId
        ]);
    }
    
    public function approvePayrollRun($payrollRunId, $userId) {
        return $this->db->execute("
            UPDATE hr_payroll_runs SET
                status = 'approved',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ?
        ", [$userId, $payrollRunId]);
    }
    
    private function generatePayrollNumber() {
        $prefix = 'PAY';
        $yearMonth = date('Ym');
        
        $sql = "SELECT payroll_number FROM hr_payroll_runs 
                WHERE payroll_number LIKE ? 
                ORDER BY payroll_number DESC LIMIT 1";
        $result = $this->db->fetchOne($sql, [$prefix . $yearMonth . '%']);
        
        if ($result) {
            $lastNumber = intval(substr($result['payroll_number'], -3));
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }
        
        return $prefix . $yearMonth . $newNumber;
    }
    
    // ==================== LEAVE MANAGEMENT ====================
    
    public function getLeaveTypes($activeOnly = true) {
        $sql = "SELECT * FROM hr_leave_types WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY leave_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getLeaveBalance($employeeId, $leaveTypeId, $year = null) {
        $year = $year ?? date('Y');
        
        $balance = $this->db->fetchOne("
            SELECT * FROM hr_leave_balances 
            WHERE employee_id = ? AND leave_type_id = ? AND year = ?
        ", [$employeeId, $leaveTypeId, $year]);
        
        if (!$balance) {
            $leaveType = $this->db->fetchOne("SELECT * FROM hr_leave_types WHERE id = ?", [$leaveTypeId]);
            
            $this->db->execute("
                INSERT INTO hr_leave_balances (
                    employee_id, leave_type_id, year, entitled_days, available_days
                ) VALUES (?, ?, ?, ?, ?)
            ", [
                $employeeId,
                $leaveTypeId,
                $year,
                $leaveType['annual_entitlement_days'],
                $leaveType['annual_entitlement_days']
            ]);
            
            $balance = $this->db->fetchOne("
                SELECT * FROM hr_leave_balances 
                WHERE employee_id = ? AND leave_type_id = ? AND year = ?
            ", [$employeeId, $leaveTypeId, $year]);
        }
        
        return $balance;
    }
    
    public function getLeaveApplications($filters = []) {
        $sql = "SELECT la.*, e.employee_number, u.full_name as employee_name,
                lt.leave_name, r.full_name as reviewed_by_name
                FROM hr_leave_applications la
                JOIN hr_employees e ON la.employee_id = e.id
                JOIN users u ON e.user_id = u.id
                JOIN hr_leave_types lt ON la.leave_type_id = lt.id
                LEFT JOIN users r ON la.reviewed_by = r.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['employee_id'])) {
            $sql .= " AND la.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND la.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(la.start_date) = ?";
            $params[] = $filters['year'];
        }
        
        $sql .= " ORDER BY la.applied_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function applyForLeave($employeeId, $data) {
        $applicationNumber = $this->generateLeaveApplicationNumber();
        
        $sql = "INSERT INTO hr_leave_applications (
            application_number, employee_id, leave_type_id, start_date, end_date,
            total_days, reason, contact_during_leave, handover_notes,
            supporting_document_path, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $params = [
            $applicationNumber,
            $employeeId,
            $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $data['total_days'],
            $data['reason'],
            $data['contact_during_leave'] ?? null,
            $data['handover_notes'] ?? null,
            $data['supporting_document_path'] ?? null
        ];
        
        $applicationId = $this->db->execute($sql, $params);
        
        $this->db->execute("
            UPDATE hr_leave_balances SET
                pending_days = pending_days + ?,
                available_days = available_days - ?
            WHERE employee_id = ? AND leave_type_id = ? AND year = ?
        ", [
            $data['total_days'],
            $data['total_days'],
            $employeeId,
            $data['leave_type_id'],
            date('Y', strtotime($data['start_date']))
        ]);
        
        return ['id' => $applicationId, 'application_number' => $applicationNumber];
    }
    
    public function reviewLeaveApplication($applicationId, $status, $comments, $userId) {
        $application = $this->db->fetchOne("SELECT * FROM hr_leave_applications WHERE id = ?", [$applicationId]);
        
        $this->db->execute("
            UPDATE hr_leave_applications SET
                status = ?,
                reviewed_by = ?,
                reviewed_at = NOW(),
                review_comments = ?
            WHERE id = ?
        ", [$status, $userId, $comments, $applicationId]);
        
        if ($status === 'approved') {
            $this->db->execute("
                UPDATE hr_leave_balances SET
                    pending_days = pending_days - ?,
                    taken_days = taken_days + ?
                WHERE employee_id = ? AND leave_type_id = ? AND year = ?
            ", [
                $application['total_days'],
                $application['total_days'],
                $application['employee_id'],
                $application['leave_type_id'],
                date('Y', strtotime($application['start_date']))
            ]);
        } elseif ($status === 'rejected') {
            $this->db->execute("
                UPDATE hr_leave_balances SET
                    pending_days = pending_days - ?,
                    available_days = available_days + ?
                WHERE employee_id = ? AND leave_type_id = ? AND year = ?
            ", [
                $application['total_days'],
                $application['total_days'],
                $application['employee_id'],
                $application['leave_type_id'],
                date('Y', strtotime($application['start_date']))
            ]);
        }
        
        return true;
    }
    
    private function generateLeaveApplicationNumber() {
        $prefix = 'LV';
        $year = date('Y');
        
        $sql = "SELECT application_number FROM hr_leave_applications 
                WHERE application_number LIKE ? 
                ORDER BY application_number DESC LIMIT 1";
        $result = $this->db->fetchOne($sql, [$prefix . $year . '%']);
        
        if ($result) {
            $lastNumber = intval(substr($result['application_number'], -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . $year . $newNumber;
    }
    
    // ==================== PERFORMANCE REVIEWS ====================
    
    public function getPerformanceCycles($filters = []) {
        $sql = "SELECT * FROM hr_performance_cycles WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['year'])) {
            $sql .= " AND cycle_year = ?";
            $params[] = $filters['year'];
        }
        
        $sql .= " ORDER BY cycle_year DESC, start_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getPerformanceReviews($filters = []) {
        $sql = "SELECT pr.*, e.employee_number, u.full_name as employee_name,
                r.full_name as reviewer_name, pc.cycle_name
                FROM hr_performance_reviews pr
                JOIN hr_employees e ON pr.employee_id = e.id
                JOIN users u ON e.user_id = u.id
                JOIN users r ON pr.reviewer_id = r.id
                JOIN hr_performance_cycles pc ON pr.cycle_id = pc.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['employee_id'])) {
            $sql .= " AND pr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['cycle_id'])) {
            $sql .= " AND pr.cycle_id = ?";
            $params[] = $filters['cycle_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND pr.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY pr.review_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // ==================== DASHBOARD & ANALYTICS ====================
    
    public function getDashboardStats() {
        $stats = [];
        
        $stats['total_employees'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM hr_employees 
            WHERE employment_status IN ('probation', 'confirmed', 'contract')
        ")['count'];
        
        $stats['on_probation'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM hr_employees 
            WHERE employment_status = 'probation'
        ")['count'];
        
        $stats['pending_leave_requests'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM hr_leave_applications 
            WHERE status = 'pending'
        ")['count'];
        
        $stats['employees_on_leave_today'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM hr_leave_applications 
            WHERE status = 'approved' 
            AND CURDATE() BETWEEN start_date AND end_date
        ")['count'];
        
        $stats['employees_on_leave_total'] = $this->db->fetchOne("
            SELECT COUNT(*) as count 
            FROM hr_leave_applications
            WHERE status = 'approved'
        ")['count'];
        
        $stats['upcoming_birthdays'] = $this->db->fetchAll("
            SELECT e.id, u.full_name, e.date_of_birth
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            WHERE MONTH(e.date_of_birth) = MONTH(CURDATE())
            AND DAY(e.date_of_birth) >= DAY(CURDATE())
            ORDER BY DAY(e.date_of_birth)
            LIMIT 5
        ");
        
        $stats['employees_by_department'] = $this->db->fetchAll("
            SELECT d.department_name, COUNT(*) as count
            FROM hr_employees e
            JOIN hr_departments d ON e.department_id = d.id
            WHERE e.employment_status IN ('probation', 'confirmed', 'contract')
            GROUP BY d.department_name
            ORDER BY count DESC
        ");
        
        return $stats;
    }
}
