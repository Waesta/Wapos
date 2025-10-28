-- Restaurant Receipt System Database Schema
-- Add these tables to support the restaurant receipt workflow

-- Print logging table to track all receipt printing
CREATE TABLE IF NOT EXISTS print_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    receipt_type ENUM('kitchen', 'invoice', 'receipt', 'bar') NOT NULL,
    action ENUM('print', 'reprint', 'failed') NOT NULL,
    copies INT DEFAULT 1,
    printer_name VARCHAR(100),
    printer_ip VARCHAR(45),
    user_id INT UNSIGNED,
    error_message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_receipt_type (receipt_type),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB;

-- Printer configuration table
CREATE TABLE IF NOT EXISTS printer_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    printer_name VARCHAR(100) NOT NULL,
    printer_type ENUM('kitchen', 'customer', 'bar', 'receipt') NOT NULL,
    ip_address VARCHAR(45),
    port INT DEFAULT 9100,
    is_active BOOLEAN DEFAULT TRUE,
    zone_categories JSON, -- Store category IDs for zone-based printing
    print_format ENUM('compact', 'standard', 'detailed') DEFAULT 'standard',
    auto_print BOOLEAN DEFAULT TRUE,
    copies INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_printer_type (printer_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Add columns to products table for restaurant-specific features
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS prep_time INT DEFAULT 0 COMMENT 'Preparation time in minutes',
ADD COLUMN IF NOT EXISTS allergens VARCHAR(255) COMMENT 'Comma-separated list of allergens',
ADD COLUMN IF NOT EXISTS kitchen_notes TEXT COMMENT 'Special preparation notes for kitchen';

-- Add columns to order_items for enhanced tracking
ALTER TABLE order_items 
ADD COLUMN IF NOT EXISTS prep_status ENUM('pending', 'preparing', 'ready', 'served') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS prep_started_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS prep_completed_at TIMESTAMP NULL;

-- Receipt templates table for customizable receipt formats
CREATE TABLE IF NOT EXISTS receipt_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    receipt_type ENUM('kitchen', 'invoice', 'receipt', 'bar') NOT NULL,
    template_content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_active_template (receipt_type, is_active),
    INDEX idx_receipt_type (receipt_type)
) ENGINE=InnoDB;

-- Insert default printer configurations
INSERT INTO printer_config (printer_name, printer_type, print_format, auto_print, copies) VALUES
('Kitchen Printer', 'kitchen', 'standard', TRUE, 1),
('Customer Printer', 'customer', 'detailed', TRUE, 1),
('Bar Printer', 'bar', 'compact', TRUE, 1)
ON DUPLICATE KEY UPDATE printer_name = VALUES(printer_name);

-- Insert default receipt templates
INSERT INTO receipt_templates (template_name, receipt_type, template_content, is_active) VALUES
('Standard Kitchen Order', 'kitchen', 'kitchen_order_standard', TRUE),
('Detailed Customer Invoice', 'invoice', 'customer_invoice_detailed', TRUE),
('Premium Customer Receipt', 'receipt', 'customer_receipt_premium', TRUE),
('Compact Bar Order', 'bar', 'bar_order_compact', TRUE)
ON DUPLICATE KEY UPDATE template_content = VALUES(template_content);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_orders_payment_status ON orders(payment_status);
CREATE INDEX IF NOT EXISTS idx_orders_order_type ON orders(order_type);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_order_items_status ON order_items(status);

-- Create view for order summary with print status
CREATE OR REPLACE VIEW order_print_summary AS
SELECT 
    o.id,
    o.order_number,
    o.order_type,
    o.status,
    o.payment_status,
    o.total_amount,
    o.created_at,
    COUNT(DISTINCT CASE WHEN pl.receipt_type = 'kitchen' THEN pl.id END) as kitchen_prints,
    COUNT(DISTINCT CASE WHEN pl.receipt_type = 'invoice' THEN pl.id END) as invoice_prints,
    COUNT(DISTINCT CASE WHEN pl.receipt_type = 'receipt' THEN pl.id END) as receipt_prints,
    MAX(CASE WHEN pl.receipt_type = 'kitchen' THEN pl.timestamp END) as last_kitchen_print,
    MAX(CASE WHEN pl.receipt_type = 'invoice' THEN pl.timestamp END) as last_invoice_print,
    MAX(CASE WHEN pl.receipt_type = 'receipt' THEN pl.timestamp END) as last_receipt_print
FROM orders o
LEFT JOIN print_log pl ON o.id = pl.order_id
GROUP BY o.id;
