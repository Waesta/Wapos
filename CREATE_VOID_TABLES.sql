-- =====================================================
-- Create All Void Management Tables
-- Run this in phpMyAdmin
-- =====================================================

-- 1. Create void_reason_codes table
CREATE TABLE IF NOT EXISTS `void_reason_codes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `requires_approval` TINYINT(1) DEFAULT 0,
    `requires_manager_approval` TINYINT(1) DEFAULT 0,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_code` (`code`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default void reasons
INSERT IGNORE INTO `void_reason_codes` (`code`, `display_name`, `description`, `requires_manager_approval`, `display_order`) VALUES
('CUSTOMER_REQUEST', 'Customer Request', 'Customer requested cancellation', 0, 1),
('WRONG_ORDER', 'Wrong Order', 'Wrong order entered', 0, 2),
('PAYMENT_ISSUE', 'Payment Issue', 'Payment processing issue', 1, 3),
('DUPLICATE', 'Duplicate Order', 'Duplicate order', 0, 4),
('OUT_OF_STOCK', 'Out of Stock', 'Item out of stock', 0, 5),
('PRICING_ERROR', 'Pricing Error', 'Pricing error', 1, 6),
('KITCHEN_ERROR', 'Kitchen Error', 'Kitchen preparation error', 0, 7),
('CUSTOMER_NO_SHOW', 'Customer No-Show', 'Customer did not show up', 0, 8),
('MANAGER_OVERRIDE', 'Manager Override', 'Manager override', 1, 9),
('OTHER', 'Other Reason', 'Other reason', 1, 10);

-- 2. Create void_settings table
CREATE TABLE IF NOT EXISTS `void_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `description` VARCHAR(255),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED,
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT IGNORE INTO `void_settings` (`setting_key`, `setting_value`, `description`) VALUES
('require_manager_approval', '1', 'Require manager approval for voids'),
('max_void_amount', '1000', 'Maximum amount that can be voided without approval'),
('void_retention_days', '90', 'Number of days to retain void records'),
('allow_partial_void', '1', 'Allow partial order voids'),
('require_reason_code', '1', 'Require reason code for all voids');

-- 3. Create void_transactions table
CREATE TABLE IF NOT EXISTS `void_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `order_type` ENUM('sale', 'restaurant_order', 'delivery') DEFAULT 'sale',
    `void_reason_code` VARCHAR(50) NOT NULL,
    `void_reason_notes` TEXT,
    `original_total` DECIMAL(15,2) NOT NULL,
    `voided_by_user_id` INT UNSIGNED NOT NULL,
    `manager_user_id` INT UNSIGNED,
    `void_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_void_timestamp` (`void_timestamp`),
    INDEX `idx_voided_by` (`voided_by_user_id`),
    INDEX `idx_reason_code` (`void_reason_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
