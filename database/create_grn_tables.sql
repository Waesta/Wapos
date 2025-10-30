-- Goods Received Notes (GRN) Tables
-- Complete procurement and inventory flow

CREATE TABLE IF NOT EXISTS goods_received_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(50) UNIQUE NOT NULL,
    purchase_order_id INT UNSIGNED,
    supplier_id INT UNSIGNED,
    received_date DATE NOT NULL,
    invoice_number VARCHAR(100),
    total_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    status ENUM('draft', 'completed', 'cancelled') DEFAULT 'draft',
    received_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_grn_number (grn_number),
    INDEX idx_po (purchase_order_id),
    INDEX idx_status (status),
    INDEX idx_received_date (received_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grn_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    ordered_quantity DECIMAL(10,2) DEFAULT 0,
    received_quantity DECIMAL(10,2) NOT NULL,
    unit_cost DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    expiry_date DATE,
    batch_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grn_id) REFERENCES goods_received_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_grn (grn_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;
