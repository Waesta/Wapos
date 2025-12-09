-- WAPOS Demo Data Seeder
-- Run this script to populate the database with realistic sample data for demos
-- Usage: mysql -u root wapos < database/seeders/demo-data-seeder.sql

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- CATEGORIES (Food & Beverage + Retail)
-- =====================================================
INSERT IGNORE INTO categories (id, name, description, parent_id, is_active) VALUES
(1, 'Beverages', 'Hot and cold drinks', NULL, 1),
(2, 'Food', 'Main dishes and snacks', NULL, 1),
(3, 'Desserts', 'Sweet treats and pastries', NULL, 1),
(4, 'Alcohol', 'Wines, spirits, and beers', NULL, 1),
(5, 'Retail', 'Shop items and merchandise', NULL, 1),
(6, 'Hot Drinks', 'Coffee, tea, and hot chocolate', 1, 1),
(7, 'Cold Drinks', 'Juices, sodas, and smoothies', 1, 1),
(8, 'Main Course', 'Full meals and entrees', 2, 1),
(9, 'Appetizers', 'Starters and small plates', 2, 1),
(10, 'Breakfast', 'Morning meals', 2, 1),
(11, 'Wines', 'Red, white, and rosé wines', 4, 1),
(12, 'Spirits', 'Whiskey, vodka, gin, etc.', 4, 1),
(13, 'Beers', 'Local and imported beers', 4, 1);

-- =====================================================
-- PRODUCTS (Comprehensive menu)
-- =====================================================
INSERT IGNORE INTO products (id, name, sku, barcode, category_id, price, cost_price, stock_quantity, min_stock_level, unit, description, is_active) VALUES
-- Hot Drinks
(1, 'Espresso', 'BEV-ESP-001', '1000000000001', 6, 3.50, 0.80, 999, 10, 'cup', 'Single shot espresso', 1),
(2, 'Cappuccino', 'BEV-CAP-001', '1000000000002', 6, 4.50, 1.20, 999, 10, 'cup', 'Espresso with steamed milk foam', 1),
(3, 'Latte', 'BEV-LAT-001', '1000000000003', 6, 4.50, 1.20, 999, 10, 'cup', 'Espresso with steamed milk', 1),
(4, 'Americano', 'BEV-AME-001', '1000000000004', 6, 3.80, 0.90, 999, 10, 'cup', 'Espresso with hot water', 1),
(5, 'Hot Chocolate', 'BEV-HOT-001', '1000000000005', 6, 4.00, 1.00, 999, 10, 'cup', 'Rich chocolate drink', 1),
(6, 'English Tea', 'BEV-TEA-001', '1000000000006', 6, 3.00, 0.50, 999, 10, 'cup', 'Classic black tea', 1),
(7, 'Green Tea', 'BEV-GRN-001', '1000000000007', 6, 3.50, 0.60, 999, 10, 'cup', 'Japanese green tea', 1),

-- Cold Drinks
(8, 'Fresh Orange Juice', 'BEV-ORA-001', '1000000000008', 7, 5.00, 2.00, 100, 20, 'glass', 'Freshly squeezed oranges', 1),
(9, 'Mango Smoothie', 'BEV-MAN-001', '1000000000009', 7, 6.50, 2.50, 100, 20, 'glass', 'Blended mango with yogurt', 1),
(10, 'Iced Coffee', 'BEV-ICE-001', '1000000000010', 7, 5.00, 1.50, 999, 10, 'glass', 'Cold brew coffee over ice', 1),
(11, 'Coca Cola', 'BEV-COK-001', '1000000000011', 7, 2.50, 1.00, 200, 50, 'bottle', '330ml bottle', 1),
(12, 'Mineral Water', 'BEV-WAT-001', '1000000000012', 7, 2.00, 0.50, 300, 100, 'bottle', '500ml still water', 1),
(13, 'Sparkling Water', 'BEV-SPA-001', '1000000000013', 7, 2.50, 0.70, 200, 50, 'bottle', '500ml sparkling', 1),

