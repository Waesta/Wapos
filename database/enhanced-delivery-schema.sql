-- Enhanced Delivery Tracking System Database Schema
-- This extends the existing delivery system with advanced tracking capabilities

-- Add columns to existing riders table
ALTER TABLE riders 
ADD COLUMN IF NOT EXISTS current_latitude DECIMAL(10, 8),
ADD COLUMN IF NOT EXISTS current_longitude DECIMAL(11, 8),
ADD COLUMN IF NOT EXISTS location_accuracy FLOAT,
ADD COLUMN IF NOT EXISTS last_location_update TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS is_online BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS shift_start_time TIME,
ADD COLUMN IF NOT EXISTS shift_end_time TIME,
ADD COLUMN IF NOT EXISTS max_deliveries_per_hour INT DEFAULT 3,
ADD COLUMN IF NOT EXISTS vehicle_capacity INT DEFAULT 5,
ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(20),
ADD COLUMN IF NOT EXISTS license_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS insurance_expiry DATE;

-- Add columns to existing deliveries table
ALTER TABLE deliveries 
ADD COLUMN IF NOT EXISTS pickup_latitude DECIMAL(10, 8),
ADD COLUMN IF NOT EXISTS pickup_longitude DECIMAL(11, 8),
ADD COLUMN IF NOT EXISTS delivery_latitude DECIMAL(10, 8),
ADD COLUMN IF NOT EXISTS delivery_longitude DECIMAL(11, 8),
ADD COLUMN IF NOT EXISTS estimated_distance_km FLOAT,
ADD COLUMN IF NOT EXISTS actual_distance_km FLOAT,
ADD COLUMN IF NOT EXISTS estimated_delivery_time DATETIME,
ADD COLUMN IF NOT EXISTS pickup_time DATETIME,
ADD COLUMN IF NOT EXISTS delivery_proof_photo VARCHAR(255),
ADD COLUMN IF NOT EXISTS customer_signature TEXT,
ADD COLUMN IF NOT EXISTS delivery_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS failed_reason TEXT,
ADD COLUMN IF NOT EXISTS weather_conditions VARCHAR(50),
ADD COLUMN IF NOT EXISTS traffic_conditions VARCHAR(50);

-- Rider location history for tracking
CREATE TABLE IF NOT EXISTS rider_location_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy FLOAT,
    speed FLOAT,
    heading FLOAT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    INDEX idx_rider_time (rider_id, recorded_at),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB;

-- Delivery status history for detailed tracking
CREATE TABLE IF NOT EXISTS delivery_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'assigned', 'picked-up', 'in-transit', 'delivered', 'failed', 'cancelled') NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    notes TEXT,
    photo_url VARCHAR(255),
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_delivery_status (delivery_id, status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Delivery routes for optimization
CREATE TABLE IF NOT EXISTS delivery_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    route_name VARCHAR(100),
    start_latitude DECIMAL(10, 8),
    start_longitude DECIMAL(11, 8),
    end_latitude DECIMAL(10, 8),
    end_longitude DECIMAL(11, 8),
    total_distance_km FLOAT,
    estimated_duration_minutes INT,
    actual_duration_minutes INT,
    delivery_count INT DEFAULT 0,
    route_date DATE,
    status ENUM('planned', 'active', 'completed', 'cancelled') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    INDEX idx_rider_date (rider_id, route_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Route waypoints for multi-stop deliveries
CREATE TABLE IF NOT EXISTS route_waypoints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_id INT UNSIGNED NOT NULL,
    delivery_id INT UNSIGNED NOT NULL,
    sequence_order INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    estimated_arrival DATETIME,
    actual_arrival DATETIME,
    time_spent_minutes INT,
    status ENUM('pending', 'arrived', 'completed', 'skipped') DEFAULT 'pending',
    FOREIGN KEY (route_id) REFERENCES delivery_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    INDEX idx_route_sequence (route_id, sequence_order)
) ENGINE=InnoDB;

-- Customer delivery preferences
CREATE TABLE IF NOT EXISTS customer_delivery_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED,
    customer_phone VARCHAR(20),
    preferred_delivery_time_start TIME,
    preferred_delivery_time_end TIME,
    delivery_instructions TEXT,
    access_codes TEXT,
    special_requirements TEXT,
    notification_preferences JSON,
    address_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_phone (customer_phone)
) ENGINE=InnoDB;

