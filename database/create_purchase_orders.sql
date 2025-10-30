-- Purchase Orders and Items Tables
-- Run this to add purchase order functionality

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT UNSIGNED,
    status ENUM('pending', 'approved', 'ordered', 'received', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_po_number (po_number),
    INDEX idx_status (status),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_cost DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    received_quantity DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_po (purchase_order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;
