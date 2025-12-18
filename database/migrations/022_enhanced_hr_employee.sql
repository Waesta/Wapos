-- =====================================================
-- WAPOS Enhanced HR & Employee Management Module
-- Migration: 022_enhanced_hr_employee.sql
-- Description: Payroll, leave management, performance reviews, and comprehensive HR
-- =====================================================

-- Employee Departments
CREATE TABLE IF NOT EXISTS hr_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(20) UNIQUE NOT NULL,
    parent_department_id INT,
    manager_user_id INT UNSIGNED,
    description TEXT,
    cost_center_code VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_department_id) REFERENCES hr_departments(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job Positions
CREATE TABLE IF NOT EXISTS hr_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_title VARCHAR(100) NOT NULL,
    position_code VARCHAR(50) UNIQUE NOT NULL,
    department_id INT,
    job_level ENUM('entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive') NOT NULL,
    employment_type ENUM('full_time', 'part_time', 'contract', 'temporary', 'intern') NOT NULL,
    description TEXT,
    responsibilities TEXT,
    requirements TEXT,
    min_salary DECIMAL(10,2),
    max_salary DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES hr_departments(id) ON DELETE SET NULL,
    INDEX idx_department (department_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Extended Profile
CREATE TABLE IF NOT EXISTS hr_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED UNIQUE NOT NULL,
    employee_number VARCHAR(50) UNIQUE NOT NULL,
    department_id INT,
    position_id INT,
    reports_to_user_id INT UNSIGNED,
    hire_date DATE NOT NULL,
    probation_end_date DATE,
    confirmation_date DATE,
    termination_date DATE,
    employment_status ENUM('probation', 'confirmed', 'contract', 'on_notice', 'terminated', 'resigned') DEFAULT 'probation',
    employment_type ENUM('full_time', 'part_time', 'contract', 'temporary', 'intern') NOT NULL,
    work_location VARCHAR(100),
    work_schedule VARCHAR(100) COMMENT 'e.g., Mon-Fri 9-5',
    id_number VARCHAR(50),
    passport_number VARCHAR(50),
    tax_pin VARCHAR(50),
    social_security_number VARCHAR(50),
    bank_name VARCHAR(100),
    bank_account_number VARCHAR(50),
    bank_branch VARCHAR(100),
    emergency_contact_name VARCHAR(100),
    emergency_contact_relationship VARCHAR(50),
    emergency_contact_phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    marital_status ENUM('single', 'married', 'divorced', 'widowed'),
    nationality VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    personal_email VARCHAR(100),
    personal_phone VARCHAR(20),
    photo_path VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES hr_departments(id) ON DELETE SET NULL,
    FOREIGN KEY (position_id) REFERENCES hr_positions(id) ON DELETE SET NULL,
    FOREIGN KEY (reports_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_number (employee_number),
    INDEX idx_department (department_id),
    INDEX idx_status (employment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Structure
CREATE TABLE IF NOT EXISTS hr_payroll_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    effective_date DATE NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    housing_allowance DECIMAL(10,2) DEFAULT 0.00,
    transport_allowance DECIMAL(10,2) DEFAULT 0.00,
    meal_allowance DECIMAL(10,2) DEFAULT 0.00,
    medical_allowance DECIMAL(10,2) DEFAULT 0.00,
    other_allowances DECIMAL(10,2) DEFAULT 0.00,
    gross_salary DECIMAL(10,2) NOT NULL,
    tax_deduction DECIMAL(10,2) DEFAULT 0.00,
    pension_deduction DECIMAL(10,2) DEFAULT 0.00,
    insurance_deduction DECIMAL(10,2) DEFAULT 0.00,
    loan_deduction DECIMAL(10,2) DEFAULT 0.00,
    other_deductions DECIMAL(10,2) DEFAULT 0.00,
    net_salary DECIMAL(10,2) NOT NULL,
    payment_frequency ENUM('weekly', 'bi_weekly', 'monthly', 'quarterly') DEFAULT 'monthly',
    payment_method ENUM('bank_transfer', 'cash', 'cheque', 'mobile_money') DEFAULT 'bank_transfer',
    currency VARCHAR(10) DEFAULT 'KES',
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_employee (employee_id),
    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Runs
CREATE TABLE IF NOT EXISTS hr_payroll_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_number VARCHAR(50) UNIQUE NOT NULL,
    period_start_date DATE NOT NULL,
    period_end_date DATE NOT NULL,
    payment_date DATE NOT NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'processed', 'paid', 'cancelled') DEFAULT 'draft',
    total_gross DECIMAL(12,2) DEFAULT 0.00,
    total_deductions DECIMAL(12,2) DEFAULT 0.00,
    total_net DECIMAL(12,2) DEFAULT 0.00,
    employee_count INT DEFAULT 0,
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED,
    approved_at TIMESTAMP NULL,
    processed_by INT UNSIGNED,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_period (period_start_date, period_end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Details (Individual Employee Payslips)
CREATE TABLE IF NOT EXISTS hr_payroll_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    housing_allowance DECIMAL(10,2) DEFAULT 0.00,
    transport_allowance DECIMAL(10,2) DEFAULT 0.00,
    meal_allowance DECIMAL(10,2) DEFAULT 0.00,
    overtime_pay DECIMAL(10,2) DEFAULT 0.00,
    bonus DECIMAL(10,2) DEFAULT 0.00,
    commission DECIMAL(10,2) DEFAULT 0.00,
    other_earnings DECIMAL(10,2) DEFAULT 0.00,
    gross_pay DECIMAL(10,2) NOT NULL,
    tax_deduction DECIMAL(10,2) DEFAULT 0.00,
    pension_deduction DECIMAL(10,2) DEFAULT 0.00,
    insurance_deduction DECIMAL(10,2) DEFAULT 0.00,
    loan_deduction DECIMAL(10,2) DEFAULT 0.00,
    advance_deduction DECIMAL(10,2) DEFAULT 0.00,
    other_deductions DECIMAL(10,2) DEFAULT 0.00,
    total_deductions DECIMAL(10,2) DEFAULT 0.00,
    net_pay DECIMAL(10,2) NOT NULL,
    days_worked DECIMAL(5,2) DEFAULT 0.00,
    hours_worked DECIMAL(6,2) DEFAULT 0.00,
    overtime_hours DECIMAL(6,2) DEFAULT 0.00,
    payment_status ENUM('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
    payment_reference VARCHAR(100),
    payment_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_run_id) REFERENCES hr_payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    INDEX idx_payroll_run (payroll_run_id),
    INDEX idx_employee (employee_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave Types
CREATE TABLE IF NOT EXISTS hr_leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_name VARCHAR(100) NOT NULL,
    leave_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    annual_entitlement_days INT DEFAULT 0,
    max_consecutive_days INT,
    requires_approval BOOLEAN DEFAULT TRUE,
    requires_document BOOLEAN DEFAULT FALSE,
    is_paid BOOLEAN DEFAULT TRUE,
    carry_forward_allowed BOOLEAN DEFAULT FALSE,
    max_carry_forward_days INT DEFAULT 0,
    accrual_rate DECIMAL(5,2) COMMENT 'Days per month',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Leave Balances
CREATE TABLE IF NOT EXISTS hr_leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    entitled_days DECIMAL(5,2) NOT NULL,
    carried_forward_days DECIMAL(5,2) DEFAULT 0.00,
    taken_days DECIMAL(5,2) DEFAULT 0.00,
    pending_days DECIMAL(5,2) DEFAULT 0.00,
    available_days DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_leave_year (employee_id, leave_type_id, year),
    INDEX idx_employee_year (employee_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave Applications
CREATE TABLE IF NOT EXISTS hr_leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_number VARCHAR(50) UNIQUE NOT NULL,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL,
    reason TEXT NOT NULL,
    contact_during_leave VARCHAR(100),
    handover_notes TEXT,
    supporting_document_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'recalled') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED,
    reviewed_at TIMESTAMP NULL,
    review_comments TEXT,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Review Cycles
CREATE TABLE IF NOT EXISTS hr_performance_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_name VARCHAR(100) NOT NULL,
    cycle_year INT NOT NULL,
    cycle_period ENUM('annual', 'semi_annual', 'quarterly', 'monthly') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    review_deadline DATE NOT NULL,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    description TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_year (cycle_year),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Reviews
CREATE TABLE IF NOT EXISTS hr_performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    employee_id INT NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    review_date DATE NOT NULL,
    review_type ENUM('self', 'supervisor', 'peer', '360') NOT NULL,
    overall_rating DECIMAL(3,2) CHECK (overall_rating BETWEEN 1 AND 5),
    work_quality_rating DECIMAL(3,2),
    productivity_rating DECIMAL(3,2),
    communication_rating DECIMAL(3,2),
    teamwork_rating DECIMAL(3,2),
    leadership_rating DECIMAL(3,2),
    initiative_rating DECIMAL(3,2),
    punctuality_rating DECIMAL(3,2),
    strengths TEXT,
    areas_for_improvement TEXT,
    achievements TEXT,
    goals_met TEXT,
    goals_not_met TEXT,
    training_needs TEXT,
    career_aspirations TEXT,
    reviewer_comments TEXT,
    employee_comments TEXT,
    action_plan TEXT,
    recommended_salary_increase DECIMAL(5,2) COMMENT 'Percentage',
    recommended_promotion BOOLEAN DEFAULT FALSE,
    promotion_position VARCHAR(100),
    status ENUM('draft', 'submitted', 'acknowledged', 'completed') DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cycle_id) REFERENCES hr_performance_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_cycle (cycle_id),
    INDEX idx_employee (employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Documents
CREATE TABLE IF NOT EXISTS hr_employee_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    document_type ENUM('contract', 'id_copy', 'passport_copy', 'certificate', 'resume', 'reference_letter', 'medical_report', 'police_clearance', 'tax_document', 'bank_details', 'other') NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    expiry_date DATE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT UNSIGNED,
    verified_at TIMESTAMP NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_type (document_type),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Training & Certifications
CREATE TABLE IF NOT EXISTS hr_employee_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    training_name VARCHAR(200) NOT NULL,
    training_provider VARCHAR(100),
    training_type ENUM('internal', 'external', 'online', 'workshop', 'seminar', 'conference') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    duration_hours INT,
    cost DECIMAL(10,2) DEFAULT 0.00,
    certificate_obtained BOOLEAN DEFAULT FALSE,
    certificate_number VARCHAR(100),
    certificate_path VARCHAR(255),
    expiry_date DATE,
    skills_acquired TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    INDEX idx_employee (employee_id),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Disciplinary Actions
CREATE TABLE IF NOT EXISTS hr_disciplinary_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) UNIQUE NOT NULL,
    employee_id INT NOT NULL,
    incident_date DATE NOT NULL,
    incident_type ENUM('tardiness', 'absenteeism', 'misconduct', 'insubordination', 'policy_violation', 'performance_issue', 'harassment', 'theft', 'other') NOT NULL,
    severity ENUM('verbal_warning', 'written_warning', 'final_warning', 'suspension', 'termination') NOT NULL,
    description TEXT NOT NULL,
    investigation_notes TEXT,
    action_taken TEXT NOT NULL,
    suspension_start_date DATE,
    suspension_end_date DATE,
    suspension_days INT,
    is_paid_suspension BOOLEAN DEFAULT FALSE,
    issued_by INT UNSIGNED NOT NULL,
    issued_date DATE NOT NULL,
    acknowledged_by_employee BOOLEAN DEFAULT FALSE,
    acknowledged_at TIMESTAMP NULL,
    employee_statement TEXT,
    witness_statements TEXT,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date DATE,
    status ENUM('open', 'under_review', 'closed', 'appealed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_employee (employee_id),
    INDEX idx_incident_date (incident_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Loans & Advances
CREATE TABLE IF NOT EXISTS hr_employee_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_number VARCHAR(50) UNIQUE NOT NULL,
    employee_id INT NOT NULL,
    loan_type ENUM('salary_advance', 'emergency_loan', 'education_loan', 'housing_loan', 'other') NOT NULL,
    loan_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) DEFAULT 0.00,
    total_repayable DECIMAL(10,2) NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,
    number_of_installments INT NOT NULL,
    installments_paid INT DEFAULT 0,
    balance_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'active', 'completed', 'defaulted', 'cancelled') DEFAULT 'pending',
    approved_by INT UNSIGNED,
    approved_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Sample Data
-- =====================================================

-- Sample Departments
INSERT INTO hr_departments (department_name, department_code, description) VALUES
('Management', 'DEPT-MGT', 'Executive management and leadership'),
('Finance & Accounting', 'DEPT-FIN', 'Financial operations and accounting'),
('Human Resources', 'DEPT-HR', 'Employee management and development'),
('Operations', 'DEPT-OPS', 'Day-to-day business operations'),
('Food & Beverage', 'DEPT-FB', 'Restaurant, bar, and catering services'),
('Front Office', 'DEPT-FO', 'Guest services and reception'),
('Housekeeping', 'DEPT-HK', 'Room cleaning and maintenance'),
('Security', 'DEPT-SEC', 'Security and safety operations'),
('Sales & Marketing', 'DEPT-SM', 'Sales, marketing, and business development'),
('IT & Technology', 'DEPT-IT', 'Information technology and systems');

-- Sample Positions
INSERT INTO hr_positions (position_title, position_code, department_id, job_level, employment_type, min_salary, max_salary, is_active) VALUES
('General Manager', 'POS-GM', 1, 'executive', 'full_time', 80000, 120000, TRUE),
('Operations Manager', 'POS-OPMGR', 4, 'manager', 'full_time', 50000, 70000, TRUE),
('Accountant', 'POS-ACC', 2, 'mid', 'full_time', 30000, 45000, TRUE),
('HR Manager', 'POS-HRMGR', 3, 'manager', 'full_time', 45000, 60000, TRUE),
('HR Officer', 'POS-HRO', 3, 'mid', 'full_time', 25000, 35000, TRUE),
('Chef', 'POS-CHEF', 5, 'senior', 'full_time', 35000, 50000, TRUE),
('Sous Chef', 'POS-SCHEF', 5, 'mid', 'full_time', 25000, 35000, TRUE),
('Waiter/Waitress', 'POS-WAIT', 5, 'entry', 'full_time', 15000, 20000, TRUE),
('Bartender', 'POS-BART', 5, 'junior', 'full_time', 18000, 25000, TRUE),
('Receptionist', 'POS-RECEP', 6, 'entry', 'full_time', 18000, 25000, TRUE),
('Front Desk Manager', 'POS-FDMGR', 6, 'manager', 'full_time', 35000, 50000, TRUE),
('Housekeeper', 'POS-HK', 7, 'entry', 'full_time', 15000, 20000, TRUE),
('Housekeeping Supervisor', 'POS-HKSUP', 7, 'junior', 'full_time', 20000, 28000, TRUE),
('Security Guard', 'POS-SECG', 8, 'entry', 'full_time', 18000, 24000, TRUE),
('Security Manager', 'POS-SECMGR', 8, 'manager', 'full_time', 35000, 50000, TRUE),
('Sales Executive', 'POS-SALES', 9, 'mid', 'full_time', 25000, 40000, TRUE),
('Marketing Manager', 'POS-MKTMGR', 9, 'manager', 'full_time', 40000, 60000, TRUE),
('IT Support', 'POS-ITSUP', 10, 'junior', 'full_time', 25000, 35000, TRUE),
('System Administrator', 'POS-SYSADM', 10, 'mid', 'full_time', 35000, 50000, TRUE);

-- Sample Leave Types
INSERT INTO hr_leave_types (leave_name, leave_code, description, annual_entitlement_days, requires_approval, is_paid, carry_forward_allowed, max_carry_forward_days, accrual_rate) VALUES
('Annual Leave', 'LEAVE-ANN', 'Annual vacation leave', 21, TRUE, TRUE, TRUE, 5, 1.75),
('Sick Leave', 'LEAVE-SICK', 'Medical sick leave', 14, TRUE, TRUE, FALSE, 0, 1.17),
('Maternity Leave', 'LEAVE-MAT', 'Maternity leave for new mothers', 90, TRUE, TRUE, FALSE, 0, 0.00),
('Paternity Leave', 'LEAVE-PAT', 'Paternity leave for new fathers', 14, TRUE, TRUE, FALSE, 0, 0.00),
('Compassionate Leave', 'LEAVE-COMP', 'Bereavement and family emergency leave', 5, TRUE, TRUE, FALSE, 0, 0.00),
('Study Leave', 'LEAVE-STUDY', 'Educational and examination leave', 10, TRUE, FALSE, FALSE, 0, 0.00),
('Unpaid Leave', 'LEAVE-UNPAID', 'Leave without pay', 0, TRUE, FALSE, FALSE, 0, 0.00);

-- =====================================================
-- Indexes for Performance
-- =====================================================

CREATE INDEX idx_payroll_employee_period ON hr_payroll_details(employee_id, payroll_run_id);
CREATE INDEX idx_leave_employee_dates ON hr_leave_applications(employee_id, start_date, end_date);
CREATE INDEX idx_review_employee_cycle ON hr_performance_reviews(employee_id, cycle_id);

-- =====================================================
-- End of Migration
-- =====================================================
