-- Delivery Dynamic Pricing Migration
-- Creates tables and indexes required for distance-based delivery pricing

CREATE TABLE IF NOT EXISTS delivery_pricing_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    priority INT UNSIGNED NOT NULL DEFAULT 1,
    distance_min_km DECIMAL(8,2) NOT NULL DEFAULT 0,
    distance_max_km DECIMAL(8,2) NULL,
    base_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    per_km_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    surcharge_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_distance_range (distance_min_km, distance_max_km),
    KEY idx_delivery_pricing_priority (priority),
    KEY idx_delivery_pricing_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_distance_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origin_hash CHAR(64) NOT NULL,
    destination_hash CHAR(64) NOT NULL,
    origin_lat DECIMAL(10,8) NOT NULL,
    origin_lng DECIMAL(11,8) NOT NULL,
    destination_lat DECIMAL(10,8) NOT NULL,
    destination_lng DECIMAL(11,8) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    distance_m INT UNSIGNED NOT NULL,
    duration_s INT UNSIGNED DEFAULT NULL,
    response_payload JSON,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    KEY idx_origin_destination_hash (origin_hash, destination_hash),
    KEY idx_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_pricing_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED DEFAULT NULL,
    request_id CHAR(36) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    rule_id INT UNSIGNED DEFAULT NULL,
    distance_m INT UNSIGNED DEFAULT NULL,
    duration_s INT UNSIGNED DEFAULT NULL,
    fee_applied DECIMAL(10,2) DEFAULT NULL,
    api_calls INT UNSIGNED DEFAULT 0,
    cache_hit TINYINT(1) DEFAULT 0,
    fallback_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_created_at (created_at),
    INDEX idx_rule_id (rule_id)
) ENGINE=InnoDB;

-- Seed default bracket if none exist
INSERT INTO delivery_pricing_rules (rule_name, priority, distance_min_km, distance_max_km, base_fee, per_km_fee, surcharge_percent, notes)
SELECT 'Default 0-5km', 1, 0.00, 5.00, 50.00, 0.00, 0.00, 'Initial default bracket'
WHERE NOT EXISTS (SELECT 1 FROM delivery_pricing_rules);
