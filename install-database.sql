-- WAPOS Complete Database Installation
-- This script will create all necessary tables and default users

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Drop existing database and recreate
DROP DATABASE IF EXISTS wapos;
CREATE DATABASE wapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wapos;

-- Source all schema files
SOURCE database/schema.sql;
SOURCE database/phase2-schema.sql;
SOURCE database/permissions-schema.sql;
SOURCE database/accounting-schema.sql;
SOURCE database/enhanced-delivery-schema.sql;
SOURCE database/restaurant-receipts-schema.sql;
SOURCE database/void-orders-schema.sql;
SOURCE database/whatsapp-integration-schema.sql;

-- Run migrations
SOURCE database/migrations/010_bar_management.sql;
SOURCE database/migrations/011_housekeeping_inventory.sql;
SOURCE database/migrations/012_bar_tabs_bot.sql;
SOURCE database/migrations/013_happy_hour.sql;
SOURCE database/migrations/016_waiter_item_tracking.sql;
SOURCE database/migrations/017_waiter_assignment.sql;

-- Create default users
-- Password for 'superadmin': thepurpose
-- Password for 'admin': admin
INSERT INTO users (username, password, full_name, email, role, is_active) VALUES
('superadmin', '$2y$10$vqZ3yGxH5YJ5zKxF.Xj8Pu7QxYZJ5K.Xj8Pu7QxYZJ5K.Xj8Pu7QxY', 'Super Administrator', 'superadmin@wapos.local', 'admin', 1),
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@wapos.local', 'admin', 1)
ON DUPLICATE KEY UPDATE username=username;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('business_name', 'WAPOS', 'string', 'Business name'),
('currency_code', 'KES', 'string', 'Currency code'),
('currency_symbol', 'KSh', 'string', 'Currency symbol'),
('tax_rate', '16', 'float', 'Default tax rate percentage')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

SET FOREIGN_KEY_CHECKS=1;

-- Show completion message
SELECT 'Database installation completed successfully!' as Status;
SELECT COUNT(*) as TableCount FROM information_schema.tables WHERE table_schema = 'wapos';
