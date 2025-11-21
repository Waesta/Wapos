-- =====================================================
-- WAPOS Migration 004: Restaurant Billing Enhancements
-- Purpose: Support split payments and tipping
-- =====================================================

-- 1. Create order_payments table (tracks split tenders)
CREATE TABLE IF NOT EXISTS order_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tip_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    metadata JSON NULL,
    recorded_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_payments_user
        FOREIGN KEY (recorded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 1a. Add fallback metadata column for MySQL versions without JSON support
ALTER TABLE order_payments
    ADD COLUMN IF NOT EXISTS metadata_fallback LONGTEXT NULL;

-- 1b. Ensure indexes for reporting
ALTER TABLE order_payments
    ADD INDEX IF NOT EXISTS idx_order_payments_order (order_id),
    ADD INDEX IF NOT EXISTS idx_order_payments_method (payment_method);

-- 2. Extend orders table with tip & amount tracking
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS tip_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER total_amount,
    ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER tip_amount;

-- 2a. Ensure updated_at column exists for audit (legacy safety)
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
