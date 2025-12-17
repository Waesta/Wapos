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
