-- Void Orders System Database Schema
-- Adds void functionality to the existing order system

-- Add 'voided' status to orders table
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'completed', 'cancelled', 'voided') DEFAULT 'pending';

-- Create void transactions audit table
CREATE TABLE IF NOT EXISTS void_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    original_total DECIMAL(15,2) NOT NULL,
    void_reason_code VARCHAR(50) NOT NULL,
    void_reason_text TEXT,
    voided_by_user_id INT UNSIGNED NOT NULL,
    manager_approval_user_id INT UNSIGNED,
    void_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    financial_impact JSON,
    inventory_adjustments JSON,
    receipt_printed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (voided_by_user_id) REFERENCES users(id),
    FOREIGN KEY (manager_approval_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_void_timestamp (void_timestamp),
    INDEX idx_voided_by (voided_by_user_id)
) ENGINE=InnoDB;

-- Create void reason codes table
CREATE TABLE IF NOT EXISTS void_reason_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    requires_manager_approval BOOLEAN DEFAULT FALSE,
    affects_inventory BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Create void settings table
CREATE TABLE IF NOT EXISTS void_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add void-related columns to orders table
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS void_reason_code VARCHAR(50),
ADD COLUMN IF NOT EXISTS void_reason_text TEXT,
ADD COLUMN IF NOT EXISTS voided_by_user_id INT UNSIGNED,
ADD COLUMN IF NOT EXISTS voided_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS manager_approval_required BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS manager_approved_by INT UNSIGNED,
ADD COLUMN IF NOT EXISTS manager_approved_at TIMESTAMP NULL,
ADD FOREIGN KEY IF NOT EXISTS fk_orders_voided_by (voided_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD FOREIGN KEY IF NOT EXISTS fk_orders_manager_approved (manager_approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Insert default void reason codes
INSERT INTO void_reason_codes (code, display_name, description, requires_manager_approval, affects_inventory, display_order) VALUES
('KITCHEN_ERROR', 'Kitchen Error', 'Food preparation error or quality issue', FALSE, TRUE, 1),
('CUSTOMER_CANCEL', 'Customer Cancellation', 'Customer requested to cancel order', FALSE, TRUE, 2),
('PAYMENT_ISSUE', 'Payment Issue', 'Payment failed or disputed', TRUE, TRUE, 3),
('ITEM_UNAVAILABLE', 'Item Unavailable', 'Ordered items not available', FALSE, TRUE, 4),
('WRONG_ORDER', 'Wrong Order Entered', 'Incorrect order entry by staff', FALSE, TRUE, 5),
('SYSTEM_ERROR', 'System Error', 'Technical system malfunction', TRUE, FALSE, 6),
('DUPLICATE_ORDER', 'Duplicate Order', 'Order was entered multiple times', FALSE, TRUE, 7),
('CUSTOMER_COMPLAINT', 'Customer Complaint', 'Customer dissatisfaction with order', TRUE, TRUE, 8),
('DELIVERY_FAILED', 'Delivery Failed', 'Unable to deliver order', FALSE, TRUE, 9),
('MANAGER_OVERRIDE', 'Manager Override', 'Management decision to void', TRUE, TRUE, 10)
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Insert default void settings
INSERT INTO void_settings (setting_key, setting_value, setting_type, description) VALUES
('void_time_limit_minutes', '60', 'number', 'Time limit in minutes after order creation to allow void without manager approval'),
('require_manager_approval_amount', '1000.00', 'number', 'Order amount above which manager approval is required for void'),
('auto_adjust_inventory', '1', 'boolean', 'Automatically adjust inventory when order is voided'),
('print_void_receipt', '1', 'boolean', 'Print void confirmation receipt'),
('allow_partial_void', '0', 'boolean', 'Allow voiding individual items from an order'),
('void_notification_email', '', 'string', 'Email address to notify of void transactions'),
('void_daily_limit', '10', 'number', 'Maximum number of voids allowed per user per day'),
('void_audit_retention_days', '365', 'number', 'Number of days to retain void transaction records')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Create view for void transaction summary
CREATE OR REPLACE VIEW void_transactions_summary AS
SELECT 
    vt.*,
    o.customer_name,
    o.customer_phone,
    o.order_type,
    o.table_id,
    u1.username as voided_by_username,
    u2.username as manager_username,
    vrc.display_name as reason_display_name,
    vrc.requires_manager_approval,
    rt.name as table_name
FROM void_transactions vt
JOIN orders o ON vt.order_id = o.id
JOIN users u1 ON vt.voided_by_user_id = u1.id
LEFT JOIN users u2 ON vt.manager_approval_user_id = u2.id
JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
LEFT JOIN restaurant_tables rt ON o.table_id = rt.id;

-- Create stored procedure for voiding orders
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS VoidOrder(
    IN p_order_id INT,
    IN p_reason_code VARCHAR(50),
    IN p_reason_text TEXT,
    IN p_voided_by_user_id INT,
    IN p_manager_user_id INT,
    OUT p_success BOOLEAN,
    OUT p_message TEXT
)
BEGIN
    DECLARE v_order_exists INT DEFAULT 0;
    DECLARE v_order_status VARCHAR(20);
    DECLARE v_order_total DECIMAL(15,2);
    DECLARE v_order_number VARCHAR(50);
    DECLARE v_requires_approval BOOLEAN DEFAULT FALSE;
    DECLARE v_void_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Database error occurred during void operation';
    END;
    
    START TRANSACTION;
    
    -- Check if order exists and get details
    SELECT COUNT(*), status, total_amount, order_number
    INTO v_order_exists, v_order_status, v_order_total, v_order_number
    FROM orders 
    WHERE id = p_order_id;
    
    IF v_order_exists = 0 THEN
        SET p_success = FALSE;
        SET p_message = 'Order not found';
        ROLLBACK;
    ELSEIF v_order_status = 'voided' THEN
        SET p_success = FALSE;
        SET p_message = 'Order is already voided';
        ROLLBACK;
    ELSEIF v_order_status = 'completed' THEN
        SET p_success = FALSE;
        SET p_message = 'Cannot void completed order';
        ROLLBACK;
    ELSE
        -- Check if manager approval is required
        SELECT requires_manager_approval INTO v_requires_approval
        FROM void_reason_codes 
        WHERE code = p_reason_code;
        
        -- Insert void transaction record
        INSERT INTO void_transactions (
            order_id, order_number, original_total, void_reason_code, 
            void_reason_text, voided_by_user_id, manager_approval_user_id
        ) VALUES (
            p_order_id, v_order_number, v_order_total, p_reason_code,
            p_reason_text, p_voided_by_user_id, p_manager_user_id
        );
        
        SET v_void_id = LAST_INSERT_ID();
        
        -- Update order status
        UPDATE orders SET 
            status = 'voided',
            void_reason_code = p_reason_code,
            void_reason_text = p_reason_text,
            voided_by_user_id = p_voided_by_user_id,
            voided_at = NOW(),
            manager_approval_required = v_requires_approval,
            manager_approved_by = p_manager_user_id,
            manager_approved_at = CASE WHEN p_manager_user_id IS NOT NULL THEN NOW() ELSE NULL END
        WHERE id = p_order_id;
        
        -- Adjust inventory if needed
        CALL AdjustInventoryForVoidOrder(p_order_id);
        
        SET p_success = TRUE;
        SET p_message = CONCAT('Order ', v_order_number, ' voided successfully');
        
        COMMIT;
    END IF;
END //

CREATE PROCEDURE IF NOT EXISTS AdjustInventoryForVoidOrder(
    IN p_order_id INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT product_id, quantity 
        FROM order_items 
        WHERE order_id = p_order_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Check if auto inventory adjustment is enabled
    IF (SELECT setting_value FROM void_settings WHERE setting_key = 'auto_adjust_inventory') = '1' THEN
        OPEN item_cursor;
        
        read_loop: LOOP
            FETCH item_cursor INTO v_product_id, v_quantity;
            IF done THEN
                LEAVE read_loop;
            END IF;
            
            -- Return items to inventory
            UPDATE products 
            SET stock_quantity = stock_quantity + v_quantity
            WHERE id = v_product_id;
            
            -- Log inventory adjustment
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, reference_type, 
                reference_id, notes, user_id, created_at
            ) VALUES (
                v_product_id, 'in', v_quantity, 'void_order',
                p_order_id, CONCAT('Inventory returned from voided order #', p_order_id),
                1, NOW()
            );
        END LOOP;
        
        CLOSE item_cursor;
    END IF;
END //

DELIMITER ;

-- Create trigger to log void actions
CREATE TRIGGER IF NOT EXISTS order_void_audit_trigger
    AFTER UPDATE ON orders
    FOR EACH ROW
BEGIN
    IF OLD.status != 'voided' AND NEW.status = 'voided' THEN
        INSERT INTO audit_log (
            table_name, record_id, action, old_values, new_values,
            user_id, ip_address, created_at
        ) VALUES (
            'orders', NEW.id, 'void',
            JSON_OBJECT('status', OLD.status, 'total_amount', OLD.total_amount),
            JSON_OBJECT('status', NEW.status, 'void_reason', NEW.void_reason_code),
            NEW.voided_by_user_id, '', NOW()
        );
    END IF;
END;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_orders_voided_at ON orders(voided_at);
CREATE INDEX IF NOT EXISTS idx_orders_void_reason ON orders(void_reason_code);
CREATE INDEX IF NOT EXISTS idx_void_transactions_timestamp ON void_transactions(void_timestamp);

-- Performance optimization
ANALYZE TABLE void_transactions, void_reason_codes, void_settings;
