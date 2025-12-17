-- ============================================================================
-- Migration: Fix Accounting Integration for Events, Security, and HR Modules
-- Version: 024
-- Date: December 17, 2025
-- Description: Adds missing columns and ensures proper accounting integration
-- ============================================================================

-- Fix event_payments table - add transaction_id if not exists
ALTER TABLE event_payments 
ADD COLUMN IF NOT EXISTS transaction_id INT COMMENT 'Link to accounting transactions table';

ALTER TABLE event_payments 
ADD INDEX IF NOT EXISTS idx_transaction (transaction_id);

-- Modify payment_date to DATE type if it's TIMESTAMP
ALTER TABLE event_payments 
MODIFY COLUMN payment_date DATE NOT NULL;

-- Add accounting integration for security expenses (optional future use)
ALTER TABLE security_incidents 
ADD COLUMN IF NOT EXISTS expense_transaction_id INT COMMENT 'Link to accounting for incident expenses';

-- Add accounting integration for HR payroll
ALTER TABLE hr_payroll_runs 
ADD COLUMN IF NOT EXISTS expense_transaction_id INT COMMENT 'Link to accounting for payroll expenses';

ALTER TABLE hr_payroll_runs 
ADD INDEX IF NOT EXISTS idx_expense_transaction (expense_transaction_id);

-- Ensure all required columns exist in event_booking_services
ALTER TABLE event_booking_services 
ADD COLUMN IF NOT EXISTS service_name VARCHAR(200) NOT NULL AFTER service_id;

ALTER TABLE event_booking_services 
ADD COLUMN IF NOT EXISTS service_category VARCHAR(100) AFTER service_name;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Changes made:
-- 1. Added transaction_id to event_payments for accounting integration
-- 2. Changed payment_date to DATE type for consistency
-- 3. Added expense tracking columns for future accounting integration
-- 4. Ensured event_booking_services has all required columns
-- ============================================================================
