-- Migration: Delivery Dispatch Log Table
-- Purpose: Track intelligent dispatch decisions for analytics and optimization
-- Date: 2025-12-17

-- Create delivery dispatch log table
CREATE TABLE IF NOT EXISTS delivery_dispatch_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    selected_rider_id INT NOT NULL,
    duration_seconds INT NOT NULL,
    distance_meters INT NOT NULL,
    candidates_evaluated INT NOT NULL DEFAULT 0,
    selection_score DECIMAL(10,2) NOT NULL,
    dispatch_data JSON NULL COMMENT 'Full dispatch result including alternatives',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_delivery (delivery_id),
    INDEX idx_rider (selected_rider_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs intelligent dispatch decisions for analytics';

-- Add max_active_deliveries to riders table if not exists
ALTER TABLE riders 
ADD COLUMN IF NOT EXISTS max_active_deliveries INT NOT NULL DEFAULT 3 
COMMENT 'Maximum concurrent deliveries this rider can handle'
AFTER is_active;

-- Update existing riders to have default capacity
UPDATE riders SET max_active_deliveries = 3 WHERE max_active_deliveries IS NULL OR max_active_deliveries = 0;
