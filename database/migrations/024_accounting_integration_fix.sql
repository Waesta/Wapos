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
