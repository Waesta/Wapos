-- ============================================================
-- WAPOS Comprehensive Test Data
-- Generated: 2025-12-05
-- Purpose: Populate system with realistic test data for demos
-- ============================================================

USE wapos;

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ============================================================
-- 1. LOCATIONS (Multiple branches)
-- ============================================================
INSERT INTO locations (name, address, phone, email, is_active, created_at) VALUES
('Main Branch - Kampala', 'Plot 45, Kampala Road, Kampala, Uganda', '+256-700-100-001', 'kampala@waesta.com', 1, NOW()),
('Nairobi Branch', 'Kenyatta Avenue 12, Nairobi, Kenya', '+254-700-200-002', 'nairobi@waesta.com', 1, NOW()),
('Kigali Branch', 'KN 5 Road, Kigali, Rwanda', '+250-788-300-003', 'kigali@waesta.com', 1, NOW()),
('Mombasa Branch', 'Moi Avenue 8, Mombasa, Kenya', '+254-700-400-004', 'mombasa@waesta.com', 1, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), address = VALUES(address);

-- ============================================================
-- 2. USERS (Various roles)
-- ============================================================
-- Password for all test users: Thepurpose@2025 (hashed)
INSERT INTO users (username, password, full_name, email, phone, role, location_id, is_active, created_at) VALUES
-- Super Admin
('superadmin', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Super Administrator', 'admin@waesta.com', '+256-700-000-001', 'developer', 1, 1, NOW()),
-- Admins
('admin_kampala', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'John Mukasa', 'john@waesta.com', '+256-700-000-002', 'admin', 1, 1, NOW()),
('admin_nairobi', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Mary Wanjiku', 'mary@waesta.com', '+254-700-000-003', 'admin', 2, 1, NOW()),
-- Managers
('manager_kampala', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Peter Okello', 'peter@waesta.com', '+256-700-000-004', 'manager', 1, 1, NOW()),
('manager_nairobi', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Grace Muthoni', 'grace@waesta.com', '+254-700-000-005', 'manager', 2, 1, NOW()),
('manager_kigali', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Jean Baptiste', 'jean@waesta.com', '+250-788-000-006', 'manager', 3, 1, NOW()),
-- Cashiers
('cashier1', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Sarah Nambi', 'sarah@waesta.com', '+256-700-000-007', 'cashier', 1, 1, NOW()),
('cashier2', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'David Ochieng', 'david@waesta.com', '+254-700-000-008', 'cashier', 2, 1, NOW()),
('cashier3', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Alice Uwimana', 'alice@waesta.com', '+250-788-000-009', 'cashier', 3, 1, NOW()),
('cashier4', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'James Kamau', 'james@waesta.com', '+254-700-000-010', 'cashier', 4, 1, NOW()),
-- Waiters
('waiter1', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Moses Ssemakula', 'moses@waesta.com', '+256-700-000-011', 'waiter', 1, 1, NOW()),
('waiter2', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Faith Njeri', 'faith@waesta.com', '+254-700-000-012', 'waiter', 2, 1, NOW()),
-- Accountant
('accountant', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Robert Kato', 'robert@waesta.com', '+256-700-000-013', 'accountant', 1, 1, NOW()),
-- Riders
('rider1', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Emmanuel Mugisha', 'emmanuel@waesta.com', '+256-700-000-014', 'rider', 1, 1, NOW()),
('rider2', '$2y$10$eHysVRSbGvkHpPg4.PxdPOQ4T1QekzEshLz4DoxexnctyNnZo0zsG', 'Joseph Otieno', 'joseph@waesta.com', '+254-700-000-015', 'rider', 2, 1, NOW())
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role);

-- ============================================================
-- 3. CATEGORIES
-- ============================================================
INSERT INTO categories (name, description, is_active, created_at) VALUES
('Beverages', 'Soft drinks, juices, water, and hot drinks', 1, NOW()),
('Food - Main', 'Main course dishes', 1, NOW()),
('Food - Starters', 'Appetizers and starters', 1, NOW()),
('Food - Desserts', 'Sweet treats and desserts', 1, NOW()),
('Alcohol', 'Beers, wines, and spirits', 1, NOW()),
('Snacks', 'Quick bites and snacks', 1, NOW()),
('Retail Items', 'General retail products', 1, NOW()),
('Room Supplies', 'Hotel room amenities', 1, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============================================================
-- 4. PRODUCTS (50+ products)
-- ============================================================
INSERT INTO products (category_id, name, sku, barcode, cost_price, selling_price, stock_quantity, reorder_level, is_active, created_at)
SELECT c.id, p.name, p.sku, p.barcode, p.cost_price, p.selling_price, p.stock_quantity, p.reorder_level, 1, NOW()
FROM (
    -- Beverages
    SELECT 'Beverages' as cat, 'Coca Cola 500ml' as name, 'BEV-001' as sku, '5449000000996' as barcode, 800 as cost_price, 1500 as selling_price, 200 as stock_quantity, 50 as reorder_level
    UNION SELECT 'Beverages', 'Fanta Orange 500ml', 'BEV-002', '5449000000997', 800, 1500, 180, 50
    UNION SELECT 'Beverages', 'Sprite 500ml', 'BEV-003', '5449000000998', 800, 1500, 150, 50
    UNION SELECT 'Beverages', 'Bottled Water 500ml', 'BEV-004', '5449000000999', 300, 1000, 500, 100
    UNION SELECT 'Beverages', 'Bottled Water 1L', 'BEV-005', '5449000001000', 500, 1500, 300, 80
    UNION SELECT 'Beverages', 'Fresh Orange Juice', 'BEV-006', 'JUICE-001', 1500, 3500, 50, 20
    UNION SELECT 'Beverages', 'Mango Juice', 'BEV-007', 'JUICE-002', 1500, 3500, 50, 20
    UNION SELECT 'Beverages', 'Hot Coffee', 'BEV-008', 'HOT-001', 500, 2500, 999, 10
    UNION SELECT 'Beverages', 'Hot Tea', 'BEV-009', 'HOT-002', 300, 1500, 999, 10
    UNION SELECT 'Beverages', 'Cappuccino', 'BEV-010', 'HOT-003', 800, 4000, 999, 10
    -- Food - Main
    UNION SELECT 'Food - Main', 'Grilled Chicken', 'FOOD-001', 'MAIN-001', 8000, 18000, 50, 10
    UNION SELECT 'Food - Main', 'Beef Steak', 'FOOD-002', 'MAIN-002', 12000, 25000, 30, 10
    UNION SELECT 'Food - Main', 'Fish Fillet', 'FOOD-003', 'MAIN-003', 10000, 22000, 40, 10
    UNION SELECT 'Food - Main', 'Vegetable Curry', 'FOOD-004', 'MAIN-004', 5000, 12000, 60, 15
    UNION SELECT 'Food - Main', 'Chicken Biryani', 'FOOD-005', 'MAIN-005', 7000, 15000, 45, 10
    UNION SELECT 'Food - Main', 'Pilau Rice', 'FOOD-006', 'MAIN-006', 4000, 10000, 70, 20
    UNION SELECT 'Food - Main', 'Ugali & Sukuma', 'FOOD-007', 'MAIN-007', 2000, 5000, 100, 30
    UNION SELECT 'Food - Main', 'Chapati (2pcs)', 'FOOD-008', 'MAIN-008', 500, 1500, 200, 50
    -- Food - Starters
    UNION SELECT 'Food - Starters', 'Samosa (3pcs)', 'START-001', 'STRT-001', 1000, 3000, 80, 20
    UNION SELECT 'Food - Starters', 'Spring Rolls (4pcs)', 'START-002', 'STRT-002', 1200, 3500, 60, 15
    UNION SELECT 'Food - Starters', 'Soup of the Day', 'START-003', 'STRT-003', 1500, 4000, 50, 10
    UNION SELECT 'Food - Starters', 'Garden Salad', 'START-004', 'STRT-004', 1000, 3500, 40, 10
    -- Food - Desserts
    UNION SELECT 'Food - Desserts', 'Chocolate Cake', 'DESS-001', 'DSRT-001', 2000, 5000, 30, 10
    UNION SELECT 'Food - Desserts', 'Ice Cream (3 scoops)', 'DESS-002', 'DSRT-002', 1500, 4000, 50, 15
    UNION SELECT 'Food - Desserts', 'Fruit Salad', 'DESS-003', 'DSRT-003', 1000, 3000, 40, 10
    -- Alcohol
    UNION SELECT 'Alcohol', 'Tusker Lager 500ml', 'ALC-001', 'BEER-001', 2000, 4000, 200, 50
    UNION SELECT 'Alcohol', 'Nile Special 500ml', 'ALC-002', 'BEER-002', 2000, 4000, 180, 50
    UNION SELECT 'Alcohol', 'Bell Lager 500ml', 'ALC-003', 'BEER-003', 2000, 4000, 150, 50
    UNION SELECT 'Alcohol', 'Heineken 330ml', 'ALC-004', 'BEER-004', 3000, 6000, 100, 30
    UNION SELECT 'Alcohol', 'Red Wine (Glass)', 'ALC-005', 'WINE-001', 3000, 8000, 80, 20
    UNION SELECT 'Alcohol', 'White Wine (Glass)', 'ALC-006', 'WINE-002', 3000, 8000, 80, 20
    UNION SELECT 'Alcohol', 'Whisky (Shot)', 'ALC-007', 'SPRT-001', 2500, 7000, 100, 25
    -- Snacks
    UNION SELECT 'Snacks', 'Potato Chips', 'SNK-001', 'SNCK-001', 500, 1500, 150, 40
    UNION SELECT 'Snacks', 'Peanuts (100g)', 'SNK-002', 'SNCK-002', 400, 1200, 120, 30
    UNION SELECT 'Snacks', 'Popcorn', 'SNK-003', 'SNCK-003', 300, 1000, 100, 30
    UNION SELECT 'Snacks', 'Chocolate Bar', 'SNK-004', 'SNCK-004', 800, 2000, 80, 20
    -- Retail Items
    UNION SELECT 'Retail Items', 'Toothpaste', 'RET-001', 'RETL-001', 2000, 4500, 50, 15
    UNION SELECT 'Retail Items', 'Soap Bar', 'RET-002', 'RETL-002', 500, 1500, 100, 30
    UNION SELECT 'Retail Items', 'Shampoo', 'RET-003', 'RETL-003', 3000, 6000, 40, 10
    UNION SELECT 'Retail Items', 'Phone Charger', 'RET-004', 'RETL-004', 5000, 12000, 20, 5
    UNION SELECT 'Retail Items', 'Umbrella', 'RET-005', 'RETL-005', 8000, 15000, 15, 5
    -- Room Supplies
    UNION SELECT 'Room Supplies', 'Room Linen Set', 'ROOM-001', 'ROOM-001', 50000, 0, 30, 10
    UNION SELECT 'Room Supplies', 'Towel Set', 'ROOM-002', 'ROOM-002', 20000, 0, 50, 15
    UNION SELECT 'Room Supplies', 'Toiletries Kit', 'ROOM-003', 'ROOM-003', 5000, 0, 100, 30
    UNION SELECT 'Room Supplies', 'Mini Bar Refill', 'ROOM-004', 'ROOM-004', 15000, 0, 40, 10
) p
JOIN categories c ON c.name = p.cat
ON DUPLICATE KEY UPDATE 
    cost_price = VALUES(cost_price),
    selling_price = VALUES(selling_price),
    stock_quantity = VALUES(stock_quantity);

-- ============================================================
-- 5. CUSTOMERS (30+ customers with birthdays)
-- ============================================================
INSERT INTO customers (name, email, phone, address, date_of_birth, loyalty_points, is_active, created_at) VALUES
('James Mwangi', 'james.mwangi@email.com', '+254-722-111-001', 'Westlands, Nairobi', '1985-03-15', 1500, 1, NOW()),
('Sarah Nakato', 'sarah.nakato@email.com', '+256-772-111-002', 'Kololo, Kampala', '1990-07-22', 2300, 1, NOW()),
('Peter Omondi', 'peter.omondi@email.com', '+254-733-111-003', 'Kilimani, Nairobi', '1988-11-08', 800, 1, NOW()),
('Grace Achieng', 'grace.achieng@email.com', '+254-700-111-004', 'Karen, Nairobi', '1992-01-30', 3200, 1, NOW()),
('David Ssempijja', 'david.ssempijja@email.com', '+256-701-111-005', 'Ntinda, Kampala', '1987-05-12', 1200, 1, NOW()),
('Mary Wambui', 'mary.wambui@email.com', '+254-711-111-006', 'Lavington, Nairobi', '1995-09-25', 500, 1, NOW()),
('John Mugisha', 'john.mugisha@email.com', '+256-782-111-007', 'Muyenga, Kampala', '1983-12-03', 4500, 1, NOW()),
('Faith Njoki', 'faith.njoki@email.com', '+254-722-111-008', 'Kileleshwa, Nairobi', '1991-04-18', 1800, 1, NOW()),
('Emmanuel Habimana', 'emmanuel.h@email.com', '+250-788-111-009', 'Kigali Heights', '1989-08-07', 2100, 1, NOW()),
('Alice Uwase', 'alice.uwase@email.com', '+250-722-111-010', 'Kimihurura, Kigali', '1994-02-14', 900, 1, NOW()),
('Robert Kamau', 'robert.kamau@email.com', '+254-733-111-011', 'Runda, Nairobi', '1986-06-28', 5600, 1, NOW()),
('Christine Namukasa', 'christine.n@email.com', '+256-772-111-012', 'Bugolobi, Kampala', '1993-10-11', 1100, 1, NOW()),
('Michael Otieno', 'michael.otieno@email.com', '+254-700-111-013', 'South B, Nairobi', '1984-03-05', 2800, 1, NOW()),
('Rose Akello', 'rose.akello@email.com', '+256-701-111-014', 'Naguru, Kampala', '1996-07-19', 400, 1, NOW()),
('Joseph Kariuki', 'joseph.kariuki@email.com', '+254-711-111-015', 'Muthaiga, Nairobi', '1982-11-22', 7200, 1, NOW()),
('Diana Namutebi', 'diana.n@email.com', '+256-782-111-016', 'Bukoto, Kampala', '1990-01-08', 1600, 1, NOW()),
('Patrick Wekesa', 'patrick.w@email.com', '+254-722-111-017', 'Parklands, Nairobi', '1988-05-30', 2400, 1, NOW()),
('Esther Nyambura', 'esther.n@email.com', '+254-733-111-018', 'Spring Valley, Nairobi', '1992-09-14', 1900, 1, NOW()),
('Samuel Okoth', 'samuel.okoth@email.com', '+254-700-111-019', 'Hurlingham, Nairobi', '1985-12-27', 3100, 1, NOW()),
('Janet Nambi', 'janet.nambi@email.com', '+256-701-111-020', 'Kansanga, Kampala', '1994-04-03', 700, 1, NOW()),
-- Customers with upcoming birthdays (for testing birthday wishes)
('Birthday Test 1', 'birthday1@test.com', '+254-700-999-001', 'Test Address 1', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 100, 1, NOW()),
('Birthday Test 2', 'birthday2@test.com', '+256-700-999-002', 'Test Address 2', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 100, 1, NOW()),
('Birthday Today', 'birthday.today@test.com', '+254-700-999-003', 'Test Address 3', CURDATE(), 100, 1, NOW()),
-- High-value customers
('VIP Customer 1', 'vip1@email.com', '+254-722-888-001', 'Runda Estate, Nairobi', '1980-06-15', 15000, 1, NOW()),
('VIP Customer 2', 'vip2@email.com', '+256-772-888-002', 'Kololo Hill, Kampala', '1978-09-20', 18500, 1, NOW()),
-- New customers (for segmentation testing)
('New Customer 1', 'new1@email.com', '+254-700-777-001', 'Nairobi', '1995-03-10', 50, 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('New Customer 2', 'new2@email.com', '+256-700-777-002', 'Kampala', '1997-08-25', 30, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
-- Inactive customers (for re-engagement testing)
('Inactive Customer 1', 'inactive1@email.com', '+254-700-666-001', 'Nairobi', '1988-04-12', 500, 1, DATE_SUB(NOW(), INTERVAL 90 DAY)),
('Inactive Customer 2', 'inactive2@email.com', '+256-700-666-002', 'Kampala', '1990-11-30', 300, 1, DATE_SUB(NOW(), INTERVAL 120 DAY))
ON DUPLICATE KEY UPDATE name = VALUES(name), loyalty_points = VALUES(loyalty_points);

-- ============================================================
-- 6. REGISTERS
-- ============================================================
INSERT INTO registers (location_id, name, description, is_active, created_at) VALUES
(1, 'Register 1 - Main', 'Main counter register', 1, NOW()),
(1, 'Register 2 - Bar', 'Bar area register', 1, NOW()),
(1, 'Register 3 - Restaurant', 'Restaurant register', 1, NOW()),
(2, 'Register 1 - Nairobi', 'Nairobi main register', 1, NOW()),
(2, 'Register 2 - Nairobi', 'Nairobi secondary register', 1, NOW()),
(3, 'Register 1 - Kigali', 'Kigali main register', 1, NOW()),
(4, 'Register 1 - Mombasa', 'Mombasa main register', 1, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================
-- 7. SUPPLIERS
-- ============================================================
INSERT INTO suppliers (name, contact_person, email, phone, address, is_active, created_at) VALUES
('East African Beverages Ltd', 'John Kato', 'orders@eabeverages.com', '+256-414-123-456', 'Industrial Area, Kampala', 1, NOW()),
('Fresh Foods Kenya', 'Mary Njeri', 'supply@freshfoods.co.ke', '+254-20-234-567', 'Mombasa Road, Nairobi', 1, NOW()),
('Rwanda Imports Co', 'Jean Pierre', 'imports@rwandaco.rw', '+250-788-345-678', 'Kigali Free Zone', 1, NOW()),
('Global Retail Supplies', 'Peter Smith', 'orders@globalretail.com', '+254-722-456-789', 'Westlands, Nairobi', 1, NOW()),
('Hotel Amenities Uganda', 'Grace Auma', 'sales@hotelamug.com', '+256-772-567-890', 'Nakawa, Kampala', 1, NOW())
ON DUPLICATE KEY UPDATE contact_person = VALUES(contact_person);

-- ============================================================
-- 8. NOTIFICATION TEMPLATES (if table exists)
-- ============================================================
INSERT INTO email_templates (name, subject, body_html, template_type, variables, is_active, created_at) VALUES
('welcome', 'Welcome to {{business_name}}!', 
 '<h2>Welcome, {{customer_name}}!</h2><p>Thank you for joining us. We''re excited to have you as a customer.</p><p>Start shopping and earn loyalty points with every purchase!</p>', 
 'transactional', '["business_name", "customer_name"]', 1, NOW()),
('receipt', 'Your Receipt - #{{sale_number}}', 
 '<h2>Thank you for your purchase!</h2><p>Receipt #: <strong>{{sale_number}}</strong></p><p>Date: {{sale_date}}</p><p>Total: {{currency}}{{total_amount}}</p>', 
 'transactional', '["sale_number", "sale_date", "total_amount", "currency"]', 1, NOW()),
('thank_you', 'Thank you for shopping with us!', 
 '<h2>Thank You, {{customer_name}}!</h2><p>We appreciate your business and look forward to serving you again.</p>', 
 'appreciation', '["customer_name", "business_name"]', 1, NOW()),
('birthday', 'Happy Birthday, {{customer_name}}! 🎂', 
 '<h2>Happy Birthday!</h2><p>Wishing you a wonderful birthday filled with joy. Visit us today for a special treat!</p>', 
 'appreciation', '["customer_name", "business_name"]', 1, NOW()),
('promo_discount', '{{discount}}% OFF - Limited Time Offer!', 
 '<h2>Special Offer Just For You!</h2><p>Enjoy {{discount}}% off your next purchase. Use code: {{promo_code}}</p><p>Valid until {{expiry_date}}</p>', 
 'marketing', '["discount", "promo_code", "expiry_date"]', 1, NOW()),
('low_stock_alert', 'Low Stock Alert - Action Required', 
 '<h2>Low Stock Alert</h2><p>The following products need restocking:</p>{{product_list}}', 
 'alert', '["product_list"]', 1, NOW()),
('daily_summary', 'Daily Sales Summary - {{date}}', 
 '<h2>Daily Summary</h2><p>Total Sales: {{transaction_count}}</p><p>Revenue: {{currency}}{{total_revenue}}</p>', 
 'alert', '["date", "transaction_count", "total_revenue", "currency"]', 1, NOW())
ON DUPLICATE KEY UPDATE subject = VALUES(subject);

INSERT INTO sms_templates (name, message, template_type, variables, is_active, created_at) VALUES
('welcome', 'Welcome to {{business_name}}! Thank you for joining us. Start earning loyalty points today!', 'transactional', '["business_name"]', 1, NOW()),
('receipt', 'Thank you! Receipt #{{sale_number}} - Total: {{currency}}{{total}}. Visit again soon!', 'transactional', '["sale_number", "total", "currency"]', 1, NOW()),
('thank_you', 'Thank you for shopping at {{business_name}}! We appreciate your business.', 'appreciation', '["business_name"]', 1, NOW()),
('birthday', 'Happy Birthday from {{business_name}}! 🎂 Visit us today for a special birthday treat!', 'appreciation', '["business_name"]', 1, NOW()),
('promo', '{{business_name}}: {{message}} Valid until {{expiry}}. T&Cs apply.', 'marketing', '["business_name", "message", "expiry"]', 1, NOW()),
('order_ready', 'Your order #{{order_number}} is ready for pickup at {{business_name}}!', 'transactional', '["order_number", "business_name"]', 1, NOW()),
('delivery_update', 'Your order is on the way! Rider: {{rider_name}}, ETA: {{eta}} mins. Track: {{tracking_link}}', 'transactional', '["rider_name", "eta", "tracking_link"]', 1, NOW())
ON DUPLICATE KEY UPDATE message = VALUES(message);

-- ============================================================
-- 9. SAMPLE NOTIFICATION LOGS (for billing demo)
-- ============================================================
INSERT INTO notification_logs (customer_id, channel, recipient, notification_type, subject, message, status, created_at) 
SELECT 
    c.id,
    channel,
    c.phone,
    notif_type,
    subject,
    message,
    'sent',
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY)
FROM customers c
CROSS JOIN (
    SELECT 'sms' as channel, 'marketing' as notif_type, 'Promo' as subject, 'Special offer just for you!' as message
    UNION SELECT 'sms', 'transactional', 'Receipt', 'Thank you for your purchase!'
    UNION SELECT 'email', 'marketing', 'Newsletter', 'Check out our latest products'
    UNION SELECT 'email', 'transactional', 'Receipt', 'Your receipt is attached'
    UNION SELECT 'whatsapp', 'appreciation', 'Thank You', 'We appreciate your business'
) notifications
WHERE c.id <= 15
LIMIT 100;

-- Add some failed notifications for testing
INSERT INTO notification_logs (customer_id, channel, recipient, notification_type, subject, message, status, response, created_at) VALUES
(1, 'sms', '+254-722-111-001', 'marketing', 'Promo', 'Test message', 'failed', 'Invalid phone number format', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'email', 'invalid-email', 'transactional', 'Receipt', 'Test email', 'failed', 'Email delivery failed', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'whatsapp', '+254-733-111-003', 'appreciation', 'Thank You', 'Test whatsapp', 'failed', 'WhatsApp not configured', NOW());

-- ============================================================
-- 10. SETTINGS (Notification & Billing)
-- ============================================================
INSERT INTO settings (setting_key, setting_value) VALUES
-- Notification Settings
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('sms_provider', 'egosms'),
('default_country_code', '256'),
('whatsapp_provider', 'aisensy'),
-- Billing Settings
('billing_enabled', '1'),
('sms_cost_per_message', '50'),
('sms_provider_cost', '30'),
('whatsapp_cost_per_message', '100'),
('whatsapp_provider_cost', '50'),
('email_cost_per_message', '10'),
('email_provider_cost', '2'),
-- Business Settings
('company_name', 'Waesta International'),
('company_phone', '+256-700-000-000'),
('company_email', 'info@waesta.com'),
('timezone', 'Africa/Kampala')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SUMMARY
-- ============================================================
-- Locations: 4 (Kampala, Nairobi, Kigali, Mombasa)
-- Users: 15 (various roles)
-- Categories: 8
-- Products: 45+
-- Customers: 30+ (including birthday test, VIP, new, inactive)
-- Registers: 7
-- Suppliers: 5
-- Email Templates: 7
-- SMS Templates: 7
-- Sample Notification Logs: 100+
-- 
-- Test Login Credentials:
-- Username: superadmin | Password: Thepurpose@2025 | Role: developer
-- Username: admin_kampala | Password: Thepurpose@2025 | Role: admin
-- Username: manager_kampala | Password: Thepurpose@2025 | Role: manager
-- Username: cashier1 | Password: Thepurpose@2025 | Role: cashier
-- ============================================================
