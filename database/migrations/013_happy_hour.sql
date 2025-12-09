-- Happy Hour System Migration
-- Time-based pricing rules and promotions

-- Happy Hour Rules
CREATE TABLE IF NOT EXISTS happy_hour_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Timing
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    days_of_week VARCHAR(100) DEFAULT 'all' COMMENT 'Comma-separated: monday,tuesday,etc or "all"',
    
    -- Date range (optional)
    valid_from DATE NULL,
    valid_until DATE NULL,
    
    -- Discount settings
    discount_type ENUM('percent', 'fixed', 'bogo') DEFAULT 'percent',
    discount_percent DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    
    -- Limits
    max_discount_amount DECIMAL(15,2) NULL COMMENT 'Cap on discount per item',
    min_purchase_amount DECIMAL(15,2) NULL COMMENT 'Minimum spend to qualify',
    max_uses_per_customer INT NULL,
    
    -- Display
    display_message VARCHAR(255) NULL,
    banner_color VARCHAR(7) DEFAULT '#ffc107',
    
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_time (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Happy Hour Categories (which categories the rule applies to)
CREATE TABLE IF NOT EXISTS happy_hour_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    happy_hour_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    
    FOREIGN KEY (happy_hour_id) REFERENCES happy_hour_rules(id) ON DELETE CASCADE,
    UNIQUE KEY uk_hh_cat (happy_hour_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Happy Hour Products (specific products the rule applies to)
CREATE TABLE IF NOT EXISTS happy_hour_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    happy_hour_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    special_price DECIMAL(15,2) NULL COMMENT 'Override price during happy hour',
    
    FOREIGN KEY (happy_hour_id) REFERENCES happy_hour_rules(id) ON DELETE CASCADE,
    UNIQUE KEY uk_hh_prod (happy_hour_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Happy Hour Usage Log
CREATE TABLE IF NOT EXISTS happy_hour_usage (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    happy_hour_id INT UNSIGNED NOT NULL,
    sale_id INT UNSIGNED NULL,
    tab_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    
    discount_applied DECIMAL(15,2) NOT NULL,
    original_amount DECIMAL(15,2) NOT NULL,
    final_amount DECIMAL(15,2) NOT NULL,
    
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_happy_hour (happy_hour_id),
    INDEX idx_date (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample happy hours
INSERT INTO happy_hour_rules (name, start_time, end_time, days_of_week, discount_type, discount_percent, display_message) VALUES
('Afternoon Happy Hour', '14:00:00', '17:00:00', 'monday,tuesday,wednesday,thursday,friday', 'percent', 25, '🍻 25% OFF all drinks!'),
('Weekend Special', '12:00:00', '15:00:00', 'saturday,sunday', 'percent', 30, '🎉 Weekend Special - 30% OFF!'),
('Late Night BOGO', '22:00:00', '23:59:59', 'friday,saturday', 'bogo', 0, '🍸 Buy One Get One Free!')
ON DUPLICATE KEY UPDATE name = VALUES(name);