-- Delivery notifications log
CREATE TABLE IF NOT EXISTS delivery_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    notification_type ENUM('sms', 'email', 'whatsapp', 'push', 'call') NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT,
    cost DECIMAL(10, 4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    INDEX idx_delivery_type (delivery_id, notification_type),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Delivery performance metrics
CREATE TABLE IF NOT EXISTS delivery_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    rider_id INT UNSIGNED,
    total_deliveries INT DEFAULT 0,
    successful_deliveries INT DEFAULT 0,
    failed_deliveries INT DEFAULT 0,
    average_delivery_time_minutes FLOAT DEFAULT 0,
    total_distance_km FLOAT DEFAULT 0,
    total_earnings DECIMAL(10, 2) DEFAULT 0,
    customer_rating_average FLOAT DEFAULT 0,
    on_time_delivery_rate FLOAT DEFAULT 0,
    fuel_cost DECIMAL(10, 2) DEFAULT 0,
    maintenance_cost DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rider_date (rider_id, metric_date),
    INDEX idx_date (metric_date)
) ENGINE=InnoDB;

-- Delivery zones for geographic organization
CREATE TABLE IF NOT EXISTS delivery_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(100) NOT NULL,
    zone_code VARCHAR(20) UNIQUE NOT NULL,
    boundary_coordinates JSON NOT NULL,
    base_delivery_fee DECIMAL(8, 2) DEFAULT 0,
    per_km_rate DECIMAL(8, 2) DEFAULT 0,
    estimated_delivery_time_minutes INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    priority_level INT DEFAULT 1,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_priority (priority_level)
) ENGINE=InnoDB;

-- Weather data for delivery optimization
CREATE TABLE IF NOT EXISTS weather_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recorded_date DATE NOT NULL,
    recorded_hour INT NOT NULL,
    temperature FLOAT,
    humidity FLOAT,
    wind_speed FLOAT,
    precipitation FLOAT,
    weather_condition VARCHAR(50),
    visibility_km FLOAT,
    impact_on_delivery ENUM('none', 'minor', 'moderate', 'severe') DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_hour (recorded_date, recorded_hour),
    INDEX idx_condition (weather_condition)
) ENGINE=InnoDB;

-- Delivery feedback and issues
CREATE TABLE IF NOT EXISTS delivery_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    feedback_type ENUM('complaint', 'compliment', 'suggestion', 'issue') NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    feedback_text TEXT,
    customer_name VARCHAR(100),
    customer_contact VARCHAR(100),
    resolution_status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
    resolution_notes TEXT,
    resolved_by INT UNSIGNED,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type_status (feedback_type, resolution_status),
    INDEX idx_rating (rating)
) ENGINE=InnoDB;

-- Create views for common queries
CREATE OR REPLACE VIEW active_deliveries_view AS
SELECT 
    d.*,
    o.order_number,
    o.customer_name,
    o.customer_phone,
    o.total_amount,
    r.name as rider_name,
    r.phone as rider_phone,
    r.vehicle_type,
    r.current_latitude as rider_lat,
    r.current_longitude as rider_lng,
    r.is_online,
    TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) as elapsed_minutes,
    dz.zone_name,
    dz.estimated_delivery_time_minutes as zone_estimate
FROM deliveries d
JOIN orders o ON d.order_id = o.id
LEFT JOIN riders r ON d.rider_id = r.id
LEFT JOIN delivery_zones dz ON ST_Contains(
    ST_GeomFromGeoJSON(dz.boundary_coordinates),
    ST_Point(d.delivery_longitude, d.delivery_latitude)
)
WHERE d.status IN ('pending', 'assigned', 'picked-up', 'in-transit');

