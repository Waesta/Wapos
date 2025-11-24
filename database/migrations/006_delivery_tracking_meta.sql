-- =====================================================
-- WAPOS Migration 006: Delivery Tracking Metadata
-- Purpose: persist rider speed/heading for real-time map experiences.
-- Date: 2025-11-24
-- Safe: guarded against duplicates.
-- =====================================================

-- Detect required tables
SET @has_riders := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'riders'
);

SET @has_rider_history := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rider_location_history'
);

-- Add live telemetry columns to riders table
SET @rider_speed_sql := IF(
    @has_riders = 0,
    'SELECT "riders table missing"',
    'ALTER TABLE riders
        ADD COLUMN IF NOT EXISTS current_speed FLOAT NULL AFTER location_accuracy,
        ADD COLUMN IF NOT EXISTS current_heading FLOAT NULL AFTER current_speed'
);
PREPARE stmt FROM @rider_speed_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure rider_location_history has speed & heading telemetry fields
SET @history_sql := IF(
    @has_rider_history = 0,
    'SELECT "rider_location_history table missing"',
    'ALTER TABLE rider_location_history
        ADD COLUMN IF NOT EXISTS speed FLOAT NULL AFTER accuracy,
        ADD COLUMN IF NOT EXISTS heading FLOAT NULL AFTER speed'
);
PREPARE stmt FROM @history_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO migrations (migration_name) VALUES ('006_delivery_tracking_meta');
