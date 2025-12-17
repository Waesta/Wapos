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
