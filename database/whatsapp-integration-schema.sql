-- WhatsApp Integration Database Schema
-- This creates the necessary tables for WhatsApp order management and tracking

-- WhatsApp messages log
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    customer_phone VARCHAR(20) NOT NULL,
    message_type ENUM('inbound', 'outbound') NOT NULL,
    content_type ENUM('text', 'image', 'document', 'audio', 'video', 'location') DEFAULT 'text',
    message_text TEXT,
    media_url VARCHAR(500),
    media_id VARCHAR(255),
    status ENUM('received', 'sent', 'delivered', 'read', 'failed') DEFAULT 'received',
    api_response TEXT,
    timestamp TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_message_type (message_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- WhatsApp order parsing results
CREATE TABLE IF NOT EXISTS whatsapp_order_parsing (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255),
    customer_phone VARCHAR(20) NOT NULL,
    raw_message TEXT NOT NULL,
    parsed_items JSON,
    confidence_score FLOAT DEFAULT 0,
    parsing_status ENUM('pending', 'success', 'failed', 'manual_review') DEFAULT 'pending',
    order_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_parsing_status (parsing_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- WhatsApp customer preferences
CREATE TABLE IF NOT EXISTS whatsapp_customer_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_phone VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100),
    preferred_language ENUM('en', 'sw', 'fr') DEFAULT 'en',
    notification_preferences JSON,
    order_history_count INT DEFAULT 0,
    last_order_date TIMESTAMP NULL,
    is_blocked BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_last_order (last_order_date)
) ENGINE=InnoDB;

-- WhatsApp automated responses
CREATE TABLE IF NOT EXISTS whatsapp_auto_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trigger_keyword VARCHAR(100) NOT NULL,
    trigger_type ENUM('exact', 'contains', 'starts_with', 'regex') DEFAULT 'contains',
    response_text TEXT NOT NULL,
    response_type ENUM('text', 'menu', 'contact', 'location') DEFAULT 'text',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_trigger_keyword (trigger_keyword),
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB;

-- WhatsApp conversation sessions
CREATE TABLE IF NOT EXISTS whatsapp_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_phone VARCHAR(20) NOT NULL,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    message_count INT DEFAULT 0,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    conversation_type ENUM('order', 'support', 'inquiry', 'complaint') DEFAULT 'inquiry',
    status ENUM('active', 'closed', 'transferred') DEFAULT 'active',
    assigned_user_id INT UNSIGNED NULL,
    notes TEXT,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_status (status),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- WhatsApp delivery notifications log
CREATE TABLE IF NOT EXISTS whatsapp_delivery_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    notification_type ENUM('assigned', 'picked_up', 'in_transit', 'nearby', 'delivered', 'eta_update') NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    message_text TEXT NOT NULL,
    sent_at TIMESTAMP NULL,
    delivery_status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    api_response TEXT,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_delivery_status (delivery_status)
) ENGINE=InnoDB;

