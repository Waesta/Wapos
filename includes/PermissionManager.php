<?php
/**
 * Comprehensive Permission Manager
 * Handles granular permissions, audit logging, and security enforcement
 */

class PermissionManager {
    private $db;
    private $userId;
    private $userPermissions = null;
    private $sessionId;
    
    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->sessionId = session_id();
    }
    
    /**
     * Get user role
     */
    private function getUserRole() {
        if (!$this->userId) return null;
        
        static $userRole = null;
        if ($userRole === null) {
            $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$this->userId]);
            $userRole = $user ? $user['role'] : null;
        }
        
        return $userRole;
    }
    
    /**
     * Check if user has permission for a specific module and action
     */
    public function hasPermission($moduleKey, $actionKey, $resourceId = null) {
        if (!$this->userId) {
            $this->logAudit('permission_denied', null, null, 'No user ID provided');
            return false;
        }
        
        // Load user permissions if not already loaded
        if ($this->userPermissions === null) {
            $this->loadUserPermissions();
        }
        
        $hasPermission = $this->checkPermission($moduleKey, $actionKey, $resourceId);
        
        // Log permission check
        $this->logAudit(
            $hasPermission ? 'permission_check' : 'permission_denied',
            $moduleKey,
            $actionKey,
            "Resource ID: " . ($resourceId ?? 'N/A')
        );
        
        return $hasPermission;
    }
    
    /**
     * Require permission or throw exception
     */
    public function requirePermission($moduleKey, $actionKey, $resourceId = null) {
        if (!$this->hasPermission($moduleKey, $actionKey, $resourceId)) {
            $this->logAudit('policy_violation', $moduleKey, $actionKey, 'Permission required but not granted');
            throw new Exception("Access denied: Insufficient permissions for {$moduleKey}:{$actionKey}");
        }
    }
    
    /**
     * Load all permissions for the current user
     */
    private function loadUserPermissions() {
        $this->userPermissions = [
            'groups' => [],
            'individual' => [],
            'modules' => []
        ];
        
        // Get user's group memberships and their permissions
        $groupPermissions = $this->db->fetchAll("
            SELECT 
                sm.module_key,
                sa.action_key,
                gp.is_granted,
                gp.conditions,
                pg.group_name
            FROM user_group_memberships ugm
            JOIN permission_groups pg ON ugm.group_id = pg.id
            JOIN group_permissions gp ON pg.id = gp.group_id
            JOIN system_modules sm ON gp.module_id = sm.id
            JOIN system_actions sa ON gp.action_id = sa.id
            WHERE ugm.user_id = ? 
            AND ugm.is_active = 1 
            AND (ugm.expires_at IS NULL OR ugm.expires_at > NOW())
            AND pg.is_active = 1
        ", [$this->userId]);
        
        foreach ($groupPermissions as $perm) {
            $key = $perm['module_key'] . ':' . $perm['action_key'];
            $this->userPermissions['groups'][$key] = [
                'granted' => (bool)$perm['is_granted'],
                'conditions' => $perm['conditions'] ? json_decode($perm['conditions'], true) : null,
                'source' => 'group:' . $perm['group_name']
            ];
        }
        
        // Get individual user permissions (these override group permissions)
        $individualPermissions = $this->db->fetchAll("
            SELECT 
                sm.module_key,
                sa.action_key,
                up.is_granted,
                up.permission_type,
                up.conditions,
                up.expires_at
            FROM user_permissions up
            JOIN system_modules sm ON up.module_id = sm.id
            JOIN system_actions sa ON up.action_id = sa.id
            WHERE up.user_id = ? 
            AND (up.expires_at IS NULL OR up.expires_at > NOW())
        ", [$this->userId]);
        
        foreach ($individualPermissions as $perm) {
            $key = $perm['module_key'] . ':' . $perm['action_key'];
            $this->userPermissions['individual'][$key] = [
                'granted' => (bool)$perm['is_granted'],
                'type' => $perm['permission_type'],
                'conditions' => $perm['conditions'] ? json_decode($perm['conditions'], true) : null,
                'expires_at' => $perm['expires_at'],
                'source' => 'individual'
            ];
        }
        
        // Get accessible modules
        $modules = $this->db->fetchAll("
            SELECT DISTINCT sm.module_key, sm.display_name, sm.icon
            FROM system_modules sm
            WHERE sm.is_active = 1
        ");
        
        foreach ($modules as $module) {
            if ($this->checkPermission($module['module_key'], 'view')) {
                $this->userPermissions['modules'][$module['module_key']] = [
                    'display_name' => $module['display_name'],
                    'icon' => $module['icon']
                ];
            }
        }
    }
    
    /**
     * Check permission logic
     */
    private function checkPermission($moduleKey, $actionKey, $resourceId = null) {
        // Admin and developer users have full access to everything
        $userRole = $this->getUserRole();
        if ($userRole === 'admin' || $userRole === 'developer') {
            return true;
        }
        
        $key = $moduleKey . ':' . $actionKey;
        
        // Individual permissions override group permissions
        if (isset($this->userPermissions['individual'][$key])) {
            $perm = $this->userPermissions['individual'][$key];
            
            // Check if it's a deny permission
            if ($perm['type'] === 'deny') {
                return false;
            }
            
            // Check conditions if any
            if ($perm['conditions'] && !$this->checkConditions($perm['conditions'], $resourceId)) {
                return false;
            }
            
            return $perm['granted'];
        }
        
        // Check group permissions
        if (isset($this->userPermissions['groups'][$key])) {
            $perm = $this->userPermissions['groups'][$key];
            
            // Check conditions if any
            if ($perm['conditions'] && !$this->checkConditions($perm['conditions'], $resourceId)) {
                return false;
            }
            
            return $perm['granted'];
        }
        
        // Default deny
        return false;
    }
    
    /**
     * Check permission conditions
     */
    private function checkConditions($conditions, $resourceId = null) {
        if (!$conditions) return true;
        
        // Time-based conditions
        if (isset($conditions['time_restrictions'])) {
            $currentHour = (int)date('H');
            $restrictions = $conditions['time_restrictions'];
            
            if (isset($restrictions['start_hour']) && $currentHour < $restrictions['start_hour']) {
                return false;
            }
            
            if (isset($restrictions['end_hour']) && $currentHour > $restrictions['end_hour']) {
                return false;
            }
        }
        
        // Location-based conditions
        if (isset($conditions['location_restrictions'])) {
            $userLocation = $this->getCurrentUserLocation();
            $allowedLocations = $conditions['location_restrictions'];
            
            if (!in_array($userLocation, $allowedLocations)) {
                return false;
            }
        }
        
        // Amount-based conditions (for financial operations)
        if (isset($conditions['amount_limit']) && $resourceId) {
            $amount = $this->getResourceAmount($resourceId);
            if ($amount > $conditions['amount_limit']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Grant permission to user
     */
    public function grantPermission($targetUserId, $moduleKey, $actionKey, $grantedBy, $conditions = null, $expiresAt = null, $reason = null) {
        $moduleId = $this->getModuleId($moduleKey);
        $actionId = $this->getActionId($actionKey);
        
        if (!$moduleId || !$actionId) {
            throw new Exception("Invalid module or action");
        }
        
        $data = [
            'user_id' => $targetUserId,
            'module_id' => $moduleId,
            'action_id' => $actionId,
            'is_granted' => 1,
            'permission_type' => 'allow',
            'conditions' => $conditions ? json_encode($conditions) : null,
            'granted_by' => $grantedBy,
            'expires_at' => $expiresAt,
            'reason' => $reason
        ];
        
        $this->db->query("
            INSERT INTO user_permissions (user_id, module_id, action_id, is_granted, permission_type, conditions, granted_by, expires_at, reason)
            VALUES (:user_id, :module_id, :action_id, :is_granted, :permission_type, :conditions, :granted_by, :expires_at, :reason)
            ON DUPLICATE KEY UPDATE
                is_granted = VALUES(is_granted),
                permission_type = VALUES(permission_type),
                conditions = VALUES(conditions),
                granted_by = VALUES(granted_by),
                expires_at = VALUES(expires_at),
                reason = VALUES(reason),
                granted_at = NOW()
        ", $data);
        
        $this->logAudit('permission_granted', $moduleKey, $actionKey, "Permission granted to user {$targetUserId}. Reason: {$reason}");
        
        // Clear cached permissions
        $this->clearUserPermissionCache($targetUserId);
    }
    
    /**
     * Revoke permission from user
     */
    public function revokePermission($targetUserId, $moduleKey, $actionKey, $revokedBy, $reason = null) {
        $moduleId = $this->getModuleId($moduleKey);
        $actionId = $this->getActionId($actionKey);
        
        $this->db->query("
            DELETE FROM user_permissions 
            WHERE user_id = ? AND module_id = ? AND action_id = ?
        ", [$targetUserId, $moduleId, $actionId]);
        
        $this->logAudit('permission_changed', $moduleKey, $actionKey, "Permission revoked from user {$targetUserId}. Reason: {$reason}");
        
        $this->clearUserPermissionCache($targetUserId);
    }
    
    /**
     * Add user to permission group
     */
    public function addUserToGroup($targetUserId, $groupId, $assignedBy, $expiresAt = null) {
        $data = [
            'user_id' => $targetUserId,
            'group_id' => $groupId,
            'assigned_by' => $assignedBy,
            'expires_at' => $expiresAt,
            'is_active' => 1
        ];
        
        $this->db->query("
            INSERT INTO user_group_memberships (user_id, group_id, assigned_by, expires_at, is_active)
            VALUES (:user_id, :group_id, :assigned_by, :expires_at, :is_active)
            ON DUPLICATE KEY UPDATE
                assigned_by = VALUES(assigned_by),
                expires_at = VALUES(expires_at),
                is_active = VALUES(is_active),
                assigned_at = NOW()
        ", $data);
        
        $groupName = $this->db->fetchOne("SELECT group_name FROM permission_groups WHERE id = ?", [$groupId])['group_name'] ?? 'Unknown';
        $this->logAudit('permission_changed', null, null, "User {$targetUserId} added to group: {$groupName}");
        
        $this->clearUserPermissionCache($targetUserId);
    }
    
    /**
     * Remove user from permission group
     */
    public function removeUserFromGroup($targetUserId, $groupId, $removedBy, $reason = null) {
        $this->db->query("
            UPDATE user_group_memberships 
            SET is_active = 0 
            WHERE user_id = ? AND group_id = ?
        ", [$targetUserId, $groupId]);
        
        $groupName = $this->db->fetchOne("SELECT group_name FROM permission_groups WHERE id = ?", [$groupId])['group_name'] ?? 'Unknown';
        $this->logAudit('permission_changed', null, null, "User {$targetUserId} removed from group: {$groupName}. Reason: {$reason}");
        
        $this->clearUserPermissionCache($targetUserId);
    }
    
    /**
     * Get user's accessible modules
     */
    public function getUserModules($userId = null) {
        $targetUserId = $userId ?? $this->userId;
        
        if ($this->userId !== $targetUserId) {
            $tempManager = new PermissionManager($targetUserId);
            $tempManager->loadUserPermissions();
            return $tempManager->userPermissions['modules'] ?? [];
        }
        
        if ($this->userPermissions === null) {
            $this->loadUserPermissions();
        }
        
        return $this->userPermissions['modules'] ?? [];
    }
    
    /**
     * Log audit trail
     */
    public function logAudit($actionType, $moduleKey = null, $actionKey = null, $details = null, $riskLevel = 'low') {
        $moduleId = $moduleKey ? $this->getModuleId($moduleKey) : null;
        $actionId = $actionKey ? $this->getActionId($actionKey) : null;
        
        $data = [
            'user_id' => $this->userId,
            'action_type' => $actionType,
            'module_id' => $moduleId,
            'action_id' => $actionId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => $this->sessionId,
            'risk_level' => $riskLevel,
            'additional_data' => $details ? json_encode(['details' => $details]) : null
        ];
        
        $this->db->insert('permission_audit_log', $data);
    }
    
    /**
     * Get sensitive actions that require approval
     */
    public function getSensitiveActions() {
        return $this->db->fetchAll("
            SELECT action_key, display_name, description
            FROM system_actions 
            WHERE is_sensitive = 1 OR requires_approval = 1
        ");
    }
    
    /**
     * Check if action requires approval
     */
    public function requiresApproval($actionKey) {
        $action = $this->db->fetchOne("
            SELECT requires_approval FROM system_actions WHERE action_key = ?
        ", [$actionKey]);
        
        return $action ? (bool)$action['requires_approval'] : false;
    }
    
    /**
     * Helper methods
     */
    private function getModuleId($moduleKey) {
        $result = $this->db->fetchOne("SELECT id FROM system_modules WHERE module_key = ?", [$moduleKey]);
        return $result ? $result['id'] : null;
    }
    
    private function getActionId($actionKey) {
        $result = $this->db->fetchOne("SELECT id FROM system_actions WHERE action_key = ?", [$actionKey]);
        return $result ? $result['id'] : null;
    }
    
    private function getCurrentUserLocation() {
        // Implementation depends on how location is tracked
        return $_SESSION['current_location_id'] ?? 1;
    }
    
    private function getResourceAmount($resourceId) {
        // Implementation depends on resource type
        return 0;
    }
    
    private function clearUserPermissionCache($userId) {
        // Clear any cached permissions for the user
        // This could be implemented with Redis, Memcached, or file cache
    }
    
    /**
     * Get permission matrix for UI display
     */
    public function getPermissionMatrix($userId = null) {
        $targetUserId = $userId ?? $this->userId;
        
        $modules = $this->db->fetchAll("
            SELECT id, module_key, display_name, icon 
            FROM system_modules 
            WHERE is_active = 1 
            ORDER BY sort_order
        ");
        
        $actions = $this->db->fetchAll("
            SELECT id, action_key, display_name, is_sensitive 
            FROM system_actions 
            ORDER BY 
                CASE action_key 
                    WHEN 'view' THEN 1
                    WHEN 'create' THEN 2
                    WHEN 'update' THEN 3
                    WHEN 'delete' THEN 4
                    ELSE 5
                END
        ");
        
        $matrix = [];
        foreach ($modules as $module) {
            $matrix[$module['module_key']] = [
                'display_name' => $module['display_name'],
                'icon' => $module['icon'],
                'actions' => []
            ];
            
            foreach ($actions as $action) {
                $hasPermission = $this->hasPermission($module['module_key'], $action['action_key']);
                $matrix[$module['module_key']]['actions'][$action['action_key']] = [
                    'display_name' => $action['display_name'],
                    'is_sensitive' => (bool)$action['is_sensitive'],
                    'has_permission' => $hasPermission
                ];
            }
        }
        
        return $matrix;
    }
}
