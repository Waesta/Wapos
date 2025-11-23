<?php

namespace App\Services\Payments;

use PDO;
use PDOException;

class PaymentRequestStore
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo instanceof PDO) {
            $this->db = $pdo;
        } else {
            $database = \Database::getInstance();
            $this->db = $database->getConnection();
        }

        $this->ensureSchema();
    }

    public function create(array $data): array
    {
        $sql = 'INSERT INTO payment_requests (
            reference,
            provider,
            status,
            context_type,
            context_id,
            amount,
            currency,
            customer_phone,
            customer_email,
            customer_name,
            instructions,
            provider_reference,
            meta,
            initiated_by_user_id
        ) VALUES (
            :reference,
            :provider,
            :status,
            :context_type,
            :context_id,
            :amount,
            :currency,
            :customer_phone,
            :customer_email,
            :customer_name,
            :instructions,
            :provider_reference,
            :meta,
            :initiated_by_user_id
        )
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            provider_reference = VALUES(provider_reference),
            instructions = VALUES(instructions),
            meta = VALUES(meta),
            context_type = VALUES(context_type),
            context_id = VALUES(context_id),
            amount = VALUES(amount),
            currency = VALUES(currency),
            customer_phone = VALUES(customer_phone),
            customer_email = VALUES(customer_email),
            customer_name = VALUES(customer_name),
            updated_at = CURRENT_TIMESTAMP';

        $stmt = $this->db->prepare($sql);
        $metaJson = $this->encodeMeta($data['meta'] ?? []);

        $stmt->execute([
            ':reference' => $data['reference'],
            ':provider' => $data['provider'],
            ':status' => $data['status'] ?? 'pending',
            ':context_type' => $data['context_type'] ?? 'pos_sale',
            ':context_id' => (int)($data['context_id'] ?? 0),
            ':amount' => (float)($data['amount'] ?? 0),
            ':currency' => $data['currency'] ?? 'KES',
            ':customer_phone' => $data['customer_phone'] ?? null,
            ':customer_email' => $data['customer_email'] ?? null,
            ':customer_name' => $data['customer_name'] ?? null,
            ':instructions' => $data['instructions'] ?? null,
            ':provider_reference' => $data['provider_reference'] ?? null,
            ':meta' => $metaJson,
            ':initiated_by_user_id' => $data['initiated_by_user_id'] ?? null,
        ]);

        return $this->findByReference($data['reference']);
    }

    public function updateStatus(string $reference, string $status, array $updates = []): bool
    {
        $fields = ['status = :status'];
        $params = [
            ':status' => $status,
            ':reference' => $reference,
        ];

        if (array_key_exists('provider_reference', $updates)) {
            $fields[] = 'provider_reference = :provider_reference';
            $params[':provider_reference'] = $updates['provider_reference'];
        }

        if (array_key_exists('instructions', $updates)) {
            $fields[] = 'instructions = :instructions';
            $params[':instructions'] = $updates['instructions'];
        }

        if (array_key_exists('meta', $updates)) {
            $fields[] = 'meta = :meta';
            $params[':meta'] = $this->encodeMeta($updates['meta']);
        }

        if (array_key_exists('last_error', $updates)) {
            $fields[] = 'last_error = :last_error';
            $params[':last_error'] = $updates['last_error'];
        }

        if (array_key_exists('customer_phone', $updates)) {
            $fields[] = 'customer_phone = :customer_phone';
            $params[':customer_phone'] = $updates['customer_phone'];
        }

        if (array_key_exists('customer_name', $updates)) {
            $fields[] = 'customer_name = :customer_name';
            $params[':customer_name'] = $updates['customer_name'];
        }

        if (array_key_exists('customer_email', $updates)) {
            $fields[] = 'customer_email = :customer_email';
            $params[':customer_email'] = $updates['customer_email'];
        }

        if (array_key_exists('amount', $updates)) {
            $fields[] = 'amount = :amount';
            $params[':amount'] = (float)$updates['amount'];
        }

        if (array_key_exists('currency', $updates)) {
            $fields[] = 'currency = :currency';
            $params[':currency'] = $updates['currency'];
        }

        $sql = 'UPDATE payment_requests SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE reference = :reference';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function findByReference(string $reference): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payment_requests WHERE reference = :reference LIMIT 1');
        $stmt->execute([':reference' => $reference]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (!empty($row['meta'])) {
            $decoded = json_decode($row['meta'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['meta'] = $decoded;
            }
        }

        return $row;
    }

    private function encodeMeta($meta): ?string
    {
        if ($meta === null) {
            return null;
        }

        if (!is_array($meta)) {
            return json_encode($meta);
        }

        return json_encode($meta);
    }

    private function ensureSchema(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS payment_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(120) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            context_type VARCHAR(50) NOT NULL,
            context_id INT NOT NULL DEFAULT 0,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT "KES",
            customer_phone VARCHAR(30) NULL,
            customer_email VARCHAR(120) NULL,
            customer_name VARCHAR(150) NULL,
            instructions TEXT NULL,
            provider_reference VARCHAR(150) NULL,
            meta JSON NULL,
            initiated_by_user_id INT UNSIGNED NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reference (reference),
            INDEX idx_provider_status (provider, status),
            INDEX idx_context (context_type, context_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        try {
            $this->db->exec($sql);
        } catch (PDOException $exception) {
            if (stripos($exception->getMessage(), 'Unknown data type') !== false) {
                $fallbackSql = str_replace('meta JSON NULL', 'meta LONGTEXT NULL', $sql);
                $this->db->exec($fallbackSql);
            } else {
                throw $exception;
            }
        }
    }
}
