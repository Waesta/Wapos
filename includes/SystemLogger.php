<?php
/**
 * System Logger
 * Centralized logging service for WAPOS
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

class SystemLogger
{
    private static ?SystemLogger $instance = null;
    private $db;
    private bool $tableChecked = false;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): SystemLogger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Ensure the system_logs table exists
     */
    private function ensureTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS system_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                log_level ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'info',
                category VARCHAR(50) NOT NULL DEFAULT 'general',
                message TEXT NOT NULL,
                context JSON,
                user_id INT UNSIGNED,
                ip_address VARCHAR(45),
                request_uri VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (log_level),
                INDEX idx_category (category),
                INDEX idx_created (created_at),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB");
            $this->tableChecked = true;
        } catch (Exception $e) {
            error_log('SystemLogger: Failed to create table - ' . $e->getMessage());
        }
    }

    /**
     * Log a message
     */
    public function log(
        string $level,
        string $message,
        string $category = 'general',
        ?array $context = null,
        ?int $userId = null
    ): bool {
        $this->ensureTable();

        try {
            $this->db->insert('system_logs', [
                'log_level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => $context ? json_encode($context) : null,
                'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null
            ]);
            return true;
        } catch (Exception $e) {
            error_log('SystemLogger: Failed to log - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log debug message
     */
    public function debug(string $message, string $category = 'general', ?array $context = null): bool
    {
        return $this->log('debug', $message, $category, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, string $category = 'general', ?array $context = null): bool
    {
        return $this->log('info', $message, $category, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, string $category = 'general', ?array $context = null): bool
    {
        return $this->log('warning', $message, $category, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, string $category = 'general', ?array $context = null): bool
    {
        return $this->log('error', $message, $category, $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, string $category = 'general', ?array $context = null): bool
    {
        return $this->log('critical', $message, $category, $context);
    }

    /**
     * Log login attempt
     */
    public function logLogin(int $userId, bool $success, ?string $username = null): bool
    {
        return $this->log(
            $success ? 'info' : 'warning',
            $success ? 'User logged in successfully' : 'Failed login attempt',
            'auth',
            ['username' => $username, 'success' => $success],
            $success ? $userId : null
        );
    }

    /**
     * Log logout
     */
    public function logLogout(int $userId): bool
    {
        return $this->log('info', 'User logged out', 'auth', null, $userId);
    }

    /**
     * Log API request
     */
    public function logApiRequest(string $endpoint, string $method, ?int $userId = null, ?array $context = null): bool
    {
        return $this->log('debug', "API: {$method} {$endpoint}", 'api', $context, $userId);
    }

    /**
     * Log security event
     */
    public function logSecurity(string $event, ?array $context = null, ?int $userId = null): bool
    {
        return $this->log('warning', $event, 'security', $context, $userId);
    }

    /**
     * Log database error
     */
    public function logDatabaseError(string $message, ?array $context = null): bool
    {
        return $this->log('error', $message, 'database', $context);
    }

    /**
     * Log payment event
     */
    public function logPayment(string $event, float $amount, string $method, ?array $context = null): bool
    {
        $context = array_merge($context ?? [], [
            'amount' => $amount,
            'method' => $method
        ]);
        return $this->log('info', $event, 'payment', $context);
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 100, ?string $level = null, ?string $category = null): array
    {
        $this->ensureTable();

        $where = [];
        $params = [];

        if ($level) {
            $where[] = 'log_level = ?';
            $params[] = $level;
        }

        if ($category) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT sl.*, u.username, u.full_name 
                FROM system_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                {$whereClause}
                ORDER BY sl.created_at DESC
                LIMIT ?";
        
        $params[] = $limit;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get log statistics
     */
    public function getStats(): array
    {
        $this->ensureTable();

        try {
            return [
                'total' => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM system_logs")['c'],
                'today' => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM system_logs WHERE DATE(created_at) = CURDATE()")['c'],
                'errors_today' => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM system_logs WHERE log_level IN ('error', 'critical') AND DATE(created_at) = CURDATE()")['c'],
                'by_level' => $this->db->fetchAll("SELECT log_level, COUNT(*) as count FROM system_logs GROUP BY log_level"),
                'by_category' => $this->db->fetchAll("SELECT category, COUNT(*) as count FROM system_logs GROUP BY category ORDER BY count DESC LIMIT 10")
            ];
        } catch (Exception $e) {
            return [
                'total' => 0,
                'today' => 0,
                'errors_today' => 0,
                'by_level' => [],
                'by_category' => []
            ];
        }
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs(int $daysToKeep = 30): int
    {
        $this->ensureTable();

        try {
            $stmt = $this->db->query(
                "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log('SystemLogger: Failed to clear old logs - ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Helper function for quick logging
 */
function system_log(string $level, string $message, string $category = 'general', ?array $context = null): bool
{
    return SystemLogger::getInstance()->log($level, $message, $category, $context);
}
