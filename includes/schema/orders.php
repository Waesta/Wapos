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
