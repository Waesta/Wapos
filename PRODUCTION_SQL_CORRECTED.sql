-- =====================================================
-- PRODUCTION SQL QUERIES - CORRECTED VERSION
-- Run these in cPanel phpMyAdmin
-- =====================================================

-- =====================================================
-- STEP 1: Add Google Maps API Settings
-- =====================================================
-- Settings table structure: (setting_key, setting_value, description)
-- NO setting_type or setting_group columns

INSERT INTO settings (setting_key, setting_value, description) 
VALUES 
('google_places_api_key', '', 'Google Places API key for address autocomplete'),
('google_routes_api_key', '', 'Google Routes API key for route optimization and distance calculations')
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- =====================================================
-- STEP 2: Check if riders table exists
-- =====================================================
SELECT COUNT(*) as table_exists 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'riders';

-- If returns 0, riders table doesn't exist - skip to STEP 6


-- =====================================================
-- STEP 3: Check riders table structure
-- =====================================================
-- Run this to see actual column names in riders table
DESCRIBE riders;

-- Expected columns in phase2-schema.sql:
-- id, name, phone, vehicle_type, vehicle_number, status, is_active, 
-- total_deliveries, rating, created_at


-- =====================================================
-- STEP 4: Check if user_id column exists in riders
-- =====================================================
SELECT COUNT(*) as column_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'riders'
AND COLUMN_NAME = 'user_id';

-- If returns 0, user_id column doesn't exist - need to add it


-- =====================================================
-- STEP 5: Add user_id column to riders table (if needed)
-- =====================================================
-- Only run this if STEP 4 returned 0

ALTER TABLE riders
ADD COLUMN user_id INT UNSIGNED NULL AFTER id,
ADD CONSTRAINT fk_riders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD INDEX idx_user_id (user_id);


-- =====================================================
-- STEP 6: Create riders table (if doesn't exist)
-- =====================================================
-- Only run if STEP 2 returned 0

CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(50),
    vehicle_make VARCHAR(50),
    vehicle_color VARCHAR(30),
    license_number VARCHAR(50),
    vehicle_plate_photo_url VARCHAR(255),
    current_latitude DECIMAL(10, 8),
    current_longitude DECIMAL(11, 8),
    location_accuracy FLOAT,
    current_speed FLOAT,
    current_heading FLOAT,
    last_location_update DATETIME,
    status ENUM('available', 'busy', 'offline') DEFAULT 'available',
    is_active TINYINT(1) DEFAULT 1,
    total_deliveries INT DEFAULT 0,
    successful_deliveries INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 5.00,
    average_rating DECIMAL(3, 2) DEFAULT 0.00,
    last_delivery_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_location (current_latitude, current_longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- STEP 7: Create rider_location_history table
-- =====================================================
CREATE TABLE IF NOT EXISTS rider_location_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy FLOAT,
    speed FLOAT,
    heading FLOAT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    INDEX idx_rider_time (rider_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- STEP 8: Create delivery_status_history table
-- =====================================================
CREATE TABLE IF NOT EXISTS delivery_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    photo_url VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_delivery (delivery_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- STEP 9: Check existing riders (CORRECTED)
-- =====================================================
-- First check if user_id column exists
SELECT COUNT(*) as has_user_id
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'riders'
AND COLUMN_NAME = 'user_id';

-- If has_user_id = 1, use this query:
SELECT 
    u.username,
    u.full_name,
    u.role,
    u.is_active,
    r.id as rider_id,
    r.phone,
    r.vehicle_type,
    r.vehicle_number,
    r.status
FROM users u
INNER JOIN riders r ON u.id = r.user_id
WHERE u.role = 'rider';

-- If has_user_id = 0, use this query instead:
SELECT 
    r.id as rider_id,
    r.name,
    r.phone,
    r.vehicle_type,
    r.vehicle_number,
    r.status,
    r.is_active
FROM riders r;


-- =====================================================
-- STEP 10: Link existing riders to user accounts
-- =====================================================
-- Only needed if riders exist but don't have user_id set

-- First, check riders without user_id
SELECT * FROM riders WHERE user_id IS NULL;

-- For each rider, create user account and link:
-- Example for rider with id=1, name='John Rider', phone='+254712345678'

-- Create user account
INSERT INTO users (username, password, full_name, email, role, is_active)
VALUES (
    'rider1',  -- Change this
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- Password: 'password'
    'John Rider',  -- Match rider name
    'rider1@example.com',
    'rider',
    1
);

-- Link to rider
UPDATE riders 
SET user_id = LAST_INSERT_ID() 
WHERE id = 1;  -- Change to actual rider id


-- =====================================================
-- STEP 11: Verify everything is set up
-- =====================================================

-- Check settings
SELECT * FROM settings WHERE setting_key LIKE 'google_%';

-- Check riders table structure
DESCRIBE riders;

-- Check if location history table exists
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_location_history';

-- Check if status history table exists
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_status_history';

-- Check riders with user accounts
SELECT 
    r.id,
    r.name,
    r.phone,
    r.vehicle_type,
    r.user_id,
    u.username,
    u.is_active
FROM riders r
LEFT JOIN users u ON r.user_id = u.id;


-- =====================================================
-- NOTES:
-- =====================================================
-- 1. Settings table has: setting_key, setting_value, description
--    (NO setting_type or setting_group)
--
-- 2. Riders table may or may not have user_id column
--    Check first before running queries
--
-- 3. If riders exist without user_id, you need to:
--    - Add user_id column (STEP 5)
--    - Create user accounts for each rider
--    - Link riders to users (STEP 10)
--
-- 4. Password hash '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
--    is for password: 'password'
--    Change this in production!
