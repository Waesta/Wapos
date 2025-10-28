-- =====================================================
-- WAPOS UNIFIED POS SYSTEM - DATABASE SCHEMA
-- Version: 1.0
-- Date: October 27, 2025
-- Architecture: MySQL 5.7+ Compatible
-- =====================================================

-- Drop existing database if exists (for fresh install)
-- DROP DATABASE IF EXISTS wapos;
-- CREATE DATABASE wapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE wapos;

-- =====================================================
-- 1. SYSTEM & CONFIGURATION TABLES
-- =====================================================

-- System settings and configuration
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Locations/Branches for multi-location support
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    timezone VARCHAR(50) DEFAULT 'UTC',
    currency_code VARCHAR(3) DEFAULT 'USD',
    tax_rate DECIMAL(5,4) DEFAULT 0.0000,
    service_charge_rate DECIMAL(5,4) DEFAULT 0.0000,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- 2. USER MANAGEMENT & AUTHENTICATION
-- =====================================================

-- User roles for RBAC
CREATE TABLE user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON, -- Store permissions as JSON array
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users/Employees
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    location_id INT,
    pin_code VARCHAR(10), -- For quick POS login
    hourly_rate DECIMAL(10,2),
    commission_rate DECIMAL(5,4),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- User sessions for security
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    device_info TEXT,
    ip_address VARCHAR(45),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 3. CUSTOMER RELATIONSHIP MANAGEMENT
-- =====================================================

-- Customer groups for pricing/discounts
CREATE TABLE customer_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(50) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_code VARCHAR(20) UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    company_name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    mobile VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    customer_group_id INT,
    credit_limit DECIMAL(12,2) DEFAULT 0.00,
    current_balance DECIMAL(12,2) DEFAULT 0.00,
    loyalty_points INT DEFAULT 0,
    total_visits INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_group_id) REFERENCES customer_groups(id)
);

-- Customer addresses (for delivery)
CREATE TABLE customer_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    address_type ENUM('billing', 'shipping', 'delivery') DEFAULT 'delivery',
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    delivery_instructions TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- =====================================================
-- 4. PRODUCT & INVENTORY MANAGEMENT
-- =====================================================

-- Product categories
CREATE TABLE product_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NULL,
    category_name VARCHAR(100) NOT NULL,
    category_code VARCHAR(20) UNIQUE,
    description TEXT,
    image_url VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id)
);

-- Brands
CREATE TABLE brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    brand_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    logo_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Units of measure
CREATE TABLE units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_name VARCHAR(50) NOT NULL UNIQUE,
    unit_symbol VARCHAR(10) NOT NULL,
    base_unit_id INT NULL, -- For unit conversions
    conversion_factor DECIMAL(10,4) DEFAULT 1.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_unit_id) REFERENCES units(id)
);

-- Products (master table)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(100) UNIQUE,
    product_name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    brand_id INT,
    unit_id INT,
    product_type ENUM('simple', 'variant', 'kit', 'service') DEFAULT 'simple',
    cost_price DECIMAL(12,4) DEFAULT 0.0000,
    selling_price DECIMAL(12,4) NOT NULL,
    min_selling_price DECIMAL(12,4),
    tax_rate DECIMAL(5,4) DEFAULT 0.0000,
    is_stockable BOOLEAN DEFAULT TRUE,
    track_serial BOOLEAN DEFAULT FALSE,
    reorder_level INT DEFAULT 0,
    reorder_quantity INT DEFAULT 0,
    shelf_life_days INT,
    weight DECIMAL(8,3),
    dimensions VARCHAR(50), -- L x W x H
    image_url VARCHAR(255),
    gallery_images JSON, -- Array of image URLs
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

-- Product variants (for size, color, etc.)
CREATE TABLE product_variants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_product_id INT NOT NULL,
    variant_code VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(100) UNIQUE,
    variant_name VARCHAR(200) NOT NULL,
    attributes JSON, -- {"size": "Large", "color": "Red"}
    cost_price DECIMAL(12,4),
    selling_price DECIMAL(12,4) NOT NULL,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Recipe/BOM for kits and restaurant items
CREATE TABLE product_recipes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    ingredient_product_id INT NOT NULL,
    quantity DECIMAL(10,4) NOT NULL,
    unit_id INT,
    cost_per_unit DECIMAL(12,4),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_product_id) REFERENCES products(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

-- Stock levels per location
CREATE TABLE stock_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT,
    variant_id INT,
    location_id INT NOT NULL,
    quantity_on_hand DECIMAL(12,4) DEFAULT 0.0000,
    quantity_reserved DECIMAL(12,4) DEFAULT 0.0000,
    quantity_available DECIMAL(12,4) GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED,
    last_cost DECIMAL(12,4),
    average_cost DECIMAL(12,4),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    UNIQUE KEY unique_stock (product_id, variant_id, location_id)
);

-- =====================================================
-- 5. RESTAURANT MANAGEMENT
-- =====================================================