-- WhatsApp menu catalog
CREATE TABLE IF NOT EXISTS whatsapp_menu_catalog (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    whatsapp_product_id VARCHAR(255),
    display_name VARCHAR(200) NOT NULL,
    description TEXT,
    image_url VARCHAR(500),
    price DECIMAL(10, 2) NOT NULL,
    category_name VARCHAR(100),
    is_available BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_is_available (is_available),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB;

-- WhatsApp order items (for parsed orders)
CREATE TABLE IF NOT EXISTS whatsapp_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parsing_id INT UNSIGNED NOT NULL,
    item_text VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(200),
    unit_price DECIMAL(10, 2) DEFAULT 0,
    total_price DECIMAL(10, 2) DEFAULT 0,
    match_confidence FLOAT DEFAULT 0,
    is_confirmed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    FOREIGN KEY (parsing_id) REFERENCES whatsapp_order_parsing(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_parsing_id (parsing_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB;

-- WhatsApp business settings
CREATE TABLE IF NOT EXISTS whatsapp_business_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB;

-- Add WhatsApp-related columns to existing tables
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS whatsapp_message_id VARCHAR(255),
ADD COLUMN IF NOT EXISTS whatsapp_conversation_id INT UNSIGNED,
ADD COLUMN IF NOT EXISTS order_source ENUM('pos', 'online', 'whatsapp', 'phone', 'walk_in') DEFAULT 'pos',
ADD INDEX IF NOT EXISTS idx_order_source (order_source),
ADD INDEX IF NOT EXISTS idx_whatsapp_message (whatsapp_message_id);

ALTER TABLE deliveries
ADD COLUMN IF NOT EXISTS whatsapp_notifications_sent JSON,
ADD COLUMN IF NOT EXISTS last_whatsapp_update TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS customer_whatsapp_phone VARCHAR(20);

-- Insert default WhatsApp auto-responses
INSERT INTO whatsapp_auto_responses (trigger_keyword, trigger_type, response_text, response_type, priority) VALUES
('menu', 'exact', 'Here is our menu! 🍽️', 'menu', 1),
('hi', 'exact', 'Hello! 👋 Welcome to our restaurant! Type *menu* to see our offerings or *help* for assistance.', 'text', 2),
('hello', 'exact', 'Hello! 👋 Welcome to our restaurant! Type *menu* to see our offerings or *help* for assistance.', 'text', 2),
('help', 'exact', 'I can help you with:\n🍽️ *menu* - View our food catalog\n📋 *order* - Place an order\n📍 *status* - Check order status\n🚚 *track* - Track delivery\n📞 *contact* - Get support', 'text', 1),
('contact', 'exact', 'Contact us for support! 📞', 'contact', 1),
('location', 'exact', 'Here is our location! 📍', 'location', 1),
('status', 'contains', 'Let me check your order status...', 'text', 1),
('track', 'contains', 'Let me track your delivery...', 'text', 1),
('order:', 'starts_with', 'Thank you for your order! Let me process this for you...', 'text', 1)
ON DUPLICATE KEY UPDATE response_text = VALUES(response_text);

-- Insert default WhatsApp business settings
INSERT INTO whatsapp_business_settings (setting_key, setting_value, setting_type, description, is_required) VALUES
('auto_reply_enabled', '1', 'boolean', 'Enable automatic replies to WhatsApp messages', false),
('business_hours_start', '08:00', 'string', 'Business hours start time', false),
('business_hours_end', '22:00', 'string', 'Business hours end time', false),
('after_hours_message', 'Thank you for contacting us! We are currently closed. Our business hours are 8:00 AM - 10:00 PM. We will respond when we open.', 'string', 'Message sent outside business hours', false),
('welcome_message', 'Welcome to our restaurant! 🍽️ How can we help you today?', 'string', 'Welcome message for new customers', false),
('order_confirmation_template', 'Your order has been confirmed! Order #{order_number} - Total: KES {total_amount}', 'string', 'Order confirmation message template', false),
('delivery_update_template', 'Delivery update for order #{order_number}: {status}', 'string', 'Delivery update message template', false)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Create views for common WhatsApp queries
CREATE OR REPLACE VIEW whatsapp_active_conversations AS
SELECT 
    c.*,
    COUNT(m.id) as message_count_today,
    MAX(m.created_at) as last_message_time,
    u.username as assigned_user_name
FROM whatsapp_conversations c
LEFT JOIN whatsapp_messages m ON c.customer_phone = m.customer_phone 
    AND DATE(m.created_at) = CURDATE()
LEFT JOIN users u ON c.assigned_user_id = u.id
WHERE c.status = 'active'
GROUP BY c.id;

CREATE OR REPLACE VIEW whatsapp_order_summary AS
SELECT 
    DATE(o.created_at) as order_date,
    COUNT(o.id) as total_orders,
    SUM(o.total_amount) as total_revenue,
    AVG(o.total_amount) as avg_order_value,
    COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders
FROM orders o
WHERE o.order_source = 'whatsapp'
GROUP BY DATE(o.created_at)
ORDER BY order_date DESC;

-- Create stored procedures for WhatsApp operations
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS ProcessWhatsAppOrder(
    IN p_customer_phone VARCHAR(20),
    IN p_message_text TEXT,
    IN p_message_id VARCHAR(255)
)
BEGIN
    DECLARE v_parsing_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insert parsing record
    INSERT INTO whatsapp_order_parsing (
        message_id, customer_phone, raw_message, parsing_status
    ) VALUES (
        p_message_id, p_customer_phone, p_message_text, 'pending'
    );
    
    SET v_parsing_id = LAST_INSERT_ID();
    
    -- Here you would add logic to parse the order items
    -- For now, we'll mark it as needing manual review
    UPDATE whatsapp_order_parsing 
    SET parsing_status = 'manual_review', processed_at = NOW()
    WHERE id = v_parsing_id;
    
    COMMIT;
    
    SELECT v_parsing_id as parsing_id;
END //

CREATE PROCEDURE IF NOT EXISTS SendWhatsAppDeliveryUpdate(
    IN p_delivery_id INT,
    IN p_notification_type VARCHAR(50)
)
BEGIN
    DECLARE v_customer_phone VARCHAR(20);
    DECLARE v_order_number VARCHAR(50);
    
    -- Get delivery details
    SELECT o.customer_phone, o.order_number
    INTO v_customer_phone, v_order_number
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    WHERE d.id = p_delivery_id;
    
    -- Insert notification record
    INSERT INTO whatsapp_delivery_notifications (
        delivery_id, notification_type, customer_phone, 
        message_text, delivery_status
    ) VALUES (
        p_delivery_id, p_notification_type, v_customer_phone,
        CONCAT('Delivery update for order #', v_order_number, ': ', p_notification_type),
        'pending'
    );
    
    -- Update delivery notifications sent
    UPDATE deliveries 
    SET whatsapp_notifications_sent = JSON_ARRAY_APPEND(
        COALESCE(whatsapp_notifications_sent, JSON_ARRAY()), 
        '$', 
        JSON_OBJECT('type', p_notification_type, 'sent_at', NOW())
    ),
    last_whatsapp_update = NOW()
    WHERE id = p_delivery_id;
    
END //

DELIMITER ;

-- Create triggers for automatic WhatsApp notifications
CREATE TRIGGER IF NOT EXISTS order_status_whatsapp_trigger
    AFTER UPDATE ON orders
    FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status AND NEW.order_source = 'whatsapp' THEN
        INSERT INTO whatsapp_delivery_notifications (
            delivery_id, notification_type, customer_phone, 
            message_text, delivery_status
        )
        SELECT 
            d.id, 'status_update', NEW.customer_phone,
            CONCAT('Order status updated to: ', NEW.status),
            'pending'
        FROM deliveries d 
        WHERE d.order_id = NEW.id;
    END IF;
END;

CREATE TRIGGER IF NOT EXISTS delivery_status_whatsapp_trigger
    AFTER UPDATE ON deliveries
    FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO whatsapp_delivery_notifications (
            delivery_id, notification_type, customer_phone, 
            message_text, delivery_status
        )
        SELECT 
            NEW.id, NEW.status, o.customer_phone,
            CONCAT('Delivery status: ', NEW.status),
            'pending'
        FROM orders o 
        WHERE o.id = NEW.order_id AND o.order_source = 'whatsapp';
    END IF;
END;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_phone_date ON whatsapp_messages(customer_phone, DATE(created_at));
CREATE INDEX IF NOT EXISTS idx_whatsapp_conversations_phone_status ON whatsapp_conversations(customer_phone, status);
CREATE INDEX IF NOT EXISTS idx_whatsapp_notifications_delivery_type ON whatsapp_delivery_notifications(delivery_id, notification_type);

-- Performance optimization
ANALYZE TABLE whatsapp_messages, whatsapp_order_parsing, whatsapp_conversations;
