-- =====================================================
-- WAPOS Migration 005: Delivery Tracking Enhancements
-- Purpose: Enforce rider metadata requirements and ensure
--          delivery coordinates exist for live Google Maps tracking.
-- Date: 2025-11-24
-- Safe: Idempotent guards around every statement
-- =====================================================

-- Ensure riders table exists before proceeding
SET @has_riders := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'riders'
);

SET @has_deliveries := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'deliveries'
);

-- Only run rider alterations when table is present
SET @rider_sql := IF(
    @has_riders = 0,
    'SELECT "riders table missing"',
    'ALTER TABLE riders
        ADD COLUMN IF NOT EXISTS vehicle_make VARCHAR(80) NULL AFTER vehicle_type,
        ADD COLUMN IF NOT EXISTS vehicle_color VARCHAR(40) NULL AFTER vehicle_make,
        ADD COLUMN IF NOT EXISTS license_number VARCHAR(80) NULL AFTER vehicle_number,
        ADD COLUMN IF NOT EXISTS vehicle_plate_photo_url VARCHAR(255) NULL AFTER license_number,
        ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(40) NULL AFTER vehicle_plate_photo_url'
);
PREPARE stmt FROM @rider_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Normalise blank phone and plate values before enforcing NOT NULL
UPDATE riders SET phone = '0000000000' WHERE phone IS NULL OR TRIM(phone) = '';
UPDATE riders SET vehicle_number = 'UNKNOWN' WHERE vehicle_number IS NULL OR TRIM(vehicle_number) = '';

-- Enforce NOT NULL constraints if table exists
SET @rider_notnull_sql := IF(
    @has_riders = 0,
    'SELECT "riders table missing"',
    'ALTER TABLE riders
        MODIFY phone VARCHAR(40) NOT NULL,
        MODIFY vehicle_number VARCHAR(60) NOT NULL'
);
PREPARE stmt FROM @rider_notnull_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure deliveries table has geocoordinates required for Google Maps
SET @delivery_sql := IF(
    @has_deliveries = 0,
    'SELECT "deliveries table missing"',
    'ALTER TABLE deliveries
        ADD COLUMN IF NOT EXISTS delivery_latitude DECIMAL(10,8) NULL AFTER delivery_address,
        ADD COLUMN IF NOT EXISTS delivery_longitude DECIMAL(11,8) NULL AFTER delivery_latitude,
        ADD COLUMN IF NOT EXISTS pickup_latitude DECIMAL(10,8) NULL AFTER pickup_time,
        ADD COLUMN IF NOT EXISTS pickup_longitude DECIMAL(11,8) NULL AFTER pickup_latitude,
        ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL AFTER status,
        ADD COLUMN IF NOT EXISTS picked_up_at DATETIME NULL AFTER assigned_at,
        ADD COLUMN IF NOT EXISTS in_transit_at DATETIME NULL AFTER picked_up_at'
);
PREPARE stmt FROM @delivery_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Indexes to accelerate lookups
SET @delivery_index_sql := IF(
    @has_deliveries = 0,
    'SELECT "deliveries table missing"',
    'ALTER TABLE deliveries
        ADD INDEX IF NOT EXISTS idx_status_created (status, created_at),
        ADD INDEX IF NOT EXISTS idx_delivery_lat_lng (delivery_latitude, delivery_longitude)'
);
PREPARE stmt FROM @delivery_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Record migration execution
INSERT IGNORE INTO migrations (migration_name) VALUES ('005_delivery_tracking_enhancements');
