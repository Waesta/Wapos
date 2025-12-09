<?php
/**
 * Authentication Class
 * Handles user login, session management, and permissions
 */

class Auth {
    private $db;
    private $userId = null;
    private $user = null;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
        $this->checkSession();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params(SESSION_LIFETIME);
            session_start();
        }
    }
    
    private function checkSession() {
        if (isset($_SESSION['user_id'])) {
            $this->userId = $_SESSION['user_id'];
            $this->loadUser();
        }
    }
    
    private function loadUser() {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        $this->user = $this->db->fetchOne($sql, [$this->userId]);
        
        if (!$this->user) {
            // User not found or inactive - clear session without full logout to avoid redirect loops
            $this->userId = null;
            $this->user = null;
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
            unset($_SESSION['role']);
        }
    }
    
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
        $user = $this->db->fetchOne($sql, [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $user['id']]
            );
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            $this->userId = $user['id'];
            $this->user = $user;
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        $_SESSION = [];
        session_destroy();
        $this->userId = null;
        $this->user = null;
    }
    
    public function isLoggedIn() {
        return $this->userId !== null;
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function getUserId() {
        return $this->userId;
    }
    
    public function getUsername() {
        return $this->user['username'] ?? null;
    }
    
    public function getRole() {
        if (!isset($this->user['role'])) {
            return null;
        }

        $role = strtolower(trim($this->user['role']));
        $role = str_replace([' ', '-'], '_', $role);

        $roleAliases = [
            'superadmin' => 'super_admin',
            'superadministrator' => 'super_admin',
            'super_administrator' => 'super_admin',
        ];

        return $roleAliases[$role] ?? $role;
    }
    
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $currentRole = $this->getRole();
        
        // Super admin and developer have full access to everything
        if (in_array($currentRole, ['super_admin', 'developer'], true)) {
            return true;
        }
        
        $userRole = $this->getRole();
        
        // Super admin has unrestricted access
        if ($userRole === 'super_admin') {
            return true;
        }

        // Admin and developer have access to everything
        if ($userRole === 'admin' || $userRole === 'developer') {
            return true;
        }
        
        // Support checking against multiple roles
        if (is_array($role)) {
            foreach ($role as $r) {
                if ($this->hasRole($r)) {
                    return true;
                }
            }
            return false;
        }
        
        // Manager has access to manager, inventory_manager, cashier, waiter, rider
        if ($userRole === 'manager' && in_array($role, ['manager', 'inventory_manager', 'cashier', 'waiter', 'rider'])) {
            return true;
        }
        
        // Inventory Manager has access to inventory_manager only
        if ($userRole === 'inventory_manager' && $role === 'inventory_manager') {
            return true;
        }
        
        // Exact role match for others
        return $userRole === $role;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            // Redirect to access denied page or show error
            header('Location: ' . APP_URL . '/access-denied.php');
            exit;
        }
    }
    
    public static function hashPassword($password) {
        return password_hash($password, HASH_ALGO, HASH_OPTIONS);
    }
}
