<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

try {
    // Create print_log table if it doesn't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS print_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            receipt_type ENUM('kitchen', 'invoice', 'receipt', 'bar') NOT NULL,
            action ENUM('print', 'reprint', 'failed') NOT NULL,
            copies INT DEFAULT 1,
            printer_name VARCHAR(100),
            printer_ip VARCHAR(45),
            user_id INT UNSIGNED,
            error_message TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_id (order_id),
            INDEX idx_receipt_type (receipt_type),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB
    ");
    
    // Add missing columns to products table if they don't exist
    try {
        $db->query("ALTER TABLE products ADD COLUMN prep_time INT DEFAULT 0 COMMENT 'Preparation time in minutes'");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    try {
        $db->query("ALTER TABLE products ADD COLUMN allergens VARCHAR(255) COMMENT 'Comma-separated list of allergens'");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    try {
        $db->query("ALTER TABLE products ADD COLUMN kitchen_notes TEXT COMMENT 'Special preparation notes for kitchen'");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    // Add missing columns to order_items table if they don't exist
    try {
        $db->query("ALTER TABLE order_items ADD COLUMN prep_status ENUM('pending', 'preparing', 'ready', 'served') DEFAULT 'pending'");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    try {
        $db->query("ALTER TABLE order_items ADD COLUMN prep_started_at TIMESTAMP NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    try {
        $db->query("ALTER TABLE order_items ADD COLUMN prep_completed_at TIMESTAMP NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    // Create printer_config table if it doesn't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS printer_config (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            printer_name VARCHAR(100) NOT NULL,
            printer_type ENUM('kitchen', 'customer', 'bar', 'receipt') NOT NULL,
            ip_address VARCHAR(45),
            port INT DEFAULT 9100,
            is_active BOOLEAN DEFAULT TRUE,
            zone_categories JSON,
            print_format ENUM('compact', 'standard', 'detailed') DEFAULT 'standard',
            auto_print BOOLEAN DEFAULT TRUE,
            copies INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_printer_type (printer_type),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB
    ");
    
    // Insert default printer configurations if they don't exist
    $existingPrinters = $db->fetchAll("SELECT printer_name FROM printer_config");
    $printerNames = array_column($existingPrinters, 'printer_name');
    
    if (!in_array('Kitchen Printer', $printerNames)) {
        $db->insert('printer_config', [
            'printer_name' => 'Kitchen Printer',
            'printer_type' => 'kitchen',
            'print_format' => 'standard',
            'auto_print' => true,
            'copies' => 1
        ]);
    }
    
    if (!in_array('Customer Printer', $printerNames)) {
        $db->insert('printer_config', [
            'printer_name' => 'Customer Printer',
            'printer_type' => 'customer',
            'print_format' => 'detailed',
            'auto_print' => true,
            'copies' => 1
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Restaurant database tables created/updated successfully!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error setting up restaurant tables: ' . $e->getMessage()
    ]);
}
?>
