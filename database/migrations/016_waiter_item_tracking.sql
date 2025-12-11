-- Migration: Add waiter/server tracking at item level for Bar and Restaurant
-- This allows tracking which waiter added each item, enabling:
-- 1. Commission tracking per waiter
-- 2. Performance reports
-- 3. Shift handoff tracking
-- 4. Accountability

-- Add added_by column to bar_tab_items
ALTER TABLE bar_tab_items 
ADD COLUMN IF NOT EXISTS added_by INT UNSIGNED NULL COMMENT 'Waiter who added this item' AFTER special_instructions,
ADD COLUMN IF NOT EXISTS added_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When item was added' AFTER added_by;

-- Add index for waiter performance queries
ALTER TABLE bar_tab_items 
ADD INDEX IF NOT EXISTS idx_added_by (added_by);

-- Add added_by column to restaurant_order_items if it exists
ALTER TABLE restaurant_order_items 
ADD COLUMN IF NOT EXISTS added_by INT UNSIGNED NULL COMMENT 'Waiter who added this item' AFTER special_instructions,
ADD COLUMN IF NOT EXISTS added_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When item was added' AFTER added_by;

-- Add index for waiter performance queries
ALTER TABLE restaurant_order_items 
ADD INDEX IF NOT EXISTS idx_added_by (added_by);

-- Create waiter performance summary view
CREATE OR REPLACE VIEW waiter_daily_performance AS
SELECT 
    DATE(bti.added_at) as work_date,
    bti.added_by as waiter_id,
    u.full_name as waiter_name,
    'bar' as source,
    COUNT(DISTINCT bti.tab_id) as tabs_served,
    COUNT(bti.id) as items_served,
    SUM(bti.total_price) as total_sales,
    SUM(CASE WHEN bti.status = 'voided' THEN bti.total_price ELSE 0 END) as voided_amount
FROM bar_tab_items bti
LEFT JOIN users u ON bti.added_by = u.id
WHERE bti.added_by IS NOT NULL
GROUP BY DATE(bti.added_at), bti.added_by, u.full_name

UNION ALL

SELECT 
    DATE(roi.added_at) as work_date,
    roi.added_by as waiter_id,
    u.full_name as waiter_name,
    'restaurant' as source,
    COUNT(DISTINCT roi.order_id) as orders_served,
    COUNT(roi.id) as items_served,
    SUM(roi.total_price) as total_sales,
    SUM(CASE WHEN roi.status = 'voided' THEN roi.total_price ELSE 0 END) as voided_amount
FROM restaurant_order_items roi
LEFT JOIN users u ON roi.added_by = u.id
WHERE roi.added_by IS NOT NULL
GROUP BY DATE(roi.added_at), roi.added_by, u.full_name;

-- Create waiter tips tracking table
CREATE TABLE IF NOT EXISTS waiter_tips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waiter_id INT UNSIGNED NOT NULL,
    source_type ENUM('bar_tab', 'restaurant_order', 'room_service') NOT NULL,
    source_id INT UNSIGNED NOT NULL COMMENT 'tab_id or order_id',
    tip_amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_waiter (waiter_id),
    INDEX idx_source (source_type, source_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tab/Order transfer history for shift handoffs
CREATE TABLE IF NOT EXISTS service_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('bar_tab', 'restaurant_order') NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    from_waiter_id INT UNSIGNED NOT NULL,
    to_waiter_id INT UNSIGNED NOT NULL,
    transfer_reason VARCHAR(255) NULL COMMENT 'shift_change, break, reassignment',
    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_source (source_type, source_id),
    INDEX idx_from (from_waiter_id),
    INDEX idx_to (to_waiter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
