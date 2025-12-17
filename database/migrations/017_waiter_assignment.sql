-- Migration: Waiter Assignment Enhancement
-- Adds opened_by column to track who created the tab (cashier) vs who owns it (waiter)
-- Also adds default waiter settings

-- Add opened_by column to bar_tabs if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'bar_tabs';
SET @columnname = 'opened_by';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE bar_tabs ADD COLUMN opened_by INT UNSIGNED NULL AFTER server_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key for opened_by if not exists
SET @fkname = 'fk_bar_tabs_opened_by';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @fkname) > 0,
    'SELECT 1',
    'ALTER TABLE bar_tabs ADD CONSTRAINT fk_bar_tabs_opened_by FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insert default waiter assignment settings if they don't exist
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
    ('waiter_assignment_mode', 'self', 'string', 'Waiter assignment mode: self, cashier, or both'),
    ('require_waiter_on_tab', '0', 'bool', 'Require waiter selection when creating tabs'),
    ('show_waiter_filter', '0', 'bool', 'Show waiter filter on tabs list'),
    ('waiter_commission_enabled', '0', 'bool', 'Enable waiter commission tracking'),
    ('waiter_commission_rate', '0', 'float', 'Default waiter commission percentage')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add commission_rate column to users table for individual waiter rates
SET @tablename = 'users';
SET @columnname = 'commission_rate';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN commission_rate DECIMAL(5,2) NULL DEFAULT NULL COMMENT ''Individual commission rate override'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
