-- =====================================================
-- Migration 004: Email, SMS & Notification System
-- Supports marketing, communication, and customer appreciation
-- =====================================================

-- Notification logs table
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('email', 'sms', 'whatsapp') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    notification_type ENUM('marketing', 'transactional', 'appreciation', 'reminder', 'alert') DEFAULT 'transactional',
    customer_id INT UNSIGNED NULL,
    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    response TEXT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_channel (channel),
    INDEX idx_status (status),
    INDEX idx_type (notification_type),
    INDEX idx_customer (customer_id),
    INDEX idx_created (created_at),
    
    CONSTRAINT fk_notification_customer 
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT NULL,
    template_type ENUM('marketing', 'transactional', 'appreciation', 'reminder', 'alert') DEFAULT 'transactional',
    variables JSON NULL COMMENT 'Available template variables',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type (template_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS templates table
CREATE TABLE IF NOT EXISTS sms_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    message VARCHAR(160) NOT NULL COMMENT 'SMS limited to 160 chars',
    template_type ENUM('marketing', 'transactional', 'appreciation', 'reminder', 'alert') DEFAULT 'transactional',
    variables JSON NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type (template_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marketing campaigns table
CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    channel ENUM('email', 'sms', 'whatsapp', 'all') DEFAULT 'email',
    campaign_type ENUM('promotional', 'newsletter', 'announcement', 'seasonal', 'loyalty') DEFAULT 'promotional',
    subject VARCHAR(255) NULL,
    content TEXT NOT NULL,
    target_segment ENUM('all', 'active', 'inactive', 'high_value', 'new', 'birthday', 'custom') DEFAULT 'all',
    target_criteria JSON NULL COMMENT 'Custom targeting criteria',
    scheduled_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    status ENUM('draft', 'scheduled', 'running', 'completed', 'cancelled') DEFAULT 'draft',
    total_recipients INT UNSIGNED DEFAULT 0,
    sent_count INT UNSIGNED DEFAULT 0,
    failed_count INT UNSIGNED DEFAULT 0,
    open_count INT UNSIGNED DEFAULT 0,
    click_count INT UNSIGNED DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_channel (channel),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_type (campaign_type),
    
    CONSTRAINT fk_campaign_creator 
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign recipients tracking
CREATE TABLE IF NOT EXISTS campaign_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    recipient VARCHAR(255) NOT NULL,
    channel ENUM('email', 'sms', 'whatsapp') NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'opened', 'clicked', 'unsubscribed') DEFAULT 'pending',
    sent_at DATETIME NULL,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    error_message VARCHAR(255) NULL,
    
    INDEX idx_campaign (campaign_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    
    CONSTRAINT fk_recipient_campaign 
        FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_recipient_customer 
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer communication preferences
CREATE TABLE IF NOT EXISTS customer_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL UNIQUE,
    email_marketing TINYINT(1) DEFAULT 1,
    sms_marketing TINYINT(1) DEFAULT 1,
    whatsapp_marketing TINYINT(1) DEFAULT 1,
    email_transactional TINYINT(1) DEFAULT 1,
    sms_transactional TINYINT(1) DEFAULT 1,
    whatsapp_transactional TINYINT(1) DEFAULT 1,
    birthday_wishes TINYINT(1) DEFAULT 1,
    promotional_offers TINYINT(1) DEFAULT 1,
    newsletter TINYINT(1) DEFAULT 1,
    preferred_channel ENUM('email', 'sms', 'whatsapp') DEFAULT 'email',
    unsubscribed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_preferences_customer 
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add date_of_birth to customers if not exists
-- Run this separately if needed:
-- ALTER TABLE customers ADD COLUMN date_of_birth DATE NULL AFTER phone;

-- Insert default email templates
INSERT INTO email_templates (name, subject, body_html, template_type, variables) VALUES
('welcome', 'Welcome to {{business_name}}!', 
 '<h2>Welcome, {{customer_name}}!</h2><p>Thank you for joining us. We''re excited to have you as a customer.</p><p>Start shopping and earn loyalty points with every purchase!</p>', 
 'transactional', '["business_name", "customer_name"]'),

('receipt', 'Your Receipt - #{{sale_number}}', 
 '<h2>Thank you for your purchase!</h2><p>Receipt #: <strong>{{sale_number}}</strong></p><p>Date: {{sale_date}}</p><p>Total: {{currency}}{{total_amount}}</p>', 
 'transactional', '["sale_number", "sale_date", "total_amount", "currency"]'),

('thank_you', 'Thank you for shopping with us!', 
 '<h2>Thank You, {{customer_name}}!</h2><p>We appreciate your business and look forward to serving you again.</p>', 
 'appreciation', '["customer_name", "business_name"]'),

('birthday', 'Happy Birthday, {{customer_name}}! 🎂', 
 '<h2>Happy Birthday!</h2><p>Wishing you a wonderful birthday filled with joy. Visit us today for a special treat!</p>', 
 'appreciation', '["customer_name", "business_name"]'),

('low_stock_alert', 'Low Stock Alert - Action Required', 
 '<h2>Low Stock Alert</h2><p>The following products need restocking:</p>{{product_list}}', 
 'alert', '["product_list"]'),

('daily_summary', 'Daily Sales Summary - {{date}}', 
 '<h2>Daily Summary</h2><p>Total Sales: {{transaction_count}}</p><p>Revenue: {{currency}}{{total_revenue}}</p>', 
 'alert', '["date", "transaction_count", "total_revenue", "currency"]')

ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Insert default SMS templates
INSERT INTO sms_templates (name, message, template_type, variables) VALUES
('welcome', 'Welcome to {{business_name}}! Thank you for joining us. Start earning loyalty points today!', 'transactional', '["business_name"]'),
('receipt', 'Thank you! Receipt #{{sale_number}} - Total: {{currency}}{{total}}. Visit again soon!', 'transactional', '["sale_number", "total", "currency"]'),
('thank_you', 'Thank you for shopping at {{business_name}}! We appreciate your business.', 'appreciation', '["business_name"]'),
('birthday', 'Happy Birthday from {{business_name}}! 🎂 Visit us today for a special birthday treat!', 'appreciation', '["business_name"]'),
('promo', '{{business_name}}: {{message}} Valid until {{expiry}}. T&Cs apply.', 'marketing', '["business_name", "message", "expiry"]')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add notification settings to settings table
INSERT INTO settings (setting_key, setting_value) VALUES
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', ''),
('smtp_encryption', 'tls'),
('sms_provider', 'africastalking'),
('sms_api_key', ''),
('sms_api_secret', ''),
('sms_sender_id', ''),
('default_country_code', '254'),
('notification_admin_email', ''),
('auto_send_receipts', '0'),
('auto_birthday_wishes', '1'),
('auto_daily_summary', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
