-- WAPOS Phase 2 Schema
-- Restaurant, Room Booking, Delivery, and Advanced Features

USE wapos;

-- Restaurant Tables
CREATE TABLE IF NOT EXISTS restaurant_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(20) NOT NULL,
    table_name VARCHAR(100),
    capacity INT DEFAULT 4,
    floor VARCHAR(50),
    status ENUM('available', 'occupied', 'reserved', 'maintenance') DEFAULT 'available',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_table_number (table_number)
) ENGINE=InnoDB;

-- Order Modifiers (e.g., "Extra cheese", "No onions")
CREATE TABLE IF NOT EXISTS modifiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT 0,
    category VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Order Types and Customization
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_type ENUM('dine-in', 'takeout', 'delivery', 'retail') NOT NULL,
    table_id INT UNSIGNED,
    customer_id INT UNSIGNED,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    delivery_address TEXT,
    delivery_instructions TEXT,
    rider_id INT UNSIGNED,
    status ENUM('pending', 'preparing', 'ready', 'delivered', 'completed', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    delivery_fee DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
    user_id INT UNSIGNED NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES restaurant_tables(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_order_type (order_type),
    INDEX idx_status (status),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Order Items with Modifiers
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    modifiers_data JSON,
    special_instructions TEXT,
    status ENUM('pending', 'preparing', 'ready', 'served') DEFAULT 'pending',
    total_price DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Room Types
CREATE TABLE IF NOT EXISTS room_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    capacity INT DEFAULT 2,
    amenities JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Rooms
CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    room_type_id INT UNSIGNED NOT NULL,
    floor VARCHAR(20),
    status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id),
    INDEX idx_status (status),
    INDEX idx_room_number (room_number)
) ENGINE=InnoDB;

-- Room Bookings
CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED,
    guest_name VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(20) NOT NULL,
    guest_email VARCHAR(100),
    guest_id_number VARCHAR(50),
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    check_in_time DATETIME,
    check_out_time DATETIME,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    room_rate DECIMAL(10,2) NOT NULL,
    total_nights INT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    booking_status ENUM('pending', 'confirmed', 'checked-in', 'checked-out', 'cancelled') DEFAULT 'pending',
    special_requests TEXT,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_dates (check_in_date, check_out_date),
    INDEX idx_status (booking_status)
) ENGINE=InnoDB;

-- Delivery Riders
CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(50),
    status ENUM('available', 'busy', 'offline') DEFAULT 'available',
    is_active TINYINT(1) DEFAULT 1,
    total_deliveries INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Delivery Tracking
CREATE TABLE IF NOT EXISTS deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED,
    pickup_address TEXT,
    delivery_address TEXT NOT NULL,
    delivery_instructions TEXT,
    estimated_time INT,
    actual_delivery_time DATETIME,
    status ENUM('pending', 'assigned', 'picked-up', 'in-transit', 'delivered', 'failed') DEFAULT 'pending',
    customer_rating INT,
    rider_rating INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_rider (rider_id)
) ENGINE=InnoDB;

-- Locations for Multi-Location Support
CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    manager_id INT UNSIGNED,
    is_active TINYINT(1) DEFAULT 1,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (code)
) ENGINE=InnoDB;

-- Location Stock (for multi-location inventory)
CREATE TABLE IF NOT EXISTS location_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT DEFAULT 0,
    min_level INT DEFAULT 10,
    last_restocked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY idx_location_product (location_id, product_id),
    INDEX idx_location (location_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- Expenses with Categories
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE expenses 
ADD COLUMN category_id INT UNSIGNED AFTER user_id,
ADD COLUMN location_id INT UNSIGNED AFTER category_id,
ADD FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
ADD FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL;

-- Audit Logs for Security
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT UNSIGNED,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Offline Sync Queue
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation VARCHAR(20) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_data JSON NOT NULL,
    status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- System Backups Log
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    backup_file VARCHAR(255) NOT NULL,
    backup_size BIGINT,
    backup_type ENUM('auto', 'manual') DEFAULT 'manual',
    status ENUM('success', 'failed') DEFAULT 'success',
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Update users table for additional roles
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'manager', 'cashier', 'waiter', 'inventory_manager', 'rider') DEFAULT 'cashier';

-- Add location_id to users
ALTER TABLE users 
ADD COLUMN location_id INT UNSIGNED AFTER role,
ADD FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL;

-- Add location_id to sales
ALTER TABLE sales 
ADD COLUMN location_id INT UNSIGNED AFTER user_id,
ADD FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL;

-- Insert sample restaurant tables
INSERT INTO restaurant_tables (table_number, table_name, capacity, floor, status) VALUES
('T1', 'Table 1', 4, 'Ground Floor', 'available'),
('T2', 'Table 2', 4, 'Ground Floor', 'available'),
('T3', 'Table 3', 2, 'Ground Floor', 'available'),
('T4', 'Table 4', 6, 'Ground Floor', 'available'),
('T5', 'Table 5', 4, 'First Floor', 'available'),
('T6', 'Table 6', 8, 'First Floor', 'available');

-- Insert sample modifiers
INSERT INTO modifiers (name, price, category) VALUES
('Extra Cheese', 50.00, 'Add-ons'),
('No Onions', 0.00, 'Remove'),
('Extra Sauce', 30.00, 'Add-ons'),
('Spicy', 0.00, 'Preferences'),
('Less Salt', 0.00, 'Preferences'),
('Extra Meat', 100.00, 'Add-ons');

-- Insert sample room types
INSERT INTO room_types (name, description, base_price, capacity, amenities) VALUES
('Standard Room', 'Comfortable room with basic amenities', 3000.00, 2, '["TV", "WiFi", "AC"]'),
('Deluxe Room', 'Spacious room with premium amenities', 5000.00, 2, '["TV", "WiFi", "AC", "Mini Bar", "Balcony"]'),
('Suite', 'Luxurious suite with separate living area', 8000.00, 4, '["TV", "WiFi", "AC", "Mini Bar", "Balcony", "Kitchen", "Living Room"]');

-- Insert sample rooms
INSERT INTO rooms (room_number, room_type_id, floor, status) VALUES
('101', 1, 'Ground Floor', 'available'),
('102', 1, 'Ground Floor', 'available'),
('103', 1, 'Ground Floor', 'available'),
('201', 2, 'First Floor', 'available'),
('202', 2, 'First Floor', 'available'),
('301', 3, 'Second Floor', 'available');

-- Insert default location
INSERT INTO locations (name, code, address, is_active) VALUES
('Main Branch', 'MAIN', 'Main Store Address', 1);

-- Insert expense categories
INSERT INTO expense_categories (name, description) VALUES
('Utilities', 'Electricity, Water, Internet'),
('Rent', 'Property rent and lease'),
('Salaries', 'Employee salaries and wages'),
('Supplies', 'Office and store supplies'),
('Maintenance', 'Equipment and facility maintenance'),
('Marketing', 'Advertising and promotional activities'),
('Transportation', 'Delivery and logistics costs'),
('Other', 'Miscellaneous expenses');