-- Main Course
(14, 'Grilled Chicken', 'FOO-GRC-001', '2000000000001', 8, 18.50, 7.00, 50, 10, 'plate', 'Grilled chicken breast with vegetables', 1),
(15, 'Beef Steak', 'FOO-STK-001', '2000000000002', 8, 28.00, 12.00, 30, 5, 'plate', '250g ribeye steak with sides', 1),
(16, 'Fish & Chips', 'FOO-FSH-001', '2000000000003', 8, 16.00, 6.00, 40, 10, 'plate', 'Battered fish with fries', 1),
(17, 'Pasta Carbonara', 'FOO-PAS-001', '2000000000004', 8, 14.50, 4.50, 50, 10, 'plate', 'Creamy bacon pasta', 1),
(18, 'Vegetable Curry', 'FOO-CUR-001', '2000000000005', 8, 13.00, 4.00, 40, 10, 'plate', 'Mixed vegetable curry with rice', 1),
(19, 'Burger Deluxe', 'FOO-BUR-001', '2000000000006', 8, 15.00, 5.50, 50, 10, 'plate', 'Beef burger with cheese and fries', 1),
(20, 'Pizza Margherita', 'FOO-PIZ-001', '2000000000007', 8, 14.00, 4.00, 40, 10, 'plate', '12 inch classic pizza', 1),
(21, 'Club Sandwich', 'FOO-CLB-001', '2000000000008', 8, 12.00, 4.00, 50, 10, 'plate', 'Triple-decker sandwich with fries', 1),

-- Appetizers
(22, 'Caesar Salad', 'FOO-SAL-001', '2000000000009', 9, 9.50, 3.00, 50, 10, 'bowl', 'Romaine lettuce with caesar dressing', 1),
(23, 'Soup of the Day', 'FOO-SOU-001', '2000000000010', 9, 7.00, 2.00, 30, 5, 'bowl', 'Chef''s daily soup selection', 1),
(24, 'Chicken Wings', 'FOO-WNG-001', '2000000000011', 9, 11.00, 4.00, 40, 10, 'plate', '8 pieces with dipping sauce', 1),
(25, 'Spring Rolls', 'FOO-SPR-001', '2000000000012', 9, 8.00, 2.50, 50, 10, 'plate', '4 vegetable spring rolls', 1),
(26, 'Garlic Bread', 'FOO-GAR-001', '2000000000013', 9, 5.50, 1.50, 60, 15, 'plate', 'Toasted bread with garlic butter', 1),

-- Breakfast
(27, 'Full English Breakfast', 'FOO-ENG-001', '2000000000014', 10, 14.00, 5.00, 30, 5, 'plate', 'Eggs, bacon, sausage, beans, toast', 1),
(28, 'Pancakes', 'FOO-PAN-001', '2000000000015', 10, 10.00, 3.00, 40, 10, 'plate', 'Stack of 3 with maple syrup', 1),
(29, 'Eggs Benedict', 'FOO-EGG-001', '2000000000016', 10, 12.00, 4.00, 30, 5, 'plate', 'Poached eggs on muffin with hollandaise', 1),
(30, 'Avocado Toast', 'FOO-AVO-001', '2000000000017', 10, 9.00, 3.00, 40, 10, 'plate', 'Smashed avocado on sourdough', 1),
(31, 'Omelette', 'FOO-OML-001', '2000000000018', 10, 10.00, 3.00, 50, 10, 'plate', '3-egg omelette with fillings', 1),

-- Desserts
(32, 'Chocolate Cake', 'DES-CHO-001', '3000000000001', 3, 7.50, 2.50, 20, 5, 'slice', 'Rich chocolate layer cake', 1),
(33, 'Cheesecake', 'DES-CHE-001', '3000000000002', 3, 8.00, 3.00, 20, 5, 'slice', 'New York style cheesecake', 1),
(34, 'Ice Cream', 'DES-ICE-001', '3000000000003', 3, 5.00, 1.50, 50, 10, 'scoop', '2 scoops, choice of flavor', 1),
(35, 'Tiramisu', 'DES-TIR-001', '3000000000004', 3, 8.50, 3.00, 15, 5, 'portion', 'Classic Italian dessert', 1),
(36, 'Apple Pie', 'DES-APP-001', '3000000000005', 3, 7.00, 2.50, 20, 5, 'slice', 'Warm apple pie with cream', 1),

