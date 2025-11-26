-- Additional tables to complete the system to 100%

-- Add suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    tax_id VARCHAR(100),
    payment_terms VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add product batches table
CREATE TABLE IF NOT EXISTS product_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2),
    expiry_date DATE,
    supplier_id INT UNSIGNED,
    received_date DATE NOT NULL,
    location_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add stock movements table
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in', 'out', 'transfer', 'damaged', 'adjustment') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    old_quantity DECIMAL(10,3) NOT NULL,
    new_quantity DECIMAL(10,3) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    notes TEXT,
    source_module VARCHAR(50) DEFAULT NULL,
    reference VARCHAR(50),
    user_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add stock transfers table
CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) UNIQUE NOT NULL,
    from_location_id INT UNSIGNED NOT NULL,
    to_location_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'in_transit', 'completed', 'cancelled') DEFAULT 'pending',
    transfer_date DATE NOT NULL,
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED,
    received_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_location_id) REFERENCES locations(id),
    FOREIGN KEY (to_location_id) REFERENCES locations(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Add stock transfer items
CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    received_quantity INT DEFAULT 0,
    notes TEXT,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Add customer addresses for delivery
CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    address_type ENUM('home', 'work', 'other') DEFAULT 'home',
    street_address TEXT NOT NULL,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    landmark TEXT,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add payment installments table
CREATE TABLE IF NOT EXISTS payment_installments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED,
    booking_id INT UNSIGNED,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_date DATETIME NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Add reorder alerts table
CREATE TABLE IF NOT EXISTS reorder_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    alert_date DATE NOT NULL,
    current_stock INT NOT NULL,
    min_stock_level INT NOT NULL,
    status ENUM('pending', 'ordered', 'resolved', 'ignored') DEFAULT 'pending',
    resolved_at DATETIME,
    resolved_by INT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Add comprehensive audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT UNSIGNED,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add scheduled tasks table for automated backups
CREATE TABLE IF NOT EXISTS scheduled_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) NOT NULL,
    task_type ENUM('backup', 'report', 'alert', 'cleanup') NOT NULL,
    schedule VARCHAR(50) NOT NULL,
    last_run DATETIME,
    next_run DATETIME,
    status ENUM('active', 'inactive', 'running', 'failed') DEFAULT 'active',
    config TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Modify products table to add missing fields
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS sku VARCHAR(100) UNIQUE AFTER id,
ADD COLUMN IF NOT EXISTS supplier_id INT UNSIGNED AFTER category_id,
ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT 'pcs' AFTER selling_price,
ADD COLUMN IF NOT EXISTS has_expiry TINYINT(1) DEFAULT 0 AFTER unit,
ADD COLUMN IF NOT EXISTS alert_before_days INT DEFAULT 30 AFTER has_expiry,
ADD FOREIGN KEY IF NOT EXISTS (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- Modify customers table to add delivery preferences
ALTER TABLE customers
ADD COLUMN IF NOT EXISTS delivery_notes TEXT AFTER address,
ADD COLUMN IF NOT EXISTS preferred_delivery_time VARCHAR(50) AFTER delivery_notes;

-- Add delivery scheduling to orders
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS delivery_address_id INT UNSIGNED AFTER customer_phone,
ADD COLUMN IF NOT EXISTS scheduled_delivery_time DATETIME AFTER delivery_address_id,
ADD COLUMN IF NOT EXISTS delivery_instructions TEXT AFTER scheduled_delivery_time;

-- Insert default suppliers
INSERT INTO suppliers (name, contact_person, phone, email) VALUES
('Local Supplier', 'John Doe', '+1234567890', 'supplier@example.com'),
('Wholesale Distributor', 'Jane Smith', '+0987654321', 'wholesale@example.com')
ON DUPLICATE KEY UPDATE name=name;

-- Insert scheduled backup task
INSERT INTO scheduled_tasks (task_name, task_type, schedule, next_run, config) VALUES
('Daily Database Backup', 'backup', 'daily', DATE_ADD(NOW(), INTERVAL 1 DAY), '{"time":"02:00","retention_days":30}'),
('Weekly Sales Report', 'report', 'weekly', DATE_ADD(NOW(), INTERVAL 7 DAY), '{"day":"monday","time":"09:00"}'),
('Low Stock Alert', 'alert', 'daily', DATE_ADD(NOW(), INTERVAL 1 DAY), '{"time":"08:00"}')
ON DUPLICATE KEY UPDATE task_name=task_name;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_table ON audit_log(table_name, record_id);
CREATE INDEX IF NOT EXISTS idx_audit_date ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_batch_expiry ON product_batches(expiry_date);
CREATE INDEX IF NOT EXISTS idx_reorder_status ON reorder_alerts(status);
CREATE INDEX IF NOT EXISTS idx_transfer_status ON stock_transfers(status);
