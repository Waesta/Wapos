-- Bar Tabs & BOT System Migration
-- Professional bar tab management with pre-authorization support

-- Bar Tabs - Open tabs by card/name with pre-auth
CREATE TABLE IF NOT EXISTS bar_tabs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tab_number VARCHAR(20) NOT NULL,
    tab_name VARCHAR(100) NOT NULL COMMENT 'Guest name or card last 4 digits',
    tab_type ENUM('name', 'card', 'room', 'member') DEFAULT 'name',
    
    -- Guest/Customer linking
    customer_id INT UNSIGNED NULL,
    room_booking_id INT UNSIGNED NULL COMMENT 'Link to room folio',
    member_id VARCHAR(50) NULL COMMENT 'Loyalty/membership ID',
    
    -- Pre-authorization
    preauth_amount DECIMAL(15,2) NULL COMMENT 'Card pre-auth hold amount',
    preauth_reference VARCHAR(100) NULL,
    preauth_expires_at TIMESTAMP NULL,
    card_last_four VARCHAR(4) NULL,
    card_type VARCHAR(20) NULL,
    
    -- Tab details
    location_id INT UNSIGNED NULL,
    bar_station VARCHAR(50) NULL COMMENT 'Main Bar, Pool Bar, etc.',
    table_id INT UNSIGNED NULL,
    server_id INT UNSIGNED NOT NULL COMMENT 'Bartender/waiter who opened',
    
    -- Financials
    subtotal DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tip_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) DEFAULT 0,
    
    -- Status
    status ENUM('open', 'pending_payment', 'paid', 'transferred', 'voided', 'charged_to_room') DEFAULT 'open',
    guest_count INT DEFAULT 1,
    notes TEXT,
    
    -- Timestamps
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_tab_number (tab_number),
    INDEX idx_status (status),
    INDEX idx_server (server_id),
    INDEX idx_customer (customer_id),
    INDEX idx_room (room_booking_id),
    INDEX idx_location (location_id),
    INDEX idx_opened (opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bar Tab Items
CREATE TABLE IF NOT EXISTS bar_tab_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tab_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    portion_id INT UNSIGNED NULL COMMENT 'Specific portion if applicable',
    recipe_id INT UNSIGNED NULL COMMENT 'If cocktail/recipe',
    
    item_name VARCHAR(200) NOT NULL,
    portion_name VARCHAR(100) NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    
    -- Modifiers
    modifiers JSON NULL COMMENT 'Ice, garnish, mixer preferences',
    special_instructions TEXT,
    
    -- Status
    status ENUM('pending', 'preparing', 'ready', 'served', 'voided') DEFAULT 'pending',
    served_by INT UNSIGNED NULL,
    served_at TIMESTAMP NULL,
    
    -- Void tracking
    voided_by INT UNSIGNED NULL,
    voided_at TIMESTAMP NULL,
    void_reason VARCHAR(255) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tab_id) REFERENCES bar_tabs(id) ON DELETE CASCADE,
    INDEX idx_tab (tab_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bar Tab Payments (supports split payments)
CREATE TABLE IF NOT EXISTS bar_tab_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tab_id INT UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'card', 'mpesa', 'airtel', 'mtn', 'room_charge', 'member_charge', 'comp', 'split') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    tip_amount DECIMAL(15,2) DEFAULT 0,
    
    -- Payment details
    reference_number VARCHAR(100) NULL,
    card_last_four VARCHAR(4) NULL,
    phone_number VARCHAR(20) NULL,
    room_number VARCHAR(20) NULL,
    
    -- For split payments
    split_guest_name VARCHAR(100) NULL,
    split_portion_percent DECIMAL(5,2) NULL,
    
    processed_by INT UNSIGNED NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tab_id) REFERENCES bar_tabs(id) ON DELETE CASCADE,
    INDEX idx_tab (tab_id),
    INDEX idx_method (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bar Tab Transfers (transfer between tabs or to restaurant)
CREATE TABLE IF NOT EXISTS bar_tab_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_tab_id INT UNSIGNED NOT NULL,
    to_tab_id INT UNSIGNED NULL COMMENT 'NULL if transferred to order',
    to_order_id INT UNSIGNED NULL COMMENT 'Restaurant order if applicable',
    to_room_folio_id INT UNSIGNED NULL COMMENT 'Room folio if applicable',
    
    transfer_type ENUM('tab_to_tab', 'tab_to_order', 'tab_to_room', 'items_only') NOT NULL,
    items_transferred JSON NULL COMMENT 'Specific items if partial transfer',
    amount_transferred DECIMAL(15,2) NOT NULL,
    
    transferred_by INT UNSIGNED NOT NULL,
    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255),
    
    INDEX idx_from (from_tab_id),
    INDEX idx_to_tab (to_tab_id),
    INDEX idx_to_order (to_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bar Order Tickets (BOT) - Separate from KOT
CREATE TABLE IF NOT EXISTS bar_order_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_number VARCHAR(30) NOT NULL COMMENT 'BOT-YYMMDD-XXXX format',
    
    -- Source linking
    source_type ENUM('tab', 'order', 'room_service', 'pos') NOT NULL,
    tab_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    sale_id INT UNSIGNED NULL,
    
    -- Routing
    bar_station VARCHAR(50) NOT NULL DEFAULT 'Main Bar',
    priority ENUM('normal', 'rush', 'vip') DEFAULT 'normal',
    
    -- Items
    items JSON NOT NULL COMMENT 'Array of items with portions/modifiers',
    item_count INT NOT NULL DEFAULT 0,
    
    -- Status
    status ENUM('pending', 'acknowledged', 'preparing', 'ready', 'picked_up', 'cancelled') DEFAULT 'pending',
    acknowledged_by INT UNSIGNED NULL,
    acknowledged_at TIMESTAMP NULL,
    prepared_by INT UNSIGNED NULL,
    prepared_at TIMESTAMP NULL,
    picked_up_at TIMESTAMP NULL,
    
    -- Timing
    estimated_time_minutes INT DEFAULT 5,
    actual_time_minutes INT NULL,
    
    -- Print tracking
    print_count INT DEFAULT 0,
    last_printed_at TIMESTAMP NULL,
    auto_printed TINYINT(1) DEFAULT 0,
    
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_bot_number (bot_number),
    INDEX idx_status (status),
    INDEX idx_station (bar_station),
    INDEX idx_tab (tab_id),
    INDEX idx_order (order_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bar Stations Configuration
CREATE TABLE IF NOT EXISTS bar_stations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    location_id INT UNSIGNED NULL,
    
    -- Display settings
    display_order INT DEFAULT 0,
    color VARCHAR(7) DEFAULT '#17a2b8',
    icon VARCHAR(50) DEFAULT 'bi-cup-straw',
    
    -- Capabilities
    serves_cocktails TINYINT(1) DEFAULT 1,
    serves_wine TINYINT(1) DEFAULT 1,
    serves_beer TINYINT(1) DEFAULT 1,
    serves_spirits TINYINT(1) DEFAULT 1,
    serves_soft_drinks TINYINT(1) DEFAULT 1,
    
    -- Printer
    printer_name VARCHAR(100) NULL,
    printer_ip VARCHAR(45) NULL,
    auto_print_bot TINYINT(1) DEFAULT 1,
    
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_code (code),
    INDEX idx_location (location_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Time Clock
CREATE TABLE IF NOT EXISTS employee_time_clock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NULL,
    
    clock_in_at TIMESTAMP NOT NULL,
    clock_out_at TIMESTAMP NULL,
    
    -- Break tracking
    break_start_at TIMESTAMP NULL,
    break_end_at TIMESTAMP NULL,
    total_break_minutes INT DEFAULT 0,
    
    -- Calculated hours
    scheduled_hours DECIMAL(5,2) NULL,
    actual_hours DECIMAL(5,2) NULL,
    overtime_hours DECIMAL(5,2) DEFAULT 0,
    
    -- Notes
    clock_in_note VARCHAR(255) NULL,
    clock_out_note VARCHAR(255) NULL,
    manager_note VARCHAR(255) NULL,
    
    -- Approval
    approved_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'adjusted', 'disputed') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_date (clock_in_at),
    INDEX idx_status (status),
    INDEX idx_location (location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default bar stations
INSERT INTO bar_stations (name, code, color, icon, display_order) VALUES
('Main Bar', 'MAIN', '#17a2b8', 'bi-cup-straw', 1),
('Pool Bar', 'POOL', '#20c997', 'bi-water', 2),
('Lounge Bar', 'LOUNGE', '#6f42c1', 'bi-moon-stars', 3),
('Restaurant Bar', 'REST', '#fd7e14', 'bi-shop', 4),
('Room Service Bar', 'ROOM', '#e83e8c', 'bi-door-open', 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Add bar_station to orders table for routing (ignore if column doesn't exist)
-- ALTER TABLE orders ADD COLUMN IF NOT EXISTS bar_station VARCHAR(50) NULL;
-- ALTER TABLE orders ADD COLUMN IF NOT EXISTS bot_number VARCHAR(30) NULL;

-- Add tab_id to sales for linking (ignore if fails)
-- ALTER TABLE sales ADD COLUMN IF NOT EXISTS bar_tab_id INT UNSIGNED NULL;
