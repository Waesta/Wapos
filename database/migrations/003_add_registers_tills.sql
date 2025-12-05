-- Migration 003: Add Registers/Tills Support
-- Enables multiple POS terminals within the same location
-- Use case: Supermarket with multiple cashiers, Restaurant with bar counter

-- Registers/Tills table
CREATE TABLE IF NOT EXISTS registers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    register_number VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    register_type ENUM('pos', 'bar', 'restaurant', 'retail', 'service') DEFAULT 'pos',
    description TEXT,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    is_active TINYINT(1) DEFAULT 1,
    opening_balance DECIMAL(15,2) DEFAULT 0.00,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    last_opened_at DATETIME,
    last_closed_at DATETIME,
    last_opened_by INT UNSIGNED,
    last_closed_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_location_register (location_id, register_number),
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (last_opened_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (last_closed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_location (location_id),
    INDEX idx_type (register_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Register Sessions (Shifts)
CREATE TABLE IF NOT EXISTS register_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    register_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    session_number VARCHAR(50) NOT NULL,
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    closing_balance DECIMAL(15,2),
    expected_balance DECIMAL(15,2),
    variance DECIMAL(15,2),
    cash_sales DECIMAL(15,2) DEFAULT 0.00,
    card_sales DECIMAL(15,2) DEFAULT 0.00,
    mobile_sales DECIMAL(15,2) DEFAULT 0.00,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_refunds DECIMAL(15,2) DEFAULT 0.00,
    total_voids DECIMAL(15,2) DEFAULT 0.00,
    cash_in DECIMAL(15,2) DEFAULT 0.00,
    cash_out DECIMAL(15,2) DEFAULT 0.00,
    transaction_count INT DEFAULT 0,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME,
    status ENUM('open', 'closed', 'suspended') DEFAULT 'open',
    closing_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_session_number (session_number),
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_register (register_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_opened_at (opened_at)
) ENGINE=InnoDB;

-- Cash movements within register sessions
CREATE TABLE IF NOT EXISTS register_cash_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    movement_type ENUM('cash_in', 'cash_out', 'float', 'pickup', 'drop') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    notes TEXT,
    authorized_by INT UNSIGNED,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES register_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (authorized_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_session (session_id),
    INDEX idx_type (movement_type)
) ENGINE=InnoDB;

-- Add register_id to sales table
ALTER TABLE sales 
ADD COLUMN register_id INT UNSIGNED AFTER location_id,
ADD COLUMN session_id INT UNSIGNED AFTER register_id,
ADD INDEX idx_register (register_id),
ADD INDEX idx_session (session_id);

-- Add register_id to orders table (restaurant)
ALTER TABLE orders 
ADD COLUMN register_id INT UNSIGNED AFTER location_id,
ADD INDEX idx_register (register_id);

-- User-Register assignments (which users can use which registers)
CREATE TABLE IF NOT EXISTS user_registers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    register_id INT UNSIGNED NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_register (user_id, register_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert default register for each existing location
INSERT INTO registers (location_id, register_number, name, register_type, is_active)
SELECT id, 'REG-01', CONCAT(name, ' - Main Register'), 'pos', 1
FROM locations
WHERE NOT EXISTS (SELECT 1 FROM registers WHERE registers.location_id = locations.id);

-- Sample registers for testing (run after locations exist)
-- INSERT INTO registers (location_id, register_number, name, register_type) VALUES
-- (1, 'REG-01', 'Checkout 1', 'retail'),
-- (1, 'REG-02', 'Checkout 2', 'retail'),
-- (1, 'REG-03', 'Checkout 3', 'retail'),
-- (1, 'BAR-01', 'Main Bar', 'bar'),
-- (1, 'REST-01', 'Restaurant Counter', 'restaurant');
