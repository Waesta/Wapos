-- ============================================================
-- WAPOS Sample Sales Data Generator
-- Generated: 2025-12-05
-- Purpose: Create realistic sales history for testing reports
-- ============================================================

USE wapos;

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ============================================================
-- GENERATE SALES FOR LAST 30 DAYS
-- ============================================================

-- Create a procedure to generate sales
DROP PROCEDURE IF EXISTS generate_sample_sales;

DELIMITER //

CREATE PROCEDURE generate_sample_sales()
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE sale_date DATETIME;
    DECLARE loc_id INT;
    DECLARE user_id INT;
    DECLARE cust_id INT;
    DECLARE reg_id INT;
    DECLARE sale_id INT;
    DECLARE item_count INT;
    DECLARE prod_id INT;
    DECLARE prod_price DECIMAL(10,2);
    DECLARE qty INT;
    DECLARE subtotal DECIMAL(10,2);
    DECLARE sale_total DECIMAL(10,2);
    DECLARE payment_method VARCHAR(20);
    DECLARE j INT;
    
    -- Generate 500 sales over 30 days
    WHILE i < 500 DO
        -- Random date in last 30 days
        SET sale_date = DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY);
        SET sale_date = DATE_ADD(sale_date, INTERVAL FLOOR(RAND() * 12 + 8) HOUR); -- 8am to 8pm
        SET sale_date = DATE_ADD(sale_date, INTERVAL FLOOR(RAND() * 60) MINUTE);
        
        -- Random location (weighted towards location 1 and 2)
        SET loc_id = CASE 
            WHEN RAND() < 0.4 THEN 1
            WHEN RAND() < 0.7 THEN 2
            WHEN RAND() < 0.9 THEN 3
            ELSE 4
        END;
        
        -- Random cashier based on location
        SELECT id INTO user_id FROM users 
        WHERE role IN ('cashier', 'admin', 'manager') AND location_id = loc_id 
        ORDER BY RAND() LIMIT 1;
        
        IF user_id IS NULL THEN
            SET user_id = 1;
        END IF;
        
        -- Random customer (70% have customer, 30% walk-in)
        IF RAND() < 0.7 THEN
            SELECT id INTO cust_id FROM customers ORDER BY RAND() LIMIT 1;
        ELSE
            SET cust_id = NULL;
        END IF;
        
        -- Random register based on location
        SELECT id INTO reg_id FROM registers WHERE location_id = loc_id ORDER BY RAND() LIMIT 1;
        IF reg_id IS NULL THEN
            SET reg_id = 1;
        END IF;
        
        -- Random payment method
        SET payment_method = CASE 
            WHEN RAND() < 0.5 THEN 'cash'
            WHEN RAND() < 0.75 THEN 'mpesa'
            WHEN RAND() < 0.9 THEN 'card'
            ELSE 'split'
        END;
        
        -- Create sale
        INSERT INTO sales (
            location_id, user_id, customer_id, register_id,
            sale_number, subtotal, tax_amount, discount_amount, total_amount,
            payment_method, payment_status, status, created_at
        ) VALUES (
            loc_id, user_id, cust_id, reg_id,
            CONCAT('SAL-', DATE_FORMAT(sale_date, '%Y%m%d'), '-', LPAD(i + 1, 4, '0')),
            0, 0, 0, 0,
            payment_method, 'paid', 'completed', sale_date
        );
        
        SET sale_id = LAST_INSERT_ID();
        SET sale_total = 0;
        
        -- Add 1-5 items to sale
        SET item_count = FLOOR(RAND() * 5) + 1;
        SET j = 0;
        
        WHILE j < item_count DO
            -- Random product (exclude room supplies)
            SELECT p.id, p.selling_price INTO prod_id, prod_price 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE c.name != 'Room Supplies' AND p.selling_price > 0
            ORDER BY RAND() LIMIT 1;
            
            IF prod_id IS NOT NULL THEN
                SET qty = FLOOR(RAND() * 3) + 1;
                SET subtotal = prod_price * qty;
                SET sale_total = sale_total + subtotal;
                
                INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal, created_at)
                VALUES (sale_id, prod_id, qty, prod_price, subtotal, sale_date);
            END IF;
            
            SET j = j + 1;
        END WHILE;
        
        -- Update sale totals
        UPDATE sales SET 
            subtotal = sale_total,
            total_amount = sale_total
        WHERE id = sale_id;
        
        -- Add loyalty points if customer exists
        IF cust_id IS NOT NULL THEN
            UPDATE customers SET 
                loyalty_points = loyalty_points + FLOOR(sale_total / 1000)
            WHERE id = cust_id;
        END IF;
        
        SET i = i + 1;
    END WHILE;
END //

DELIMITER ;

-- Execute the procedure
CALL generate_sample_sales();

-- Clean up
DROP PROCEDURE IF EXISTS generate_sample_sales;

-- ============================================================
-- GENERATE REGISTER SESSIONS
-- ============================================================

-- Create register sessions for the past 30 days
INSERT INTO register_sessions (register_id, user_id, opening_balance, closing_balance, expected_balance, 
    cash_sales, card_sales, mobile_sales, status, opened_at, closed_at, notes)
SELECT 
    r.id as register_id,
    u.id as user_id,
    50000 as opening_balance,
    50000 + FLOOR(RAND() * 200000) as closing_balance,
    50000 + FLOOR(RAND() * 200000) as expected_balance,
    FLOOR(RAND() * 150000) as cash_sales,
    FLOOR(RAND() * 80000) as card_sales,
    FLOOR(RAND() * 100000) as mobile_sales,
    'closed' as status,
    DATE_SUB(NOW(), INTERVAL d.day_offset DAY) + INTERVAL 8 HOUR as opened_at,
    DATE_SUB(NOW(), INTERVAL d.day_offset DAY) + INTERVAL 20 HOUR as closed_at,
    CONCAT('Session for ', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL d.day_offset DAY), '%Y-%m-%d')) as notes
FROM registers r
CROSS JOIN users u
CROSS JOIN (
    SELECT 1 as day_offset UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
) d
WHERE u.role IN ('cashier', 'admin') AND u.location_id = r.location_id
AND RAND() < 0.3 -- Only create sessions for ~30% of combinations
LIMIT 50;

-- ============================================================
-- UPDATE STATISTICS
-- ============================================================

-- Update product stock based on sales
UPDATE products p
SET stock_quantity = GREATEST(10, stock_quantity - (
    SELECT COALESCE(SUM(si.quantity), 0) 
    FROM sale_items si 
    WHERE si.product_id = p.id
) * 0.1); -- Reduce by 10% of sold quantity to simulate restocking

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SUMMARY
-- ============================================================
-- Sales Generated: ~500
-- Sale Items: ~1500
-- Register Sessions: ~50
-- Date Range: Last 30 days
-- Locations: All 4 locations
-- Payment Methods: Cash (50%), M-Pesa (25%), Card (15%), Split (10%)
-- ============================================================

SELECT 'Sample sales data generated successfully!' as Status;
SELECT COUNT(*) as TotalSales FROM sales;
SELECT COUNT(*) as TotalSaleItems FROM sale_items;
SELECT COUNT(*) as TotalRegisterSessions FROM register_sessions;
