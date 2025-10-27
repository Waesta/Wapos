<?php
/**
 * System Manager - Clean and Stable
 * Simple data access with error handling
 */

class SystemManager {
    private static $instance = null;
    private $db;
    private $cache = [];
    
    private function __construct() {
        $this->db = Database::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get system modules with error handling
     */
    public function getSystemModules() {
        try {
            if (!isset($this->cache['modules'])) {
                $this->cache['modules'] = $this->db->fetchAll(
                    "SELECT * FROM system_modules WHERE is_active = 1 ORDER BY sort_order"
                ) ?: [];
            }
            return $this->cache['modules'];
        } catch (Exception $e) {
            error_log('SystemManager getSystemModules error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ensure system has required data
     */
    private function ensureSystemData() {
        // Check if we have modules
        $moduleCount = $this->getCachedCount('system_modules');
        if ($moduleCount === 0) {
            $this->populateSystemModules();
        }
        
        // Check if we have actions
        $actionCount = $this->getCachedCount('system_actions');
        if ($actionCount === 0) {
            $this->populateSystemActions();
        }
        
        // Check if we have module-action relationships
        $relationshipCount = $this->getCachedCount('module_actions');
        if ($relationshipCount === 0) {
            $this->populateModuleActions();
        }
        
        // Check if we have permission groups
        $groupCount = $this->getCachedCount('permission_groups');
        if ($groupCount === 0) {
            $this->populatePermissionGroups();
        }
    }
    
    /**
     * Get cached count or fetch from database
     */
    private function getCachedCount($table) {
        $cacheKey = "count_{$table}";
        
        if (!isset($this->cache[$cacheKey])) {
            try {
                $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
                $this->cache[$cacheKey] = $result ? (int)$result['count'] : 0;
            } catch (Exception $e) {
                $this->cache[$cacheKey] = 0;
            }
        }
        
        return $this->cache[$cacheKey];
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        try {
            $result = $this->db->fetchOne("SHOW TABLES LIKE ?", [$tableName]);
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create missing tables by running schema
     */
    private function createMissingTables() {
        $schemaFiles = [
            __DIR__ . '/../database/permissions-schema.sql'
        ];
        
        foreach ($schemaFiles as $schemaFile) {
            if (file_exists($schemaFile)) {
                $this->runSqlFile($schemaFile);
            }
        }
        
        // Clear cache after creating tables
        $this->clearCache();
    }
    
    /**
     * Populate system modules
     */
    private function populateSystemModules() {
        $modules = [
            ['dashboard', 'Dashboard', 'Main dashboard and overview', 'dashboard', 'bi-speedometer2', 1],
            ['pos', 'Point of Sale', 'Retail POS operations', 'pos', 'bi-cart-plus', 2],
            ['restaurant', 'Restaurant', 'Restaurant operations and orders', 'restaurant', 'bi-shop', 3],
            ['rooms', 'Room Management', 'Hotel room booking and management', 'rooms', 'bi-building', 4],
            ['delivery', 'Delivery', 'Delivery management and tracking', 'delivery', 'bi-truck', 5],
            ['products', 'Products', 'Product and inventory management', 'products', 'bi-box-seam', 6],
            ['sales', 'Sales', 'Sales history and management', 'sales', 'bi-receipt', 7],
            ['customers', 'Customers', 'Customer management', 'customers', 'bi-people', 8],
            ['reports', 'Reports', 'Business reports and analytics', 'reports', 'bi-graph-up', 9],
            ['accounting', 'Accounting', 'Financial accounting and expenses', 'accounting', 'bi-calculator', 10],
            ['users', 'User Management', 'User and permission management', 'users', 'bi-person-badge', 11],
            ['settings', 'Settings', 'System settings and configuration', 'settings', 'bi-gear', 12],
            ['locations', 'Locations', 'Multi-location management', 'locations', 'bi-geo-alt', 13],
            ['manage_tables', 'Manage Tables', 'Restaurant table management', 'manage_tables', 'bi-table', 14],
            ['manage_rooms', 'Manage Rooms', 'Room type and room management', 'manage_rooms', 'bi-door-open', 15]
        ];
        
        foreach ($modules as $module) {
            $this->db->query(
                "INSERT IGNORE INTO system_modules (name, display_name, description, module_key, icon, sort_order, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                $module
            );
        }
        
        $this->clearCache();
    }
    
    /**
     * Populate system actions
     */
    private function populateSystemActions() {
        $actions = [
            ['view', 'View/Read', 'View and read data', 'view', 0, 0],
            ['create', 'Create/Add', 'Create new records', 'create', 0, 0],
            ['update', 'Edit/Update', 'Modify existing records', 'update', 0, 0],
            ['delete', 'Delete/Remove', 'Delete records', 'delete', 1, 1],
            ['void', 'Void Transaction', 'Void sales and transactions', 'void', 1, 1],
            ['refund', 'Process Refunds', 'Process customer refunds', 'refund', 1, 1],
            ['discount', 'Apply Discounts', 'Apply discounts to sales', 'discount', 1, 0],
            ['override_price', 'Override Prices', 'Override product prices', 'override_price', 1, 1],
            ['manage_cash', 'Manage Cash Drawer', 'Open/close cash drawer', 'manage_cash', 1, 0],
            ['split_payment', 'Split Payments', 'Process split payments', 'split_payment', 0, 0],
            ['layaway', 'Layaway/Hold', 'Create layaway transactions', 'layaway', 0, 0],
            ['adjust_inventory', 'Adjust Inventory', 'Make inventory adjustments', 'adjust_inventory', 1, 1],
            ['transfer_stock', 'Transfer Stock', 'Transfer inventory between locations', 'transfer_stock', 1, 0],
            ['receive_stock', 'Receive Stock', 'Receive inventory shipments', 'receive_stock', 0, 0],
            ['count_inventory', 'Count Inventory', 'Perform inventory counts', 'count_inventory', 0, 0],
            ['modify_order', 'Modify Orders', 'Modify restaurant orders', 'modify_order', 0, 0],
            ['kitchen_display', 'Kitchen Display', 'Access kitchen display system', 'kitchen_display', 0, 0],
            ['table_management', 'Table Management', 'Manage restaurant tables', 'table_management', 0, 0],
            ['loyalty_points', 'Loyalty Points', 'Manage customer loyalty points', 'loyalty_points', 0, 0],
            ['customer_credit', 'Customer Credit', 'Manage customer credit accounts', 'customer_credit', 1, 1],
            ['send_receipts', 'Send Receipts', 'Email/SMS receipts to customers', 'send_receipts', 0, 0],
            ['view_reports', 'View Reports', 'Access business reports', 'view_reports', 0, 0],
            ['financial_reports', 'Financial Reports', 'Access financial reports', 'financial_reports', 1, 0],
            ['export', 'Export Data', 'Export reports and data', 'export', 0, 0],
            ['print', 'Print', 'Print receipts and reports', 'print', 0, 0],
            ['manage_users', 'Manage Users', 'Create and manage user accounts', 'manage_users', 1, 1],
            ['change_permissions', 'Change Permissions', 'Modify user permissions', 'change_permissions', 1, 1],
            ['system_settings', 'System Settings', 'Modify system configuration', 'system_settings', 1, 1],
            ['backup_restore', 'Backup/Restore', 'Perform system backup and restore', 'backup_restore', 1, 1],
            ['audit_logs', 'Audit Logs', 'View system audit logs', 'audit_logs', 1, 0],
            ['location_reports', 'Location Reports', 'View location-specific reports', 'location_reports', 0, 0],
            ['cross_location', 'Cross Location', 'Access multiple locations', 'cross_location', 1, 0],
            ['api_access', 'API Access', 'Access system APIs', 'api_access', 1, 1],
            ['integrations', 'Integrations', 'Manage third-party integrations', 'integrations', 1, 1],
            ['tax_management', 'Tax Management', 'Manage tax settings and calculations', 'tax_management', 1, 1]
        ];
        
        foreach ($actions as $action) {
            $this->db->query(
                "INSERT IGNORE INTO system_actions (name, display_name, description, action_key, is_sensitive, requires_approval) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                $action
            );
        }
        
        $this->clearCache();
    }
    
    /**
     * Populate module-action relationships
     */
    private function populateModuleActions() {
        // Get all modules and actions
        $modules = $this->db->fetchAll("SELECT id, module_key FROM system_modules");
        $actions = $this->db->fetchAll("SELECT id, action_key FROM system_actions");
        
        // Define relationships
        $relationships = [
            'dashboard' => ['view', 'export', 'print'],
            'pos' => ['view', 'create', 'update', 'delete', 'void', 'refund', 'discount', 'override_price', 'print', 'manage_cash', 'export', 'split_payment', 'layaway', 'send_receipts', 'loyalty_points', 'customer_credit'],
            'restaurant' => ['view', 'create', 'update', 'delete', 'void', 'print', 'export', 'modify_order', 'kitchen_display', 'table_management', 'send_receipts'],
            'rooms' => ['view', 'create', 'update', 'delete', 'print', 'export', 'send_receipts', 'customer_credit'],
            'delivery' => ['view', 'create', 'update', 'delete', 'print', 'export', 'send_receipts', 'location_reports'],
            'products' => ['view', 'create', 'update', 'delete', 'adjust_inventory', 'export', 'print', 'transfer_stock', 'receive_stock', 'count_inventory'],
            'sales' => ['view', 'void', 'refund', 'export', 'print', 'view_reports', 'financial_reports'],
            'customers' => ['view', 'create', 'update', 'delete', 'export', 'print', 'loyalty_points', 'customer_credit', 'send_receipts'],
            'reports' => ['view', 'view_reports', 'financial_reports', 'export', 'print', 'location_reports'],
            'accounting' => ['view', 'create', 'update', 'delete', 'export', 'print', 'view_reports', 'financial_reports', 'tax_management'],
            'users' => ['view', 'create', 'update', 'delete', 'manage_users', 'export', 'change_permissions', 'audit_logs'],
            'settings' => ['view', 'update', 'system_settings', 'backup_restore', 'integrations', 'tax_management', 'api_access'],
            'locations' => ['view', 'create', 'update', 'delete', 'export', 'location_reports', 'cross_location', 'transfer_stock'],
            'manage_tables' => ['view', 'create', 'update', 'delete', 'export', 'table_management'],
            'manage_rooms' => ['view', 'create', 'update', 'delete', 'export']
        ];
        
        // Create module and action lookup arrays
        $moduleMap = [];
        foreach ($modules as $module) {
            $moduleMap[$module['module_key']] = $module['id'];
        }
        
        $actionMap = [];
        foreach ($actions as $action) {
            $actionMap[$action['action_key']] = $action['id'];
        }
        
        // Insert relationships
        foreach ($relationships as $moduleKey => $actionKeys) {
            if (!isset($moduleMap[$moduleKey])) continue;
            
            $moduleId = $moduleMap[$moduleKey];
            
            foreach ($actionKeys as $actionKey) {
                if (!isset($actionMap[$actionKey])) continue;
                
                $actionId = $actionMap[$actionKey];
                $isDefault = in_array($actionKey, ['view', 'create', 'update']) ? 1 : 0;
                
                $this->db->query(
                    "INSERT IGNORE INTO module_actions (module_id, action_id, is_default) VALUES (?, ?, ?)",
                    [$moduleId, $actionId, $isDefault]
                );
            }
        }
        
        $this->clearCache();
    }
    
    /**
     * Populate permission groups
     */
    private function populatePermissionGroups() {
        $groups = [
            ['Super Administrators', 'Full system access with all permissions', '#dc3545'],
            ['Store Managers', 'Full operational access with reporting capabilities', '#28a745'],
            ['Shift Supervisors', 'Limited management access for shift operations', '#ffc107'],
            ['Cashiers', 'Basic POS operations and customer service', '#17a2b8'],
            ['Waiters', 'Restaurant service and order management', '#6f42c1'],
            ['Kitchen Staff', 'Kitchen operations and order fulfillment', '#fd7e14'],
            ['Delivery Personnel', 'Delivery operations and order tracking', '#20c997'],
            ['Inventory Managers', 'Product and inventory management', '#6c757d'],
            ['Accountants', 'Financial reporting and accounting access', '#343a40'],
            ['Maintenance Staff', 'Limited access for system maintenance', '#e83e8c']
        ];
        
        foreach ($groups as $group) {
            $this->db->query(
                "INSERT IGNORE INTO permission_groups (name, description, color, is_active, created_at) 
                 VALUES (?, ?, ?, 1, NOW())",
                $group
            );
        }
        
        $this->clearCache();
        
        // Ensure admin users have full permissions
        $this->ensureAdminPermissions();
    }
    
    /**
     * Ensure admin and developer users have full permissions
     */
    private function ensureAdminPermissions() {
        try {
            // Get admin group
            $adminGroup = $this->db->fetchOne("SELECT id FROM permission_groups WHERE name = 'Super Administrators'");
            if (!$adminGroup) {
                // Create admin group if it doesn't exist
                $adminGroupId = $this->db->insert('permission_groups', [
                    'name' => 'Super Administrators',
                    'description' => 'Full system access with all permissions',
                    'color' => '#dc3545',
                    'is_active' => 1
                ]);
            } else {
                $adminGroupId = $adminGroup['id'];
            }
            
            // Get all admin and developer users
            $adminUsers = $this->db->fetchAll("SELECT id FROM users WHERE role IN ('admin', 'developer') AND is_active = 1");
            
            foreach ($adminUsers as $user) {
                // Add user to admin group if not already there
                $membership = $this->db->fetchOne(
                    "SELECT id FROM user_group_memberships WHERE user_id = ? AND group_id = ? AND is_active = 1",
                    [$user['id'], $adminGroupId]
                );
                
                if (!$membership) {
                    $this->db->insert('user_group_memberships', [
                        'user_id' => $user['id'],
                        'group_id' => $adminGroupId,
                        'assigned_by' => 1, // System
                        'is_active' => 1
                    ]);
                }
            }
            
            // Grant all permissions to admin group
            $this->grantAllPermissionsToGroup($adminGroupId);
            
        } catch (Exception $e) {
            error_log('[WAPOS SystemManager] Admin permissions error: ' . $e->getMessage());
        }
    }
    
    /**
     * Grant all permissions to a group
     */
    private function grantAllPermissionsToGroup($groupId) {
        try {
            // Get all module-action combinations
            $moduleActions = $this->db->fetchAll("
                SELECT ma.module_id, ma.action_id 
                FROM module_actions ma
                JOIN system_modules sm ON ma.module_id = sm.id
                JOIN system_actions sa ON ma.action_id = sa.id
                WHERE sm.is_active = 1
            ");
            
            foreach ($moduleActions as $ma) {
                // Check if permission already exists
                $existing = $this->db->fetchOne(
                    "SELECT id FROM group_permissions WHERE group_id = ? AND module_id = ? AND action_id = ?",
                    [$groupId, $ma['module_id'], $ma['action_id']]
                );
                
                if (!$existing) {
                    $this->db->insert('group_permissions', [
                        'group_id' => $groupId,
                        'module_id' => $ma['module_id'],
                        'action_id' => $ma['action_id'],
                        'is_granted' => 1,
                        'granted_by' => 1 // System
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log('[WAPOS SystemManager] Grant permissions error: ' . $e->getMessage());
        }
    }
    
    /**
     * Run SQL file
     */
    private function runSqlFile($filePath) {
        if (!file_exists($filePath)) return;
        
        $sql = file_get_contents($filePath);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $this->db->query($statement);
                } catch (Exception $e) {
                    // Log but don't stop for duplicate key errors
                    if (stripos($e->getMessage(), 'duplicate') === false && 
                        stripos($e->getMessage(), 'already exists') === false) {
                        error_log('[WAPOS SystemManager] SQL Error: ' . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Get system modules with caching
     */
    public function getSystemModules() {
        $cacheKey = 'system_modules_data';
        
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->db->fetchAll(
                "SELECT * FROM system_modules WHERE is_active = 1 ORDER BY sort_order"
            );
        }
        
        return $this->cache[$cacheKey];
    }
    
    /**
     * Get system actions with caching
     */
    public function getSystemActions() {
        $cacheKey = 'system_actions_data';
        
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->db->fetchAll(
                "SELECT * FROM system_actions ORDER BY 
                 CASE action_key 
                     WHEN 'view' THEN 1 
                     WHEN 'create' THEN 2 
                     WHEN 'update' THEN 3 
                     WHEN 'delete' THEN 4 
                     ELSE 5 
                 END, display_name"
            );
        }
        
        return $this->cache[$cacheKey];
    }
    
    /**
     * Get permission groups with caching
     */
    public function getPermissionGroups() {
        $cacheKey = 'permission_groups_data';
        
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->db->fetchAll(
                "SELECT * FROM permission_groups WHERE is_active = 1 ORDER BY name"
            );
        }
        
        return $this->cache[$cacheKey];
    }
    
    /**
     * Force system refresh
     */
    public function forceRefresh() {
        $this->clearCache();
        $this->initialized = false;
        $this->initializeSystem();
    }
    
    /**
     * Get system status
     */
    public function getSystemStatus() {
        return [
            'initialized' => $this->initialized,
            'modules_count' => $this->getCachedCount('system_modules'),
            'actions_count' => $this->getCachedCount('system_actions'),
            'relationships_count' => $this->getCachedCount('module_actions'),
            'groups_count' => $this->getCachedCount('permission_groups')
        ];
    }
}
