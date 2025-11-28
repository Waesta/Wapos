-- Comprehensive User Permissions Module Schema
-- This creates a granular permission system with groups, roles, and audit trails

-- Permission Groups (like departments or job functions)
CREATE TABLE IF NOT EXISTS permission_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- System Modules (POS, Inventory, Reports, etc.)
CREATE TABLE IF NOT EXISTS system_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    module_key VARCHAR(50) NOT NULL UNIQUE,
    parent_module_id INT UNSIGNED,
    icon VARCHAR(50) DEFAULT 'bi-circle',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_module_id) REFERENCES system_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- System Actions (create, read, update, delete, execute, etc.)
CREATE TABLE IF NOT EXISTS system_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    action_key VARCHAR(50) NOT NULL UNIQUE,
    is_sensitive TINYINT(1) DEFAULT 0,
    requires_approval TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Module Actions (which actions are available for each module)
CREATE TABLE IF NOT EXISTS module_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    action_id INT UNSIGNED NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_module_action (module_id, action_id),
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES system_actions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User Group Memberships (users can belong to multiple groups)
CREATE TABLE IF NOT EXISTS user_group_memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_user_group (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Group Permissions (permissions assigned to groups)
CREATE TABLE IF NOT EXISTS group_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    action_id INT UNSIGNED NOT NULL,
    is_granted TINYINT(1) DEFAULT 1,
    conditions JSON,
    granted_by INT UNSIGNED,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group_permission (group_id, module_id, action_id),
    FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES system_actions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- User Permissions (individual permissions that override group permissions)
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    action_id INT UNSIGNED NOT NULL,
    is_granted TINYINT(1) DEFAULT 1,
    permission_type ENUM('allow', 'deny') DEFAULT 'allow',
    conditions JSON,
    granted_by INT UNSIGNED,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    reason TEXT,
    UNIQUE KEY unique_user_permission (user_id, module_id, action_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES system_actions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Permission Audit Log
CREATE TABLE IF NOT EXISTS permission_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    target_user_id INT UNSIGNED,
    action_type ENUM('login_attempt', 'login_success', 'login_failure', 'logout', 'permission_check', 'permission_denied', 'permission_granted', 'permission_changed', 'sensitive_action', 'policy_violation') NOT NULL,
    module_id INT UNSIGNED,
    action_id INT UNSIGNED,
    resource_type VARCHAR(100),
    resource_id INT UNSIGNED,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(100),
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_target_user_id (target_user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_risk_level (risk_level),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE SET NULL,
    FOREIGN KEY (action_id) REFERENCES system_actions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Session Management
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    location_id INT UNSIGNED,
    device_fingerprint VARCHAR(255),
    two_factor_verified TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Two-Factor Authentication
CREATE TABLE IF NOT EXISTS user_two_factor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    secret_key VARCHAR(255) NOT NULL,
    backup_codes JSON,
    is_enabled TINYINT(1) DEFAULT 0,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Permission Templates (for easy role cloning)
CREATE TABLE IF NOT EXISTS permission_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    template_data JSON NOT NULL,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insert Default System Modules
INSERT INTO system_modules (name, display_name, description, module_key, icon, sort_order) VALUES
('dashboard', 'Dashboard', 'Main dashboard and overview', 'dashboard', 'bi-speedometer2', 1),
('pos', 'Point of Sale', 'Retail POS operations', 'pos', 'bi-cart-plus', 2),
('restaurant', 'Restaurant', 'Restaurant operations and orders', 'restaurant', 'bi-shop', 3),
('rooms', 'Room Management', 'Hotel room booking and management', 'rooms', 'bi-building', 4),
('delivery', 'Delivery', 'Delivery management and tracking', 'delivery', 'bi-truck', 5),
('products', 'Products', 'Product and inventory management', 'products', 'bi-box-seam', 6),
('sales', 'Sales', 'Sales history and management', 'sales', 'bi-receipt', 7),
('customers', 'Customers', 'Customer management', 'customers', 'bi-people', 8),
('reports', 'Reports', 'Business reports and analytics', 'reports', 'bi-graph-up', 9),
('accounting', 'Accounting', 'Financial accounting and expenses', 'accounting', 'bi-calculator', 10),
('users', 'User Management', 'User and permission management', 'users', 'bi-person-badge', 11),
('settings', 'Settings', 'System settings and configuration', 'settings', 'bi-gear', 12),
('locations', 'Locations', 'Multi-location management', 'locations', 'bi-geo-alt', 13),
('manage_tables', 'Manage Tables', 'Restaurant table management', 'manage_tables', 'bi-table', 14),
('manage_rooms', 'Manage Rooms', 'Room type and room management', 'manage_rooms', 'bi-door-open', 15)
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Insert Comprehensive System Actions for Professional POS
INSERT INTO system_actions (name, display_name, description, action_key, is_sensitive, requires_approval) VALUES
-- Basic CRUD Operations
('view', 'View/Read', 'View and read data', 'view', 0, 0),
('create', 'Create/Add', 'Create new records', 'create', 0, 0),
('update', 'Edit/Update', 'Modify existing records', 'update', 0, 0),
('delete', 'Delete/Remove', 'Delete records', 'delete', 1, 1),

-- POS Operations
('void', 'Void Transaction', 'Void sales and transactions', 'void', 1, 1),
('refund', 'Process Refunds', 'Process customer refunds', 'refund', 1, 1),
('discount', 'Apply Discounts', 'Apply discounts to sales', 'discount', 1, 0),
('override_price', 'Override Prices', 'Override product prices', 'override_price', 1, 1),
('manage_cash', 'Manage Cash Drawer', 'Open/close cash drawer', 'manage_cash', 1, 0),
('split_payment', 'Split Payments', 'Process split payments', 'split_payment', 0, 0),
('layaway', 'Layaway/Hold', 'Create layaway transactions', 'layaway', 0, 0),

-- Inventory Operations
('adjust_inventory', 'Adjust Inventory', 'Make inventory adjustments', 'adjust_inventory', 1, 1),
('transfer_stock', 'Transfer Stock', 'Transfer inventory between locations', 'transfer_stock', 1, 0),
('receive_stock', 'Receive Stock', 'Receive inventory shipments', 'receive_stock', 0, 0),
('count_inventory', 'Count Inventory', 'Perform inventory counts', 'count_inventory', 0, 0),

-- Restaurant Operations
('modify_order', 'Modify Orders', 'Modify restaurant orders', 'modify_order', 0, 0),
('kitchen_display', 'Kitchen Display', 'Access kitchen display system', 'kitchen_display', 0, 0),
('table_management', 'Table Management', 'Manage restaurant tables', 'table_management', 0, 0),

-- Customer Operations
('loyalty_points', 'Loyalty Points', 'Manage customer loyalty points', 'loyalty_points', 0, 0),
('customer_credit', 'Customer Credit', 'Manage customer credit accounts', 'customer_credit', 1, 1),
('send_receipts', 'Send Receipts', 'Email/SMS receipts to customers', 'send_receipts', 0, 0),

-- Reporting & Analytics
('view_reports', 'View Reports', 'Access business reports', 'view_reports', 0, 0),
('financial_reports', 'Financial Reports', 'Access financial reports', 'financial_reports', 1, 0),
('export', 'Export Data', 'Export reports and data', 'export', 0, 0),
('print', 'Print', 'Print receipts and reports', 'print', 0, 0),

-- Administrative Operations
('manage_users', 'Manage Users', 'Create and manage user accounts', 'manage_users', 1, 1),
('change_permissions', 'Change Permissions', 'Modify user permissions', 'change_permissions', 1, 1),
('system_settings', 'System Settings', 'Modify system configuration', 'system_settings', 1, 1),
('backup_restore', 'Backup/Restore', 'Perform system backup and restore', 'backup_restore', 1, 1),
('audit_logs', 'Audit Logs', 'View system audit logs', 'audit_logs', 1, 0),

-- Multi-location Operations
('location_reports', 'Location Reports', 'View location-specific reports', 'location_reports', 0, 0),
('cross_location', 'Cross Location', 'Access multiple locations', 'cross_location', 1, 0),

-- Advanced Features
('api_access', 'API Access', 'Access system APIs', 'api_access', 1, 1),
('integrations', 'Integrations', 'Manage third-party integrations', 'integrations', 1, 1),
('tax_management', 'Tax Management', 'Manage tax settings and calculations', 'tax_management', 1, 1)
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Create comprehensive Module-Action relationships for professional POS system
INSERT INTO module_actions (module_id, action_id, is_default) 
SELECT m.id, a.id, 
    CASE 
        WHEN a.action_key IN ('view', 'create', 'update') THEN 1 
        ELSE 0 
    END as is_default
FROM system_modules m 
CROSS JOIN system_actions a
WHERE 
    -- Dashboard Module
    (m.module_key = 'dashboard' AND a.action_key IN ('view', 'export', 'print'))
    
    -- POS Module - Complete retail operations
    OR (m.module_key = 'pos' AND a.action_key IN ('view', 'create', 'update', 'delete', 'void', 'refund', 'discount', 'override_price', 'print', 'manage_cash', 'export', 'split_payment', 'layaway', 'send_receipts', 'loyalty_points', 'customer_credit'))
    
    -- Restaurant Module - Complete food service operations  
    OR (m.module_key = 'restaurant' AND a.action_key IN ('view', 'create', 'update', 'delete', 'void', 'print', 'export', 'modify_order', 'kitchen_display', 'table_management', 'send_receipts'))
    
    -- Rooms Module - Complete hotel/accommodation management
    OR (m.module_key = 'rooms' AND a.action_key IN ('view', 'create', 'update', 'delete', 'print', 'export', 'send_receipts', 'customer_credit'))
    
    -- Delivery Module - Complete order delivery and logistics
    OR (m.module_key = 'delivery' AND a.action_key IN ('view', 'create', 'update', 'delete', 'print', 'export', 'send_receipts', 'location_reports'))
    
    -- Products Module - Complete inventory and product management
    OR (m.module_key = 'products' AND a.action_key IN ('view', 'create', 'update', 'delete', 'adjust_inventory', 'export', 'print', 'transfer_stock', 'receive_stock', 'count_inventory'))
    
    -- Sales Module - Complete sales history and analysis
    OR (m.module_key = 'sales' AND a.action_key IN ('view', 'void', 'refund', 'export', 'print', 'view_reports', 'financial_reports'))
    
    -- Customers Module - Complete customer relationship management
    OR (m.module_key = 'customers' AND a.action_key IN ('view', 'create', 'update', 'delete', 'export', 'print', 'loyalty_points', 'customer_credit', 'send_receipts'))
    
    -- Reports Module - Complete business intelligence and analytics
    OR (m.module_key = 'reports' AND a.action_key IN ('view', 'view_reports', 'financial_reports', 'export', 'print', 'location_reports'))
    
    -- Accounting Module - Complete financial management
    OR (m.module_key = 'accounting' AND a.action_key IN ('view', 'create', 'update', 'delete', 'export', 'print', 'view_reports', 'financial_reports', 'tax_management'))
    
    -- Users Module - Complete user account management
    OR (m.module_key = 'users' AND a.action_key IN ('view', 'create', 'update', 'delete', 'manage_users', 'export', 'change_permissions', 'audit_logs'))
    
    -- Settings Module - Complete system configuration
    OR (m.module_key = 'settings' AND a.action_key IN ('view', 'update', 'system_settings', 'backup_restore', 'integrations', 'tax_management', 'api_access'))
    
    -- Locations Module - Complete multi-location management
    OR (m.module_key = 'locations' AND a.action_key IN ('view', 'create', 'update', 'delete', 'export', 'location_reports', 'cross_location', 'transfer_stock'))
    
    -- Manage Tables Module - Complete restaurant table management
    OR (m.module_key = 'manage_tables' AND a.action_key IN ('view', 'create', 'update', 'delete', 'export', 'table_management'))
    
    -- Manage Rooms Module - Complete room type and room management
    OR (m.module_key = 'manage_rooms' AND a.action_key IN ('view', 'create', 'update', 'delete', 'export'))
ON DUPLICATE KEY UPDATE is_default = VALUES(is_default);

-- Insert Default Permission Groups
INSERT INTO permission_groups (name, description, color) VALUES
('Super Administrators', 'Full system access with all permissions', '#dc3545'),
('Store Managers', 'Full operational access with reporting capabilities', '#28a745'),
('Shift Supervisors', 'Limited management access for shift operations', '#ffc107'),
('Cashiers', 'Basic POS operations and customer service', '#17a2b8'),
('Waiters', 'Restaurant service and order management', '#6f42c1'),
('Kitchen Staff', 'Kitchen operations and order fulfillment', '#fd7e14'),
('Delivery Personnel', 'Delivery operations and order tracking', '#20c997'),
('Inventory Managers', 'Product and inventory management', '#6c757d'),
('Accountants', 'Financial reporting and accounting access', '#343a40'),
('Maintenance Staff', 'Limited access for system maintenance', '#e83e8c')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Create indexes for performance
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_expires_at ON user_sessions(expires_at);
CREATE INDEX idx_group_permissions_group_id ON group_permissions(group_id);
CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_group_memberships_user_id ON user_group_memberships(user_id);
CREATE INDEX idx_permission_audit_log_user_action ON permission_audit_log(user_id, action_type, created_at);