-- Wines
(37, 'House Red Wine', 'ALC-RED-001', '4000000000001', 11, 6.00, 2.00, 100, 20, 'glass', 'Glass of house red', 1),
(38, 'House White Wine', 'ALC-WHT-001', '4000000000002', 11, 6.00, 2.00, 100, 20, 'glass', 'Glass of house white', 1),
(39, 'Prosecco', 'ALC-PRO-001', '4000000000003', 11, 8.00, 3.00, 50, 10, 'glass', 'Italian sparkling wine', 1),
(40, 'Cabernet Sauvignon Bottle', 'ALC-CAB-001', '4000000000004', 11, 35.00, 15.00, 30, 5, 'bottle', '750ml premium red', 1),

-- Spirits
(41, 'Whiskey', 'ALC-WHI-001', '4000000000005', 12, 8.00, 3.00, 50, 10, 'shot', 'Single malt whiskey', 1),
(42, 'Vodka', 'ALC-VOD-001', '4000000000006', 12, 6.00, 2.00, 50, 10, 'shot', 'Premium vodka', 1),
(43, 'Gin & Tonic', 'ALC-GNT-001', '4000000000007', 12, 9.00, 3.50, 50, 10, 'glass', 'Gin with tonic water', 1),
(44, 'Rum & Coke', 'ALC-RUM-001', '4000000000008', 12, 8.00, 3.00, 50, 10, 'glass', 'Dark rum with cola', 1),

-- Beers
(45, 'Local Lager', 'ALC-LAG-001', '4000000000009', 13, 4.50, 1.50, 100, 30, 'bottle', '330ml local beer', 1),
(46, 'Imported Beer', 'ALC-IMP-001', '4000000000010', 13, 6.00, 2.50, 80, 20, 'bottle', '330ml imported', 1),
(47, 'Draft Beer', 'ALC-DRF-001', '4000000000011', 13, 5.00, 1.80, 200, 50, 'pint', 'Fresh draft beer', 1);

