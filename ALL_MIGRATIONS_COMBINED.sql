-- =====================================================
-- WAPOS Events & Banquet Management Module
-- Migration: 020_events_banquet_management.sql
-- Description: Complete event booking, venue management, and banquet operations
-- =====================================================

-- Event Venues/Spaces
CREATE TABLE IF NOT EXISTS event_venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(100) NOT NULL,
    venue_code VARCHAR(20) UNIQUE NOT NULL,
    venue_type ENUM('conference_hall', 'banquet_hall', 'garden', 'rooftop', 'poolside', 'meeting_room', 'ballroom', 'outdoor_space') NOT NULL,
    capacity_seated INT DEFAULT 0,
    capacity_standing INT DEFAULT 0,
    area_sqm DECIMAL(10,2),
    location VARCHAR(100),
    floor_level VARCHAR(50),
    description TEXT,
    amenities JSON COMMENT 'AC, projector, sound system, stage, dance floor, etc.',
    hourly_rate DECIMAL(10,2) DEFAULT 0.00,
    half_day_rate DECIMAL(10,2) DEFAULT 0.00,
    full_day_rate DECIMAL(10,2) DEFAULT 0.00,
    setup_time_minutes INT DEFAULT 60,
    teardown_time_minutes INT DEFAULT 60,
    images JSON COMMENT 'Array of image URLs',
    is_active BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_venue_type (venue_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Types & Packages
CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    type_code VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('wedding', 'conference', 'birthday', 'corporate', 'seminar', 'workshop', 'anniversary', 'graduation', 'reunion', 'other') NOT NULL,
    description TEXT,
    default_duration_hours INT DEFAULT 4,
    min_guests INT DEFAULT 1,
    max_guests INT,
    base_price DECIMAL(10,2) DEFAULT 0.00,
    price_per_guest DECIMAL(10,2) DEFAULT 0.00,
    includes JSON COMMENT 'Default inclusions: venue, catering, decoration, etc.',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Bookings
CREATE TABLE IF NOT EXISTS event_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    event_type_id INT,
    venue_id INT NOT NULL,
    customer_id INT UNSIGNED,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20) NOT NULL,
    customer_company VARCHAR(100),
    event_title VARCHAR(200) NOT NULL,
    event_description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    setup_start_time TIME,
    teardown_end_time TIME,
    expected_guests INT NOT NULL,
    actual_guests INT,
    status ENUM('inquiry', 'pending', 'confirmed', 'deposit_paid', 'fully_paid', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'inquiry',
    booking_source ENUM('walk_in', 'phone', 'email', 'website', 'referral', 'agent') DEFAULT 'walk_in',
    special_requests TEXT,
    internal_notes TEXT,
    venue_rate DECIMAL(10,2) DEFAULT 0.00,
    catering_cost DECIMAL(10,2) DEFAULT 0.00,
    decoration_cost DECIMAL(10,2) DEFAULT 0.00,
    equipment_cost DECIMAL(10,2) DEFAULT 0.00,
    other_charges DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_reason VARCHAR(200),
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT 0.00,
    deposit_paid_at TIMESTAMP NULL,
    balance_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'deposit_paid', 'partially_paid', 'fully_paid', 'refunded') DEFAULT 'unpaid',
    payment_terms TEXT,
    cancellation_policy TEXT,
    contract_signed BOOLEAN DEFAULT FALSE,
    contract_signed_at TIMESTAMP NULL,
    contract_file_path VARCHAR(255),
    assigned_coordinator_id INT UNSIGNED COMMENT 'Event coordinator/manager',
    created_by INT UNSIGNED NOT NULL,
    confirmed_by INT UNSIGNED,
    confirmed_at TIMESTAMP NULL,
    cancelled_by INT UNSIGNED,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL,
    FOREIGN KEY (venue_id) REFERENCES event_venues(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_coordinator_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking_number (booking_number),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status),
    INDEX idx_venue (venue_id),
    INDEX idx_customer (customer_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Services & Add-ons
CREATE TABLE IF NOT EXISTS event_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    service_code VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('catering', 'decoration', 'equipment', 'entertainment', 'photography', 'transport', 'accommodation', 'other') NOT NULL,
    description TEXT,
    unit_type ENUM('per_person', 'per_hour', 'per_day', 'per_item', 'flat_rate') NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    min_quantity INT DEFAULT 1,
    max_quantity INT,
    is_active BOOLEAN DEFAULT TRUE,
    supplier_name VARCHAR(100),
    supplier_contact VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Booking Services (Line Items)
CREATE TABLE IF NOT EXISTS event_booking_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    service_id INT,
    service_name VARCHAR(100) NOT NULL,
    service_category VARCHAR(50),
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES event_services(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Payments
CREATE TABLE IF NOT EXISTS event_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    payment_date DATE NOT NULL,
    payment_type ENUM('deposit', 'partial', 'balance', 'full', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'mpesa', 'card', 'bank_transfer', 'cheque', 'other') NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    received_by INT UNSIGNED NOT NULL,
    transaction_id INT COMMENT 'Link to accounting transactions table',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_booking (booking_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Setup Requirements
CREATE TABLE IF NOT EXISTS event_setup_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    setup_type ENUM('seating', 'stage', 'audio_visual', 'lighting', 'decoration', 'catering', 'other') NOT NULL,
    description TEXT NOT NULL,
    quantity INT DEFAULT 1,
    assigned_to INT UNSIGNED COMMENT 'Staff member responsible',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    notes TEXT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Feedback & Reviews
CREATE TABLE IF NOT EXISTS event_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    venue_rating INT CHECK (venue_rating BETWEEN 1 AND 5),
    service_rating INT CHECK (service_rating BETWEEN 1 AND 5),
    food_rating INT CHECK (food_rating BETWEEN 1 AND 5),
    comments TEXT,
    would_recommend BOOLEAN,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Documents
CREATE TABLE IF NOT EXISTS event_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    document_type ENUM('contract', 'invoice', 'receipt', 'floor_plan', 'menu', 'quote', 'other') NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_booking (booking_id),
    INDEX idx_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Activity Log
CREATE TABLE IF NOT EXISTS event_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    performed_by INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_booking (booking_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Sample Data
-- =====================================================

-- Sample Event Venues
INSERT INTO event_venues (venue_name, venue_code, venue_type, capacity_seated, capacity_standing, area_sqm, location, floor_level, description, amenities, hourly_rate, half_day_rate, full_day_rate, setup_time_minutes, teardown_time_minutes) VALUES
('Grand Ballroom', 'BALL-001', 'ballroom', 300, 500, 450.00, 'Main Building', 'Ground Floor', 'Elegant ballroom with crystal chandeliers and hardwood floors', '["AC", "Sound System", "Stage", "Dance Floor", "Projector", "WiFi"]', 15000.00, 80000.00, 150000.00, 120, 90),
('Garden Pavilion', 'GARD-001', 'garden', 150, 250, 600.00, 'Outdoor Area', 'Ground Level', 'Beautiful garden setting with natural shade and fountain', '["Gazebo", "Fairy Lights", "Sound System", "Parking"]', 8000.00, 40000.00, 70000.00, 90, 60),
('Conference Hall A', 'CONF-A01', 'conference_hall', 100, 150, 200.00, 'Business Center', '2nd Floor', 'Modern conference facility with state-of-the-art AV equipment', '["AC", "Projector", "Screen", "Microphones", "WiFi", "Whiteboard"]', 5000.00, 25000.00, 45000.00, 60, 45),
('Rooftop Terrace', 'ROOF-001', 'rooftop', 80, 120, 300.00, 'Main Building', 'Rooftop', 'Stunning city views, perfect for cocktail events', '["Bar Setup", "Lounge Furniture", "Lighting", "Sound System"]', 10000.00, 50000.00, 90000.00, 90, 60),
('Meeting Room 1', 'MEET-001', 'meeting_room', 20, 30, 50.00, 'Business Center', '2nd Floor', 'Intimate boardroom-style meeting space', '["AC", "TV Screen", "WiFi", "Conference Phone"]', 2000.00, 8000.00, 15000.00, 30, 30),
('Poolside Deck', 'POOL-001', 'poolside', 60, 100, 250.00, 'Pool Area', 'Ground Level', 'Relaxed outdoor setting by the pool', '["Umbrellas", "Lounge Chairs", "Bar", "Sound System"]', 6000.00, 30000.00, 55000.00, 60, 45);

-- Sample Event Types
INSERT INTO event_types (type_name, type_code, category, description, default_duration_hours, min_guests, max_guests, base_price, price_per_guest, includes) VALUES
('Wedding Reception', 'WED-REC', 'wedding', 'Complete wedding reception package', 6, 50, 500, 200000.00, 1500.00, '["Venue", "Decoration", "Catering", "Sound System", "Coordinator"]'),
('Corporate Conference', 'CORP-CONF', 'conference', 'Full-day corporate conference package', 8, 20, 200, 80000.00, 800.00, '["Venue", "AV Equipment", "WiFi", "Tea Breaks", "Lunch"]'),
('Birthday Party', 'BIRTH-PARTY', 'birthday', 'Birthday celebration package', 4, 10, 100, 30000.00, 500.00, '["Venue", "Decoration", "Catering", "Entertainment"]'),
('Business Seminar', 'BIZ-SEM', 'seminar', 'Half-day business seminar', 4, 15, 100, 40000.00, 600.00, '["Venue", "Projector", "Refreshments"]'),
('Anniversary Celebration', 'ANNIV', 'anniversary', 'Anniversary party package', 4, 20, 150, 50000.00, 700.00, '["Venue", "Decoration", "Catering", "Music"]'),
('Graduation Party', 'GRAD-PARTY', 'graduation', 'Graduation celebration package', 5, 30, 200, 60000.00, 800.00, '["Venue", "Decoration", "Catering", "Photography"]');

-- Sample Event Services
INSERT INTO event_services (service_name, service_code, category, description, unit_type, unit_price, min_quantity) VALUES
('Buffet Catering - Standard', 'CAT-BUFF-STD', 'catering', 'Standard buffet menu with 3 main courses', 'per_person', 1200.00, 20),
('Buffet Catering - Premium', 'CAT-BUFF-PREM', 'catering', 'Premium buffet with 5 courses and desserts', 'per_person', 2000.00, 20),
('Plated Dinner - 3 Course', 'CAT-PLATE-3', 'catering', 'Formal plated 3-course dinner', 'per_person', 1800.00, 20),
('Cocktail Reception', 'CAT-COCKTAIL', 'catering', 'Cocktails and hors d\'oeuvres', 'per_person', 800.00, 20),
('Floral Decoration - Basic', 'DEC-FLORAL-BAS', 'decoration', 'Basic floral centerpieces and arrangements', 'flat_rate', 15000.00, 1),
('Floral Decoration - Premium', 'DEC-FLORAL-PREM', 'decoration', 'Premium floral design with exotic flowers', 'flat_rate', 35000.00, 1),
('Stage Setup', 'EQUIP-STAGE', 'equipment', 'Professional stage with backdrop', 'flat_rate', 20000.00, 1),
('Sound System - Basic', 'EQUIP-SOUND-BAS', 'equipment', 'Basic PA system with microphones', 'per_day', 8000.00, 1),
('Sound System - Premium', 'EQUIP-SOUND-PREM', 'equipment', 'Professional sound with DJ equipment', 'per_day', 18000.00, 1),
('Projector & Screen', 'EQUIP-PROJ', 'equipment', 'HD projector with large screen', 'per_day', 5000.00, 1),
('Photography - 4 Hours', 'PHOTO-4HR', 'photography', 'Professional photographer for 4 hours', 'flat_rate', 25000.00, 1),
('Photography - Full Day', 'PHOTO-FULL', 'photography', 'Professional photographer full day coverage', 'flat_rate', 45000.00, 1),
('Videography - Highlights', 'VIDEO-HIGH', 'photography', 'Highlight video with editing', 'flat_rate', 35000.00, 1),
('Live Band - 3 Hours', 'ENT-BAND-3HR', 'entertainment', 'Live band performance 3 hours', 'flat_rate', 40000.00, 1),
('DJ Services - 4 Hours', 'ENT-DJ-4HR', 'entertainment', 'Professional DJ with equipment', 'flat_rate', 20000.00, 1),
('MC Services', 'ENT-MC', 'entertainment', 'Professional master of ceremonies', 'per_day', 15000.00, 1),
('Chair Covers & Sashes', 'DEC-CHAIRS', 'decoration', 'Chair covers with decorative sashes', 'per_item', 150.00, 10),
('Table Linens - Premium', 'DEC-LINEN', 'decoration', 'Premium table cloths and napkins', 'per_item', 200.00, 5),
('Lighting - Uplighting', 'EQUIP-LIGHT-UP', 'equipment', 'LED uplighting for ambiance', 'per_day', 12000.00, 1),
('Dance Floor', 'EQUIP-DANCE', 'equipment', 'Portable dance floor installation', 'flat_rate', 15000.00, 1);

-- =====================================================
-- Indexes for Performance
-- =====================================================

-- Additional composite indexes
CREATE INDEX idx_booking_date_status ON event_bookings(event_date, status);
CREATE INDEX idx_booking_venue_date ON event_bookings(venue_id, event_date);
CREATE INDEX idx_payment_booking_date ON event_payments(booking_id, payment_date);

-- =====================================================
-- End of Migration
-- =====================================================
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
-- ============================================================================
-- Migration: Add New User Roles for Events, Security, and HR Modules
-- Version: 023
-- Date: December 17, 2025
-- Description: Adds new roles to support Events & Banquet Management,
--              Security Management, and HR & Employee Management modules
-- ============================================================================

-- Add new roles to users table
ALTER TABLE users 
MODIFY COLUMN role ENUM(
    'super_admin',
    'developer', 
    'admin',
    'manager',
    'cashier',
    'waiter',
    'bartender',
    'accountant',
    'rider',
    'housekeeper',
    'housekeeping_staff',
    'housekeeping_manager',
    'maintenance_staff',
    'maintenance_manager',
    'technician',
    'engineer',
    'frontdesk',
    'receptionist',
    'inventory_manager',
    'security_manager',
    'security_staff',
    'hr_manager',
    'hr_staff',
    'banquet_coordinator'
) NOT NULL DEFAULT 'cashier';

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- New roles added:
-- - security_manager: Full access to security management module
-- - security_staff: Limited access to security operations (own schedule, incidents)
-- - hr_manager: Full access to HR module including payroll approval
-- - hr_staff: Limited HR access (no payroll approval)
-- - banquet_coordinator: Specialized role for events management
-- ============================================================================
-- ============================================================================
-- Migration: Fix Accounting Integration for Events, Security, and HR Modules
-- Version: 024
-- Date: December 17, 2025
-- Description: Adds missing columns and ensures proper accounting integration
-- ============================================================================

-- Fix event_payments table - add transaction_id
-- Check if column exists first, then add if missing
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'event_payments' AND column_name = 'transaction_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE event_payments ADD COLUMN transaction_id INT COMMENT ''Link to accounting transactions table''', 
    'SELECT ''Column transaction_id already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for transaction_id
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'event_payments' AND index_name = 'idx_transaction');

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE event_payments ADD INDEX idx_transaction (transaction_id)', 
    'SELECT ''Index idx_transaction already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Modify payment_date to DATE type if it's TIMESTAMP
ALTER TABLE event_payments 
MODIFY COLUMN payment_date DATE NOT NULL;

-- Add accounting integration for security expenses (optional future use)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'security_incidents' AND column_name = 'expense_transaction_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE security_incidents ADD COLUMN expense_transaction_id INT COMMENT ''Link to accounting for incident expenses''', 
    'SELECT ''Column expense_transaction_id already exists in security_incidents'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add accounting integration for HR payroll (only if table exists)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'hr_payroll_runs');

SET @col_exists = IF(@table_exists > 0,
    (SELECT COUNT(*) FROM information_schema.columns 
        WHERE table_schema = DATABASE() AND table_name = 'hr_payroll_runs' AND column_name = 'expense_transaction_id'),
    1);

SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE hr_payroll_runs ADD COLUMN expense_transaction_id INT COMMENT ''Link to accounting for payroll expenses''', 
    'SELECT ''Skipping hr_payroll_runs - table does not exist or column already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for hr_payroll_runs expense_transaction_id (only if table exists)
SET @idx_exists = IF(@table_exists > 0,
    (SELECT COUNT(*) FROM information_schema.statistics 
        WHERE table_schema = DATABASE() AND table_name = 'hr_payroll_runs' AND index_name = 'idx_expense_transaction'),
    1);

SET @sql = IF(@table_exists > 0 AND @idx_exists = 0, 
    'ALTER TABLE hr_payroll_runs ADD INDEX idx_expense_transaction (expense_transaction_id)', 
    'SELECT ''Skipping hr_payroll_runs index - table does not exist or index already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure all required columns exist in event_booking_services
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'event_booking_services' AND column_name = 'service_name');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE event_booking_services ADD COLUMN service_name VARCHAR(200) NOT NULL AFTER service_id', 
    'SELECT ''Column service_name already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'event_booking_services' AND column_name = 'service_category');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE event_booking_services ADD COLUMN service_category VARCHAR(100) AFTER service_name', 
    'SELECT ''Column service_category already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Changes made:
-- 1. Added transaction_id to event_payments for accounting integration
-- 2. Changed payment_date to DATE type for consistency
-- 3. Added expense tracking columns for future accounting integration
-- 4. Ensured event_booking_services has all required columns
-- ============================================================================
