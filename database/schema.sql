-- WAPOS Database Schema
-- Simple and clean structure for Point of Sale System

CREATE DATABASE IF NOT EXISTS wapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wapos;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM(
        'admin',
        'manager',
        'accountant',
        'cashier',
        'waiter',
        'bartender',
        'inventory_manager',
        'rider',
        'frontdesk',
        'housekeeping_manager',
        'housekeeping_staff',
        'maintenance_manager',
        'maintenance_staff',
        'technician',
        'engineer',
        'developer',
        'front_office_manager',
        'guest_relations_manager',
        'concierge',
        'spa_manager',
        'spa_staff',
        'events_manager',
        'banquet_supervisor',
        'room_service_manager',
        'room_service_staff',
        'security_manager',
        'security_staff',
        'hr_manager',
        'hr_staff',
        'revenue_manager',
        'sales_manager',
        'sales_executive'
    ) DEFAULT 'cashier',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    sku VARCHAR(50) UNIQUE,
    barcode VARCHAR(50) UNIQUE,
    cost_price DECIMAL(15,2) DEFAULT 0,
    selling_price DECIMAL(15,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 10,
    unit VARCHAR(20) DEFAULT 'pcs',
    tax_rate DECIMAL(5,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_sku (sku),
    INDEX idx_barcode (barcode),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- Sales Table
CREATE TABLE IF NOT EXISTS sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    subtotal DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) NOT NULL,
    change_amount DECIMAL(15,2) DEFAULT 0,
    payment_method ENUM('cash', 'card', 'mobile_money', 'bank_transfer') DEFAULT 'cash',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_sale_number (sale_number),
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Sale Items Table
CREATE TABLE IF NOT EXISTS sale_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    total_price DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    total_purchases DECIMAL(15,2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone)
) ENGINE=InnoDB;

-- Expenses Table
CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'mobile_money', 'bank_transfer') DEFAULT 'cash',
    receipt_number VARCHAR(50),
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_date (expense_date),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- Stock Adjustments Table
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    adjustment_type ENUM('in', 'out', 'correction') NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_product (product_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- System Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
-- Hash generated with: password_hash('admin123', PASSWORD_ARGON2ID)
INSERT INTO users (username, password, full_name, email, role) VALUES 
('admin', '$argon2id$v=19$m=65536,t=4,p=1$bGhSYnQxaHBCMjRhOE5mSQ$eZPm9YF0C+4LRqSYd8xNGmPGVJhPqgWYN9bKYzH5h1I', 'Administrator', 'admin@wapos.local', 'admin'),
('developer', '$argon2id$v=19$m=65536,t=4,p=1$bGhSYnQxaHBCMjRhOE5mSQ$eZPm9YF0C+4LRqSYd8xNGmPGVJhPqgWYN9bKYzH5h1I', 'Developer', 'developer@wapos.local', 'admin');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('business_name', 'WAPOS Store', 'Business name for receipts and reports'),
('business_address', '', 'Business address'),
('business_phone', '', 'Business phone number'),
('business_email', '', 'Business email address'),
('tax_rate', '0', 'Default tax rate percentage'),
('currency', '$', 'Currency symbol'),
('currency_code', 'USD', 'Currency code (USD, EUR, GBP, etc)'),
('receipt_header', '', 'Header text for receipts'),
('receipt_footer', 'Thank you for your business!', 'Footer text for receipts'),
('schema_version', '1', 'Current database schema version');

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Food & Beverages', 'Food items and drinks'),
('Electronics', 'Electronic devices and accessories'),
('Clothing', 'Apparel and fashion items'),
('Household', 'Household items and supplies'),
('Other', 'Miscellaneous items');