-- =====================================================
-- CUSTOMERS
-- =====================================================
INSERT IGNORE INTO customers (id, name, email, phone, address, loyalty_points, total_spent, visit_count, is_active, created_at) VALUES
(1, 'John Smith', 'john.smith@email.com', '+1234567001', '123 Main Street, City', 250, 1500.00, 15, 1, DATE_SUB(NOW(), INTERVAL 6 MONTH)),
(2, 'Sarah Johnson', 'sarah.j@email.com', '+1234567002', '456 Oak Avenue, Town', 180, 980.00, 12, 1, DATE_SUB(NOW(), INTERVAL 5 MONTH)),
(3, 'Michael Brown', 'mbrown@email.com', '+1234567003', '789 Pine Road, Village', 320, 2100.00, 22, 1, DATE_SUB(NOW(), INTERVAL 8 MONTH)),
(4, 'Emily Davis', 'emily.d@email.com', '+1234567004', '321 Elm Street, City', 90, 450.00, 6, 1, DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(5, 'David Wilson', 'dwilson@email.com', '+1234567005', '654 Maple Lane, Town', 410, 2800.00, 28, 1, DATE_SUB(NOW(), INTERVAL 10 MONTH)),
(6, 'Lisa Anderson', 'lisa.a@email.com', '+1234567006', '987 Cedar Drive, City', 150, 750.00, 10, 1, DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(7, 'James Taylor', 'jtaylor@email.com', '+1234567007', '147 Birch Way, Village', 280, 1650.00, 18, 1, DATE_SUB(NOW(), INTERVAL 7 MONTH)),
(8, 'Jennifer Martinez', 'jmartinez@email.com', '+1234567008', '258 Spruce Court, Town', 60, 320.00, 4, 1, DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(9, 'Robert Garcia', 'rgarcia@email.com', '+1234567009', '369 Willow Street, City', 200, 1100.00, 14, 1, DATE_SUB(NOW(), INTERVAL 5 MONTH)),
(10, 'Amanda Lee', 'alee@email.com', '+1234567010', '741 Ash Boulevard, Town', 340, 2250.00, 24, 1, DATE_SUB(NOW(), INTERVAL 9 MONTH)),
(11, 'Corporate Client A', 'accounts@companya.com', '+1234567011', '100 Business Park, City', 500, 5000.00, 35, 1, DATE_SUB(NOW(), INTERVAL 12 MONTH)),
(12, 'Hotel Guest Services', 'concierge@hotel.com', '+1234567012', 'In-House', 0, 0.00, 0, 1, NOW());

-- =====================================================
-- SUPPLIERS
-- =====================================================
INSERT IGNORE INTO suppliers (id, name, contact_person, email, phone, address, payment_terms, is_active, created_at) VALUES
(1, 'Fresh Foods Ltd', 'Tom Baker', 'orders@freshfoods.com', '+1234560001', '10 Industrial Estate, City', 'Net 30', 1, DATE_SUB(NOW(), INTERVAL 12 MONTH)),
(2, 'Beverage Distributors Inc', 'Mary White', 'sales@bevdist.com', '+1234560002', '25 Warehouse Road, Town', 'Net 15', 1, DATE_SUB(NOW(), INTERVAL 10 MONTH)),
(3, 'Premium Wines & Spirits', 'Charles Green', 'orders@premiumws.com', '+1234560003', '50 Wine Lane, City', 'Net 30', 1, DATE_SUB(NOW(), INTERVAL 8 MONTH)),
(4, 'Dairy Fresh Co', 'Susan Black', 'supply@dairyfresh.com', '+1234560004', '15 Farm Road, Village', 'COD', 1, DATE_SUB(NOW(), INTERVAL 6 MONTH)),
(5, 'Bakery Supplies Ltd', 'Peter Brown', 'orders@bakerysupplies.com', '+1234560005', '30 Mill Street, Town', 'Net 14', 1, DATE_SUB(NOW(), INTERVAL 4 MONTH));

-- =====================================================
-- RESTAURANT TABLES
-- =====================================================
INSERT IGNORE INTO restaurant_tables (id, table_number, capacity, location, status, is_active) VALUES
(1, 'T1', 2, 'Window', 'available', 1),
(2, 'T2', 2, 'Window', 'available', 1),
(3, 'T3', 4, 'Main Floor', 'available', 1),
(4, 'T4', 4, 'Main Floor', 'available', 1),
(5, 'T5', 4, 'Main Floor', 'available', 1),
(6, 'T6', 6, 'Main Floor', 'available', 1),
(7, 'T7', 6, 'Corner', 'available', 1),
(8, 'T8', 8, 'Private Area', 'available', 1),
(9, 'T9', 8, 'Private Area', 'available', 1),
(10, 'T10', 10, 'Private Room', 'available', 1),
(11, 'B1', 4, 'Bar', 'available', 1),
(12, 'B2', 4, 'Bar', 'available', 1),
(13, 'P1', 4, 'Patio', 'available', 1),
(14, 'P2', 4, 'Patio', 'available', 1),
(15, 'P3', 6, 'Patio', 'available', 1);

-- =====================================================
-- ROOMS (Hotel)
-- =====================================================
INSERT IGNORE INTO rooms (id, room_number, room_type, floor, capacity, base_price, status, amenities, description, is_active) VALUES
(1, '101', 'standard', 1, 2, 120.00, 'available', '["wifi","tv","ac","minibar"]', 'Standard room with city view', 1),
(2, '102', 'standard', 1, 2, 120.00, 'available', '["wifi","tv","ac","minibar"]', 'Standard room with garden view', 1),
(3, '103', 'standard', 1, 2, 120.00, 'occupied', '["wifi","tv","ac","minibar"]', 'Standard room with city view', 1),
(4, '201', 'deluxe', 2, 2, 180.00, 'available', '["wifi","tv","ac","minibar","balcony"]', 'Deluxe room with balcony', 1),
(5, '202', 'deluxe', 2, 2, 180.00, 'available', '["wifi","tv","ac","minibar","balcony"]', 'Deluxe room with sea view', 1),
(6, '203', 'deluxe', 2, 3, 200.00, 'maintenance', '["wifi","tv","ac","minibar","balcony"]', 'Deluxe family room', 1),
(7, '301', 'suite', 3, 2, 280.00, 'available', '["wifi","tv","ac","minibar","balcony","jacuzzi"]', 'Junior suite with jacuzzi', 1),
(8, '302', 'suite', 3, 4, 350.00, 'available', '["wifi","tv","ac","minibar","balcony","jacuzzi","kitchen"]', 'Executive suite', 1),
(9, '401', 'presidential', 4, 4, 500.00, 'available', '["wifi","tv","ac","minibar","balcony","jacuzzi","kitchen","butler"]', 'Presidential suite', 1),
(10, '402', 'presidential', 4, 6, 650.00, 'reserved', '["wifi","tv","ac","minibar","balcony","jacuzzi","kitchen","butler","private_pool"]', 'Royal suite with private pool', 1);

-- =====================================================
-- ROOM BOOKINGS
-- =====================================================
INSERT IGNORE INTO room_bookings (id, booking_number, room_id, customer_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, adults, children, total_amount, amount_paid, status, special_requests, created_at) VALUES
(1, 'BK-2024-001', 3, 1, 'John Smith', 'john.smith@email.com', '+1234567001', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 2, 0, 360.00, 360.00, 'checked_in', 'Late checkout requested', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'BK-2024-002', 10, 5, 'David Wilson', 'dwilson@email.com', '+1234567005', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 4, 2, 1950.00, 500.00, 'confirmed', 'Anniversary celebration - champagne on arrival', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'BK-2024-003', 4, 3, 'Michael Brown', 'mbrown@email.com', '+1234567003', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 2, 0, 540.00, 540.00, 'confirmed', NULL, NOW()),
(4, 'BK-2024-004', 7, 7, 'James Taylor', 'jtaylor@email.com', '+1234567007', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 2, 0, 840.00, 0.00, 'pending', 'Honeymoon package', NOW());

-- =====================================================
-- VOID REASON CODES
-- =====================================================
INSERT IGNORE INTO void_reason_codes (id, code, display_name, description, requires_manager_approval, affects_inventory, is_active) VALUES
(1, 'CUST_REQUEST', 'Customer Request', 'Customer changed their mind or cancelled', 0, 1, 1),
(2, 'WRONG_ORDER', 'Wrong Order', 'Incorrect item was ordered or entered', 0, 1, 1),
(3, 'QUALITY_ISSUE', 'Quality Issue', 'Food/item quality not acceptable', 1, 1, 1),
(4, 'KITCHEN_ERROR', 'Kitchen Error', 'Kitchen prepared wrong item', 0, 1, 1),
(5, 'SYSTEM_ERROR', 'System Error', 'POS or system malfunction', 1, 0, 1),
(6, 'DUPLICATE', 'Duplicate Entry', 'Item was entered twice by mistake', 0, 0, 1),
(7, 'PRICE_ADJUST', 'Price Adjustment', 'Price correction required', 1, 0, 1),
(8, 'COMP', 'Complimentary', 'Item given complimentary to guest', 1, 1, 1),
(9, 'SPILLAGE', 'Spillage/Breakage', 'Item was spilled or broken', 0, 1, 1),
(10, 'OTHER', 'Other', 'Other reason - requires notes', 1, 0, 1);

-- =====================================================
-- VOID SETTINGS
-- =====================================================
INSERT IGNORE INTO void_settings (setting_key, setting_value, description) VALUES
('require_manager_approval', '1', 'Require manager approval for voids'),
('manager_approval_threshold', '50.00', 'Amount above which manager approval is required'),
('daily_void_limit', '10', 'Maximum voids per user per day'),
('auto_adjust_inventory', '1', 'Automatically adjust inventory on void'),
('notification_email', '', 'Email to notify on voids'),
('void_receipt_copies', '2', 'Number of void receipt copies to print');

SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Demo data seeded successfully!' as Status, 
       (SELECT COUNT(*) FROM products) as Products,
       (SELECT COUNT(*) FROM categories) as Categories,
       (SELECT COUNT(*) FROM customers) as Customers,
       (SELECT COUNT(*) FROM suppliers) as Suppliers,
       (SELECT COUNT(*) FROM restaurant_tables) as Tables,
       (SELECT COUNT(*) FROM rooms) as Rooms,
       (SELECT COUNT(*) FROM room_bookings) as Bookings;
