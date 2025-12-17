-- =====================================================
-- WAPOS Security Management Module
-- Migration: 021_security_management.sql
-- Description: Security guard scheduling, patrol tracking, and incident management
-- =====================================================

-- Security Personnel
CREATE TABLE IF NOT EXISTS security_personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED UNIQUE COMMENT 'Link to users table',
    employee_number VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    hire_date DATE NOT NULL,
    termination_date DATE,
    employment_status ENUM('active', 'on_leave', 'suspended', 'terminated') DEFAULT 'active',
    security_clearance_level ENUM('basic', 'standard', 'high', 'top_secret') DEFAULT 'basic',
    license_number VARCHAR(50),
    license_expiry_date DATE,
    uniform_size VARCHAR(20),
    photo_path VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_number (employee_number),
    INDEX idx_status (employment_status),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Shifts
CREATE TABLE IF NOT EXISTS security_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_name VARCHAR(100) NOT NULL,
    shift_code VARCHAR(20) UNIQUE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_hours DECIMAL(4,2) NOT NULL,
    is_overnight BOOLEAN DEFAULT FALSE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Posts/Locations
CREATE TABLE IF NOT EXISTS security_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_name VARCHAR(100) NOT NULL,
    post_code VARCHAR(20) UNIQUE NOT NULL,
    location VARCHAR(200) NOT NULL,
    post_type ENUM('main_gate', 'back_gate', 'reception', 'parking', 'perimeter', 'roving', 'control_room', 'building_entrance', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    requires_armed_guard BOOLEAN DEFAULT FALSE,
    description TEXT,
    special_instructions TEXT,
    equipment_required JSON COMMENT 'Radio, flashlight, keys, etc.',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_post_type (post_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Schedule
CREATE TABLE IF NOT EXISTS security_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    post_id INT NOT NULL,
    shift_id INT NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'no_show', 'cancelled') DEFAULT 'scheduled',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    actual_hours DECIMAL(4,2),
    overtime_hours DECIMAL(4,2) DEFAULT 0.00,
    notes TEXT,
    assigned_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES security_personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES security_posts(id) ON DELETE RESTRICT,
    FOREIGN KEY (shift_id) REFERENCES security_shifts(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_personnel (personnel_id),
    INDEX idx_date (schedule_date),
    INDEX idx_status (status),
    INDEX idx_post_date (post_id, schedule_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patrol Routes
CREATE TABLE IF NOT EXISTS security_patrol_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100) NOT NULL,
    route_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    checkpoints JSON NOT NULL COMMENT 'Array of checkpoint locations',
    estimated_duration_minutes INT NOT NULL,
    frequency_per_shift INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patrol Logs
CREATE TABLE IF NOT EXISTS security_patrol_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    route_id INT NOT NULL,
    personnel_id INT NOT NULL,
    patrol_start_time TIMESTAMP NOT NULL,
    patrol_end_time TIMESTAMP NULL,
    checkpoints_completed JSON COMMENT 'Array of completed checkpoints with timestamps',
    total_checkpoints INT NOT NULL,
    completed_checkpoints INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'incomplete', 'abandoned') DEFAULT 'in_progress',
    observations TEXT,
    issues_found TEXT,
    photos JSON COMMENT 'Array of photo URLs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES security_schedule(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES security_patrol_routes(id) ON DELETE RESTRICT,
    FOREIGN KEY (personnel_id) REFERENCES security_personnel(id) ON DELETE CASCADE,
    INDEX idx_schedule (schedule_id),
    INDEX idx_personnel (personnel_id),
    INDEX idx_start_time (patrol_start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Incidents
CREATE TABLE IF NOT EXISTS security_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_number VARCHAR(50) UNIQUE NOT NULL,
    incident_type ENUM('theft', 'vandalism', 'trespassing', 'assault', 'fire', 'medical_emergency', 'suspicious_activity', 'lost_property', 'found_property', 'vehicle_accident', 'disturbance', 'alarm_activation', 'other') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    incident_date DATE NOT NULL,
    incident_time TIME NOT NULL,
    location VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    reported_by_personnel_id INT,
    reported_by_name VARCHAR(100),
    reported_by_phone VARCHAR(20),
    witnesses JSON COMMENT 'Array of witness details',
    involved_parties JSON COMMENT 'Array of involved persons',
    action_taken TEXT,
    police_notified BOOLEAN DEFAULT FALSE,
    police_report_number VARCHAR(100),
    police_officer_name VARCHAR(100),
    ambulance_called BOOLEAN DEFAULT FALSE,
    fire_department_called BOOLEAN DEFAULT FALSE,
    property_damage BOOLEAN DEFAULT FALSE,
    estimated_damage_value DECIMAL(10,2),
    injuries_reported BOOLEAN DEFAULT FALSE,
    injury_details TEXT,
    evidence_collected JSON COMMENT 'Photos, videos, documents',
    status ENUM('open', 'under_investigation', 'resolved', 'closed', 'escalated') DEFAULT 'open',
    resolution TEXT,
    resolved_by INT UNSIGNED,
    resolved_at TIMESTAMP NULL,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_by_personnel_id) REFERENCES security_personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_incident_number (incident_number),
    INDEX idx_date (incident_date),
    INDEX idx_type (incident_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitor Log
CREATE TABLE IF NOT EXISTS security_visitor_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(100) NOT NULL,
    visitor_id_type ENUM('national_id', 'passport', 'driving_license', 'other') NOT NULL,
    visitor_id_number VARCHAR(50) NOT NULL,
    visitor_phone VARCHAR(20),
    visitor_company VARCHAR(100),
    visit_purpose VARCHAR(200) NOT NULL,
    host_name VARCHAR(100) NOT NULL,
    host_department VARCHAR(100),
    host_phone VARCHAR(20),
    entry_date DATE NOT NULL,
    entry_time TIME NOT NULL,
    exit_date DATE,
    exit_time TIME,
    vehicle_registration VARCHAR(20),
    items_brought_in TEXT,
    items_taken_out TEXT,
    badge_number VARCHAR(20),
    entry_post_id INT,
    exit_post_id INT,
    entry_personnel_id INT,
    exit_personnel_id INT,
    visitor_photo_path VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entry_post_id) REFERENCES security_posts(id) ON DELETE SET NULL,
    FOREIGN KEY (exit_post_id) REFERENCES security_posts(id) ON DELETE SET NULL,
    FOREIGN KEY (entry_personnel_id) REFERENCES security_personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (exit_personnel_id) REFERENCES security_personnel(id) ON DELETE SET NULL,
    INDEX idx_entry_date (entry_date),
    INDEX idx_visitor_name (visitor_name),
    INDEX idx_host_name (host_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Equipment
CREATE TABLE IF NOT EXISTS security_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(100) NOT NULL,
    equipment_code VARCHAR(50) UNIQUE NOT NULL,
    equipment_type ENUM('radio', 'flashlight', 'baton', 'handcuffs', 'first_aid_kit', 'fire_extinguisher', 'keys', 'cctv_camera', 'metal_detector', 'vehicle', 'other') NOT NULL,
    serial_number VARCHAR(100),
    purchase_date DATE,
    warranty_expiry_date DATE,
    assigned_to_personnel_id INT,
    assigned_to_post_id INT,
    condition_status ENUM('excellent', 'good', 'fair', 'poor', 'damaged', 'lost') DEFAULT 'good',
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_personnel_id) REFERENCES security_personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_post_id) REFERENCES security_posts(id) ON DELETE SET NULL,
    INDEX idx_type (equipment_type),
    INDEX idx_assigned_personnel (assigned_to_personnel_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Training Records
CREATE TABLE IF NOT EXISTS security_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    training_name VARCHAR(200) NOT NULL,
    training_type ENUM('orientation', 'first_aid', 'fire_safety', 'conflict_resolution', 'emergency_response', 'customer_service', 'legal_compliance', 'weapons_training', 'other') NOT NULL,
    training_date DATE NOT NULL,
    expiry_date DATE,
    trainer_name VARCHAR(100),
    training_provider VARCHAR(100),
    certificate_number VARCHAR(100),
    certificate_path VARCHAR(255),
    score DECIMAL(5,2),
    passed BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES security_personnel(id) ON DELETE CASCADE,
    INDEX idx_personnel (personnel_id),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Handover Notes
CREATE TABLE IF NOT EXISTS security_handover_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    handover_date DATE NOT NULL,
    shift_id INT NOT NULL,
    post_id INT NOT NULL,
    outgoing_personnel_id INT NOT NULL,
    incoming_personnel_id INT,
    handover_time TIMESTAMP NOT NULL,
    incidents_summary TEXT,
    pending_issues TEXT,
    equipment_status TEXT,
    keys_handed_over JSON,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES security_shifts(id) ON DELETE RESTRICT,
    FOREIGN KEY (post_id) REFERENCES security_posts(id) ON DELETE RESTRICT,
    FOREIGN KEY (outgoing_personnel_id) REFERENCES security_personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (incoming_personnel_id) REFERENCES security_personnel(id) ON DELETE SET NULL,
    INDEX idx_date (handover_date),
    INDEX idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Sample Data
-- =====================================================

-- Sample Security Shifts
INSERT INTO security_shifts (shift_name, shift_code, start_time, end_time, duration_hours, is_overnight, description) VALUES
('Morning Shift', 'SHIFT-MORN', '06:00:00', '14:00:00', 8.00, FALSE, 'Morning security shift'),
('Afternoon Shift', 'SHIFT-AFT', '14:00:00', '22:00:00', 8.00, FALSE, 'Afternoon security shift'),
('Night Shift', 'SHIFT-NIGHT', '22:00:00', '06:00:00', 8.00, TRUE, 'Overnight security shift'),
('Day Shift', 'SHIFT-DAY', '08:00:00', '20:00:00', 12.00, FALSE, 'Extended day shift'),
('Split Shift A', 'SHIFT-SPLIT-A', '06:00:00', '10:00:00', 4.00, FALSE, 'Morning split shift'),
('Split Shift B', 'SHIFT-SPLIT-B', '18:00:00', '22:00:00', 4.00, FALSE, 'Evening split shift');

-- Sample Security Posts
INSERT INTO security_posts (post_name, post_code, location, post_type, priority, requires_armed_guard, description, special_instructions, equipment_required) VALUES
('Main Gate', 'POST-GATE-01', 'Main Entrance', 'main_gate', 'critical', FALSE, 'Primary entrance control point', 'Check all visitor IDs, maintain visitor log', '["Radio", "Flashlight", "Visitor Log Book", "Metal Detector"]'),
('Back Gate', 'POST-GATE-02', 'Service Entrance', 'back_gate', 'high', FALSE, 'Service and delivery entrance', 'Verify all deliveries, check vehicle contents', '["Radio", "Flashlight", "Delivery Log"]'),
('Reception Lobby', 'POST-RECEP', 'Main Building Lobby', 'reception', 'high', FALSE, 'Front desk security support', 'Monitor lobby area, assist guests', '["Radio", "First Aid Kit"]'),
('Parking Area', 'POST-PARK', 'Main Parking Lot', 'parking', 'medium', FALSE, 'Vehicle and parking security', 'Monitor vehicle movements, prevent unauthorized parking', '["Radio", "Flashlight", "Parking Tickets"]'),
('Perimeter North', 'POST-PERIM-N', 'North Boundary', 'perimeter', 'medium', FALSE, 'Northern perimeter patrol', 'Regular patrols every 2 hours', '["Radio", "Flashlight"]'),
('Control Room', 'POST-CONTROL', 'Security Office', 'control_room', 'critical', FALSE, 'Central monitoring and coordination', 'Monitor CCTV, coordinate responses', '["Radio", "CCTV Monitors", "Emergency Phone"]'),
('Building A Entrance', 'POST-BLDG-A', 'Building A Ground Floor', 'building_entrance', 'medium', FALSE, 'Building A access control', 'Check staff IDs after hours', '["Radio", "Access Card Reader"]'),
('Roving Patrol', 'POST-ROVING', 'Entire Premises', 'roving', 'high', FALSE, 'Mobile patrol unit', 'Cover all areas, respond to incidents', '["Radio", "Flashlight", "First Aid Kit", "Vehicle"]');

-- Sample Patrol Routes
INSERT INTO security_patrol_routes (route_name, route_code, description, checkpoints, estimated_duration_minutes, frequency_per_shift) VALUES
('Perimeter Check', 'ROUTE-PERIM', 'Complete perimeter inspection', '["North Gate", "East Fence", "South Boundary", "West Wall", "Back Gate"]', 45, 2),
('Building Interior', 'ROUTE-INT', 'All building floors and common areas', '["Ground Floor Lobby", "1st Floor Corridor", "2nd Floor Corridor", "3rd Floor Corridor", "Stairwells", "Emergency Exits"]', 30, 3),
('Parking & Grounds', 'ROUTE-PARK', 'Parking areas and outdoor spaces', '["Main Parking", "Staff Parking", "Garden Area", "Pool Area", "Service Area"]', 25, 2),
('Night Security Round', 'ROUTE-NIGHT', 'Comprehensive night patrol', '["All Gates", "All Buildings", "Parking Areas", "Perimeter", "Emergency Equipment Check"]', 60, 1);

-- =====================================================
-- Indexes for Performance
-- =====================================================

CREATE INDEX idx_schedule_personnel_date ON security_schedule(personnel_id, schedule_date);
CREATE INDEX idx_incident_date_severity ON security_incidents(incident_date, severity);
CREATE INDEX idx_visitor_entry_exit ON security_visitor_log(entry_date, exit_date);

-- =====================================================
-- End of Migration
-- =====================================================