CREATE OR REPLACE VIEW rider_performance_view AS
SELECT 
    r.id,
    r.name,
    r.phone,
    r.vehicle_type,
    r.is_online,
    COUNT(d.id) as total_deliveries_today,
    AVG(d.customer_rating) as avg_rating,
    AVG(TIMESTAMPDIFF(MINUTE, d.created_at, d.actual_delivery_time)) as avg_delivery_time,
    SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries,
    SUM(CASE WHEN d.status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries
FROM riders r
LEFT JOIN deliveries d ON r.id = d.rider_id AND DATE(d.created_at) = CURDATE()
WHERE r.is_active = 1
GROUP BY r.id, r.name, r.phone, r.vehicle_type, r.is_online;

-- Insert sample delivery zones
INSERT INTO delivery_zones (zone_name, zone_code, boundary_coordinates, base_delivery_fee, per_km_rate, estimated_delivery_time_minutes) VALUES
('City Center', 'CC', '{"type":"Polygon","coordinates":[[[-1.2920,36.8219],[-1.2820,36.8219],[-1.2820,36.8319],[-1.2920,36.8319],[-1.2920,36.8219]]]}', 50.00, 10.00, 20),
('Westlands', 'WL', '{"type":"Polygon","coordinates":[[[-1.2720,36.8119],[-1.2620,36.8119],[-1.2620,36.8219],[-1.2720,36.8219],[-1.2720,36.8119]]]}', 75.00, 15.00, 30),
('Eastleigh', 'EL', '{"type":"Polygon","coordinates":[[[-1.2520,36.8419],[-1.2420,36.8419],[-1.2420,36.8519],[-1.2520,36.8519],[-1.2520,36.8419]]]}', 100.00, 20.00, 45)
ON DUPLICATE KEY UPDATE zone_name = VALUES(zone_name);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_deliveries_status_created ON deliveries(status, created_at);
CREATE INDEX IF NOT EXISTS idx_riders_online_location ON riders(is_online, current_latitude, current_longitude);
CREATE INDEX IF NOT EXISTS idx_delivery_coordinates ON deliveries(delivery_latitude, delivery_longitude);

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS UpdateDeliveryStatus(
    IN p_delivery_id INT,
    IN p_status VARCHAR(20),
    IN p_latitude DECIMAL(10,8),
    IN p_longitude DECIMAL(11,8),
    IN p_notes TEXT,
    IN p_user_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update delivery status
    UPDATE deliveries 
    SET status = p_status, updated_at = NOW()
    WHERE id = p_delivery_id;
    
    -- Log status change
    INSERT INTO delivery_status_history 
    (delivery_id, status, latitude, longitude, notes, user_id)
    VALUES (p_delivery_id, p_status, p_latitude, p_longitude, p_notes, p_user_id);
    
    -- Update actual delivery time if delivered
    IF p_status = 'delivered' THEN
        UPDATE deliveries 
        SET actual_delivery_time = NOW()
        WHERE id = p_delivery_id;
    END IF;
    
    COMMIT;
END //

CREATE PROCEDURE IF NOT EXISTS CalculateDeliveryMetrics(
    IN p_date DATE
)
BEGIN
    INSERT INTO delivery_metrics (
        metric_date, rider_id, total_deliveries, successful_deliveries, 
        failed_deliveries, average_delivery_time_minutes, customer_rating_average
    )
    SELECT 
        p_date,
        rider_id,
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries,
        AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time,
        AVG(customer_rating) as avg_rating
    FROM deliveries
    WHERE DATE(created_at) = p_date AND rider_id IS NOT NULL
    GROUP BY rider_id
    ON DUPLICATE KEY UPDATE
        total_deliveries = VALUES(total_deliveries),
        successful_deliveries = VALUES(successful_deliveries),
        failed_deliveries = VALUES(failed_deliveries),
        average_delivery_time_minutes = VALUES(average_delivery_time_minutes),
        customer_rating_average = VALUES(customer_rating_average);
END //

DELIMITER ;

-- Create triggers for automatic logging
CREATE TRIGGER IF NOT EXISTS delivery_status_change_trigger
    AFTER UPDATE ON deliveries
    FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO delivery_status_history 
        (delivery_id, status, notes, created_at)
        VALUES (NEW.id, NEW.status, 'Automatic status update', NOW());
    END IF;
END;

-- Performance optimization
ANALYZE TABLE deliveries, riders, delivery_zones, rider_location_history;
