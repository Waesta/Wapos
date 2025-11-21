<?php

namespace App\Services;

use PDO;
use PDOException;

class RestaurantBillingService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        $this->createOrderPaymentsTable();
        $this->addColumnIfMissing('orders', 'tip_amount', "DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total_amount");
        $this->addColumnIfMissing('orders', 'amount_paid', "DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER tip_amount");
        $this->ensureIndex('order_payments', 'idx_order_payments_order', 'ADD INDEX idx_order_payments_order (order_id)');
        $this->ensureIndex('order_payments', 'idx_order_payments_method', 'ADD INDEX idx_order_payments_method (payment_method)');
    }

    public function getOrderPayments(int $orderId): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare(
            'SELECT id, order_id, payment_method, amount, tip_amount, metadata, recorded_by_user_id, created_at
             FROM order_payments
             WHERE order_id = :order_id
             ORDER BY id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (isset($row['metadata']) && $row['metadata'] !== null) {
                $decoded = json_decode($row['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $row['metadata'] = $decoded;
                }
            }
        }
        unset($row);

        return $rows;
    }

    public function replaceOrderPayments(int $orderId, array $payments, string $paymentMethodLabel, string $paymentStatus): array
    {
        $this->ensureSchema();

        $deleteStmt = $this->db->prepare('DELETE FROM order_payments WHERE order_id = :order_id');
        $deleteStmt->execute([':order_id' => $orderId]);

        $insertStmt = $this->db->prepare(
            'INSERT INTO order_payments (order_id, payment_method, amount, tip_amount, metadata, recorded_by_user_id, created_at)
             VALUES (:order_id, :payment_method, :amount, :tip_amount, :metadata, :recorded_by_user_id, NOW())'
        );

        $totalAmount = 0.0;
        $totalTip = 0.0;

        foreach ($payments as $payment) {
            $method = $payment['payment_method'];
            $amount = (float)$payment['amount'];
            $tipAmount = isset($payment['tip_amount']) ? (float)$payment['tip_amount'] : 0.0;
            $metadata = $payment['metadata'] ?? null;
            $recordedBy = isset($payment['recorded_by_user_id']) ? (int)$payment['recorded_by_user_id'] : null;

            $totalAmount += $amount;
            $totalTip += $tipAmount;

            $metadataJson = null;
            if ($metadata !== null) {
                $metadataJson = is_array($metadata) ? json_encode($metadata) : (string)$metadata;
            }

            $insertStmt->execute([
                ':order_id' => $orderId,
                ':payment_method' => $method,
                ':amount' => $amount,
                ':tip_amount' => $tipAmount,
                ':metadata' => $metadataJson,
                ':recorded_by_user_id' => $recordedBy,
            ]);
        }

        $updateStmt = $this->db->prepare(
            'UPDATE orders
             SET payment_method = :payment_method,
                 payment_status = :payment_status,
                 amount_paid = :amount_paid,
                 tip_amount = :tip_amount,
                 updated_at = NOW()
             WHERE id = :order_id'
        );
        $updateStmt->execute([
            ':payment_method' => $paymentMethodLabel,
            ':payment_status' => $paymentStatus,
            ':amount_paid' => $totalAmount,
            ':tip_amount' => $totalTip,
            ':order_id' => $orderId,
        ]);

        return [
            'total_amount' => $totalAmount,
            'total_tip' => $totalTip,
        ];
    }

    private function createOrderPaymentsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS order_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            tip_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            metadata JSON NULL,
            recorded_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB';

        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            // Fallback for MySQL versions without JSON support
            if (stripos($e->getMessage(), 'Unknown data type') !== false) {
                $fallbackSql = 'CREATE TABLE IF NOT EXISTS order_payments (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id INT UNSIGNED NOT NULL,
                    payment_method VARCHAR(50) NOT NULL,
                    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                    tip_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                    metadata LONGTEXT NULL,
                    recorded_by_user_id INT UNSIGNED NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB';
                $this->db->exec($fallbackSql);
            } else {
                throw $e;
            }
        }
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if (!$exists) {
            $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$definition}");
        }
    }

    private function ensureIndex(string $table, string $indexName, string $alterStatement): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name'
        );
        $stmt->execute([':table' => $table, ':index_name' => $indexName]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if (!$exists) {
            try {
                $this->db->exec("ALTER TABLE {$table} {$alterStatement}");
            } catch (PDOException $e) {
                // Ignore duplicate index errors
                if (stripos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
            }
        }
    }
}