-- Restaurant areas/sections
CREATE TABLE restaurant_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    area_name VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Restaurant tables
CREATE TABLE restaurant_tables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    area_id INT,
    table_number VARCHAR(20) NOT NULL,
    table_name VARCHAR(100),
    capacity INT DEFAULT 4,
    position_x INT DEFAULT 0, -- For floor plan
    position_y INT DEFAULT 0,
    table_shape ENUM('square', 'round', 'rectangle') DEFAULT 'square',
    status ENUM('available', 'occupied', 'reserved', 'cleaning', 'maintenance') DEFAULT 'available',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (area_id) REFERENCES restaurant_areas(id),
    UNIQUE KEY unique_table (location_id, table_number)
);

-- Table reservations
CREATE TABLE table_reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_id INT NOT NULL,
    customer_id INT,
    guest_name VARCHAR(100),
    guest_phone VARCHAR(20),
    party_size INT NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    duration_minutes INT DEFAULT 120,
    status ENUM('confirmed', 'seated', 'completed', 'cancelled', 'no_show') DEFAULT 'confirmed',
    special_requests TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES restaurant_tables(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- 6. ROOM MANAGEMENT (LODGING)
-- =====================================================

-- Room types
CREATE TABLE room_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    base_rate DECIMAL(10,2) NOT NULL,
    max_occupancy INT DEFAULT 2,
    amenities JSON, -- Array of amenities
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Rooms
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    room_type_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    floor_number INT,
    status ENUM('available', 'occupied', 'maintenance', 'cleaning', 'out_of_order') DEFAULT 'available',
    last_cleaned TIMESTAMP NULL,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (room_type_id) REFERENCES room_types(id),
    UNIQUE KEY unique_room (location_id, room_number)
);

-- Room bookings/reservations
CREATE TABLE room_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(50) NOT NULL UNIQUE,
    room_id INT NOT NULL,
    customer_id INT,
    guest_name VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(20),
    guest_email VARCHAR(100),
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    nights INT GENERATED ALWAYS AS (DATEDIFF(check_out_date, check_in_date)) STORED,
    room_rate DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(12,2),
    status ENUM('confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'confirmed',
    special_requests TEXT,
    created_by INT,
    checked_in_at TIMESTAMP NULL,
    checked_out_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- 7. SALES & TRANSACTIONS
-- =====================================================

-- Sales transactions (unified for all modules)
CREATE TABLE sales_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(50) NOT NULL UNIQUE,
    location_id INT NOT NULL,
    transaction_type ENUM('sale', 'return', 'void', 'hold') DEFAULT 'sale',
    module_type ENUM('retail', 'restaurant', 'room', 'delivery') DEFAULT 'retail',
    customer_id INT,
    table_id INT, -- For restaurant orders
    room_booking_id INT, -- For room charges
    cashier_id INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    service_charge DECIMAL(12,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    order_status ENUM('pending', 'preparing', 'ready', 'served', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    is_offline_transaction BOOLEAN DEFAULT FALSE,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (table_id) REFERENCES restaurant_tables(id),
    FOREIGN KEY (room_booking_id) REFERENCES room_bookings(id),
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- Sales transaction items
CREATE TABLE sales_transaction_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    product_id INT,
    variant_id INT,
    item_name VARCHAR(200) NOT NULL, -- Store name for offline compatibility
    quantity DECIMAL(10,4) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    cost_price DECIMAL(12,4),
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,4) DEFAULT 0.0000,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    modifiers JSON, -- Store item modifiers
    kitchen_notes TEXT,
    course_number INT DEFAULT 1,
    item_status ENUM('pending', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

-- Payment methods
CREATE TABLE payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    method_name VARCHAR(50) NOT NULL UNIQUE,
    method_type ENUM('cash', 'card', 'mobile', 'account', 'voucher') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    requires_reference BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transaction payments
CREATE TABLE transaction_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(100),
    card_last_four VARCHAR(4),
    authorization_code VARCHAR(50),
    gateway_response JSON,
    payment_status ENUM('pending', 'approved', 'declined', 'refunded') DEFAULT 'pending',
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
);

-- =====================================================
-- 8. DELIVERY MANAGEMENT
-- =====================================================

-- Delivery zones
CREATE TABLE delivery_zones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    delivery_fee DECIMAL(8,2) DEFAULT 0.00,
    minimum_order DECIMAL(10,2) DEFAULT 0.00,
    estimated_time_minutes INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Delivery drivers
CREATE TABLE delivery_drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    driver_license VARCHAR(50),
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(20),
    phone VARCHAR(20),
    is_available BOOLEAN DEFAULT TRUE,
    current_cash DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Delivery orders (extends sales_transactions)
CREATE TABLE delivery_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    customer_address_id INT,
    delivery_address TEXT NOT NULL,
    delivery_phone VARCHAR(20),
    delivery_zone_id INT,
    driver_id INT,
    delivery_fee DECIMAL(8,2) DEFAULT 0.00,
    estimated_delivery_time TIMESTAMP,
    actual_delivery_time TIMESTAMP NULL,
    delivery_status ENUM('pending', 'assigned', 'picked_up', 'en_route', 'delivered', 'failed') DEFAULT 'pending',
    delivery_notes TEXT,
    customer_rating INT, -- 1-5 stars
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id),
    FOREIGN KEY (customer_address_id) REFERENCES customer_addresses(id),
    FOREIGN KEY (delivery_zone_id) REFERENCES delivery_zones(id),
    FOREIGN KEY (driver_id) REFERENCES delivery_drivers(id)
);

