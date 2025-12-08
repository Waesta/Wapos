-- Core Seed Data for WAPOS
-- Generated: 2025-11-27 16:30 EAT
-- Execute manually via phpMyAdmin or mysql CLI after restoring schema.

USE wapos;

START TRANSACTION;

-- 1. System Settings
INSERT INTO settings (setting_key, setting_value, description, updated_at)
VALUES
    ('company_name', 'Waesta International Hospitality', 'Primary brand name displayed across the platform', NOW()),
    ('company_phone', '+254700000000', 'Main support line', NOW()),
    ('company_email', 'support@waesta.com', 'Official support email', NOW()),
    ('default_currency', '', 'Default currency code', NOW()),
    ('timezone', 'Africa/Nairobi', 'Server/application timezone', NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description = VALUES(description),
    updated_at = NOW();

INSERT INTO locations (name, address, phone, email, is_active, created_at)
VALUES
    ('Flagship Hotel', 'International Way 1, Nairobi, Kenya', '+254-700-111-111', 'flagship@waesta.com', 1, NOW()),
    ('Downtown Bistro', 'CBD Plaza 5, Nairobi, Kenya', '+254-700-222-222', 'downtown@waesta.com', 1, NOW())
ON DUPLICATE KEY UPDATE
    address = VALUES(address),
    phone = VALUES(phone),
    email = VALUES(email),
    is_active = VALUES(is_active);

-- 3. Super Admin User
INSERT INTO users (username, password, full_name, email, phone, role, is_active, created_at)
VALUES
    ('superadmin', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Super Administrator', 'admin@waesta.com', '+254701111111', 'developer', 1, NOW())
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    phone = VALUES(phone),
    role = VALUES(role),
    is_active = VALUES(is_active);

-- 4. Permission Groups + Membership
INSERT INTO permission_groups (group_name, description, is_active, created_at)
VALUES ('Executive Suite', 'Full-stack operations + developer overrides', 1, NOW())
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    is_active = VALUES(is_active);

INSERT INTO user_group_memberships (user_id, group_id, assigned_by, assigned_at)
SELECT u.id, g.id, u.id, NOW()
FROM users u
JOIN permission_groups g ON g.group_name = 'Executive Suite'
WHERE u.username = 'superadmin'
ON DUPLICATE KEY UPDATE assigned_at = NOW();

-- 5. Product Categories & Products
INSERT INTO categories (name, description, is_active, created_at)
VALUES
    ('Beverages', 'Drinks and refreshments', 1, NOW()),
    ('Housekeeping Supplies', 'Room and facility supplies', 1, NOW())
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    is_active = VALUES(is_active);

INSERT INTO products (category_id, name, sku, cost_price, selling_price, stock_quantity, is_active, created_at)
SELECT c.id, 'Bottled Water 500ml', 'BW-500', 20.00, 60.00, 100, 1, NOW()
FROM categories c WHERE c.name = 'Beverages'
ON DUPLICATE KEY UPDATE
    cost_price = VALUES(cost_price),
    selling_price = VALUES(selling_price),
    stock_quantity = VALUES(stock_quantity),
    is_active = VALUES(is_active);

INSERT INTO products (category_id, name, sku, cost_price, selling_price, stock_quantity, is_active, created_at)
SELECT c.id, 'Room Linen Set', 'RL-SET', 1500.00, 3200.00, 20, 1, NOW()
FROM categories c WHERE c.name = 'Housekeeping Supplies'
ON DUPLICATE KEY UPDATE
    cost_price = VALUES(cost_price),
    selling_price = VALUES(selling_price),
    stock_quantity = VALUES(stock_quantity),
    is_active = VALUES(is_active);

COMMIT;
