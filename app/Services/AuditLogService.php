<?php

namespace App\Services;

use PDO;

/**
 * Audit Log Service
 * Records sensitive admin actions for compliance and security
 */
class AuditLogService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log an action
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?string $tableName = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        // Create audit_log table if not exists
        $this->ensureTableExists();

        $sql = "INSERT INTO audit_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Log sale void
     */
    public function logVoid(int $saleId, int $userId, string $reason): void
    {
        $this->log(
            'void_sale',
            $userId,
            'sales',
            $saleId,
            null,
            ['reason' => $reason]
        );
    }

    /**
     * Log price change
     */
    public function logPriceChange(int $productId, int $userId, float $oldPrice, float $newPrice): void
    {
        $this->log(
            'price_change',
            $userId,
            'products',
            $productId,
            ['price' => $oldPrice],
            ['price' => $newPrice]
        );
    }

    /**
     * Log role change
     */
    public function logRoleChange(int $targetUserId, int $adminUserId, string $oldRole, string $newRole): void
    {
        $this->log(
            'role_change',
            $adminUserId,
            'users',
            $targetUserId,
            ['role' => $oldRole],
            ['role' => $newRole]
        );
    }

    /**
     * Log permission change
     */
    public function logPermissionChange(int $userId, int $adminUserId, array $oldPermissions, array $newPermissions): void
    {
        $this->log(
            'permission_change',
            $adminUserId,
            'users',
            $userId,
            ['permissions' => $oldPermissions],
            ['permissions' => $newPermissions]
        );
    }

    /**
     * Log accounting period close
     */
    public function logPeriodClose(int $periodId, int $userId): void
    {
        $this->log(
            'period_close',
            $userId,
            'accounting_periods',
            $periodId
        );
    }

    /**
     * Get audit trail for a record
     */
    public function getAuditTrail(string $tableName, int $recordId, int $limit = 50): array
    {
        $sql = "SELECT al.*, u.username, u.full_name 
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = ? AND al.record_id = ?
                ORDER BY al.created_at DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tableName, $recordId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent audit logs
     */
    public function getRecentLogs(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT al.*, u.username, u.full_name 
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ensure audit_log table exists
     */
    private function ensureTableExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100),
            record_id INT UNSIGNED,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_table_record (table_name, record_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }
}