-- =====================================================
-- 9. ACCOUNTING & FINANCIAL
-- =====================================================

-- Chart of accounts
CREATE TABLE chart_of_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    account_name VARCHAR(100) NOT NULL,
    account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    parent_account_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(id)
);

-- Journal entries
CREATE TABLE journal_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_number VARCHAR(50) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    reference_type ENUM('sale', 'purchase', 'payment', 'adjustment', 'manual') NOT NULL,
    reference_id INT,
    description TEXT,
    total_debit DECIMAL(15,2) NOT NULL,
    total_credit DECIMAL(15,2) NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Journal entry lines
CREATE TABLE journal_entry_lines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit_amount DECIMAL(15,2) DEFAULT 0.00,
    credit_amount DECIMAL(15,2) DEFAULT 0.00,
    description TEXT,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- =====================================================
-- 10. SYSTEM AUDIT & LOGGING
-- =====================================================

-- Audit log for tracking critical actions
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- System sync log for offline transactions
CREATE TABLE sync_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100),
    sync_type ENUM('full', 'incremental', 'conflict_resolution') DEFAULT 'incremental',
    records_synced INT DEFAULT 0,
    conflicts_resolved INT DEFAULT 0,
    sync_status ENUM('success', 'partial', 'failed') DEFAULT 'success',
    error_message TEXT,
    sync_started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_completed_at TIMESTAMP NULL
);

-- =====================================================
-- 11. INDEXES FOR PERFORMANCE
-- =====================================================

-- Performance indexes
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_products_active ON products(is_active);
CREATE INDEX idx_stock_location_product ON stock_levels(location_id, product_id);
CREATE INDEX idx_transactions_date ON sales_transactions(created_at);
CREATE INDEX idx_transactions_cashier ON sales_transactions(cashier_id);
CREATE INDEX idx_transactions_customer ON sales_transactions(customer_id);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_pin ON users(pin_code);
CREATE INDEX idx_bookings_dates ON room_bookings(check_in_date, check_out_date);
CREATE INDEX idx_reservations_date ON table_reservations(reservation_date, reservation_time);

-- =====================================================
-- 12. INITIAL DATA SETUP
-- =====================================================

-- Insert default location
INSERT INTO locations (location_code, name, address, currency_code, tax_rate) 
VALUES ('MAIN', 'Main Location', '123 Business St', 'USD', 0.0875);

-- Insert default user roles
INSERT INTO user_roles (role_name, display_name, description, permissions) VALUES
('admin', 'Administrator', 'Full system access', '["*"]'),
('manager', 'Manager', 'Management access', '["sales.*", "reports.*", "inventory.*", "customers.*"]'),
('cashier', 'Cashier', 'POS access only', '["sales.create", "sales.view", "customers.view"]'),
('waiter', 'Waiter', 'Restaurant service', '["restaurant.*", "sales.create"]'),
('housekeeper', 'Housekeeper', 'Room management', '["rooms.status", "rooms.cleaning"]');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, location_id, pin_code) 
VALUES ('admin', 'admin@wapos.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 1, 1, '1234');

-- Insert default payment methods
INSERT INTO payment_methods (method_name, method_type, requires_reference) VALUES
('Cash', 'cash', FALSE),
('Credit Card', 'card', TRUE),
('Debit Card', 'card', TRUE),
('Mobile Payment', 'mobile', TRUE),
('Customer Account', 'account', FALSE),
('Gift Voucher', 'voucher', TRUE);

-- Insert default units
INSERT INTO units (unit_name, unit_symbol) VALUES
('Piece', 'pcs'),
('Kilogram', 'kg'),
('Gram', 'g'),
('Liter', 'L'),
('Milliliter', 'ml'),
('Meter', 'm'),
('Hour', 'hr'),
('Service', 'svc');

-- Insert default customer group
INSERT INTO customer_groups (group_name, discount_percentage, description) 
VALUES ('Regular', 0.00, 'Regular customers'), ('VIP', 5.00, 'VIP customers with 5% discount');

-- Insert basic chart of accounts
INSERT INTO chart_of_accounts (account_code, account_name, account_type) VALUES
('1000', 'Cash', 'asset'),
('1100', 'Accounts Receivable', 'asset'),
('1200', 'Inventory', 'asset'),
('2000', 'Accounts Payable', 'liability'),
('3000', 'Owner Equity', 'equity'),
('4000', 'Sales Revenue', 'revenue'),
('5000', 'Cost of Goods Sold', 'expense'),
('6000', 'Operating Expenses', 'expense');

-- =====================================================
-- END OF SCHEMA
-- =====================================================
