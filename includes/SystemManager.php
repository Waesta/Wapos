<?php
/**
 * System Manager - Clean and Stable Version
 * Simple data access without complex initialization
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
            return $this->getDefaultModules();
        }
    }
    
    /**
     * Get system actions with error handling
     */
    public function getSystemActions() {
        try {
            if (!isset($this->cache['actions'])) {
                $this->cache['actions'] = $this->db->fetchAll(
                    "SELECT * FROM system_actions ORDER BY 
                     CASE action_key 
                         WHEN 'view' THEN 1 
                         WHEN 'create' THEN 2 
                         WHEN 'update' THEN 3 
                         WHEN 'delete' THEN 4 
                         ELSE 5 
                     END, display_name"
                ) ?: [];
            }
            return $this->cache['actions'];
        } catch (Exception $e) {
            error_log('SystemManager getSystemActions error: ' . $e->getMessage());
            return $this->getDefaultActions();
        }
    }
    
    /**
     * Get permission groups with error handling
     */
    public function getPermissionGroups() {
        try {
            if (!isset($this->cache['groups'])) {
                $this->cache['groups'] = $this->db->fetchAll(
                    "SELECT * FROM permission_groups WHERE is_active = 1 ORDER BY name"
                ) ?: [];
            }
            return $this->cache['groups'];
        } catch (Exception $e) {
            error_log('SystemManager getPermissionGroups error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system status
     */
    public function getSystemStatus() {
        try {
            return [
                'initialized' => true,
                'modules_count' => count($this->getSystemModules()),
                'actions_count' => count($this->getSystemActions()),
                'relationships_count' => $this->getCount('module_actions'),
                'groups_count' => count($this->getPermissionGroups())
            ];
        } catch (Exception $e) {
            error_log('SystemManager getSystemStatus error: ' . $e->getMessage());
            return [
                'initialized' => false,
                'modules_count' => 0,
                'actions_count' => 0,
                'relationships_count' => 0,
                'groups_count' => 0
            ];
        }
    }
    
    /**
     * Get count from table safely
     */
    private function getCount($table) {
        try {
            $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
            return $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Force refresh
     */
    public function forceRefresh() {
        $this->clearCache();
    }
    
    /**
     * Default modules fallback
     */
    private function getDefaultModules() {
        return [
            ['module_key' => 'dashboard', 'display_name' => 'Dashboard', 'icon' => 'bi-speedometer2'],
            ['module_key' => 'pos', 'display_name' => 'Point of Sale', 'icon' => 'bi-cart-plus'],
            ['module_key' => 'restaurant', 'display_name' => 'Restaurant', 'icon' => 'bi-shop'],
            ['module_key' => 'products', 'display_name' => 'Products', 'icon' => 'bi-box-seam'],
            ['module_key' => 'sales', 'display_name' => 'Sales', 'icon' => 'bi-receipt'],
            ['module_key' => 'customers', 'display_name' => 'Customers', 'icon' => 'bi-people'],
            ['module_key' => 'reports', 'display_name' => 'Reports', 'icon' => 'bi-graph-up'],
            ['module_key' => 'users', 'display_name' => 'Users', 'icon' => 'bi-person-badge'],
            ['module_key' => 'settings', 'display_name' => 'Settings', 'icon' => 'bi-gear']
        ];
    }
    
    /**
     * Default actions fallback
     */
    private function getDefaultActions() {
        return [
            ['action_key' => 'view', 'display_name' => 'View/Read', 'is_sensitive' => 0],
            ['action_key' => 'create', 'display_name' => 'Create/Add', 'is_sensitive' => 0],
            ['action_key' => 'update', 'display_name' => 'Edit/Update', 'is_sensitive' => 0],
            ['action_key' => 'delete', 'display_name' => 'Delete/Remove', 'is_sensitive' => 1],
            ['action_key' => 'void', 'display_name' => 'Void Transaction', 'is_sensitive' => 1],
            ['action_key' => 'refund', 'display_name' => 'Process Refunds', 'is_sensitive' => 1],
            ['action_key' => 'discount', 'display_name' => 'Apply Discounts', 'is_sensitive' => 1],
            ['action_key' => 'export', 'display_name' => 'Export Data', 'is_sensitive' => 0],
            ['action_key' => 'print', 'display_name' => 'Print', 'is_sensitive' => 0]
        ];
    }
}
