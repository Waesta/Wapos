<?php

if (!function_exists('ensureOrdersCompletedAtColumn')) {
    function ensureOrdersCompletedAtColumn(?Database $db = null): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $db = $db ?? Database::getInstance();
        if (!$db) {
            return;
        }

        try {
            $existing = $db->fetchOne('SHOW COLUMNS FROM orders LIKE "completed_at"');
            if (!$existing) {
                $db->query('ALTER TABLE orders ADD COLUMN completed_at DATETIME NULL AFTER updated_at');
            }
        } catch (Throwable $e) {
            error_log('Failed to ensure orders.completed_at column: ' . $e->getMessage());
        }

        $ensured = true;
    }
}

if (!function_exists('ensureOrdersPaymentTrackingColumns')) {
    function ensureOrdersPaymentTrackingColumns(?Database $db = null): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $db = $db ?? Database::getInstance();
        if (!$db) {
            return;
        }

        try {
            $tipColumn = $db->fetchOne('SHOW COLUMNS FROM orders LIKE "tip_amount"');
            if (!$tipColumn) {
                $db->query('ALTER TABLE orders ADD COLUMN tip_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total_amount');
            }

            $amountColumn = $db->fetchOne('SHOW COLUMNS FROM orders LIKE "amount_paid"');
            if (!$amountColumn) {
                $db->query('ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER tip_amount');
            }
        } catch (Throwable $e) {
            error_log('Failed to ensure order payment tracking columns: ' . $e->getMessage());
        }

        $ensured = true;
    }
}

if (!function_exists('ensureOrderMetaTable')) {
    function ensureOrderMetaTable(?Database $db = null): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $db = $db ?? Database::getInstance();
        if (!$db) {
            return;
        }

        try {
            $db->query(
                'CREATE TABLE IF NOT EXISTS order_meta (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id INT UNSIGNED NOT NULL,
                    meta_key VARCHAR(100) NOT NULL,
                    meta_value LONGTEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_order_meta_order (order_id),
                    INDEX idx_order_meta_key (meta_key),
                    CONSTRAINT fk_order_meta_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (Throwable $e) {
            error_log('Failed to ensure order_meta table: ' . $e->getMessage());
        }

        $ensured = true;
    }
}
