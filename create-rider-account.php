<?php
/**
 * Create Rider Account - PHP Script
 * Run this script to create a rider account with proper password hashing
 */

require_once 'includes/bootstrap.php';

// Configuration - CHANGE THESE VALUES
$username = 'rider1';
$password = 'SecurePass123!';  // Change to a secure password
$fullName = 'John Rider';
$email = 'rider1@example.com';
$phone = '+254712345678';
$vehicleType = 'motorcycle';  // Options: motorcycle, car, bicycle, van
$vehicleNumber = 'KAA 123A';

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Check if username already exists
    $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing) {
        throw new Exception("Username '{$username}' already exists!");
    }
    
    // Create user account
    $db->query("
        INSERT INTO users (username, password, full_name, email, role, is_active)
        VALUES (?, ?, ?, ?, 'rider', 1)
    ", [$username, $hashedPassword, $fullName, $email]);
    
    $userId = $db->getConnection()->lastInsertId();
    
    // Create rider profile
    $db->query("
        INSERT INTO riders (user_id, name, phone, vehicle_type, vehicle_number, status)
        VALUES (?, ?, ?, ?, ?, 'available')
    ", [$userId, $fullName, $phone, $vehicleType, $vehicleNumber]);
    
    $riderId = $db->getConnection()->lastInsertId();
    
    $db->commit();
    
    echo "✅ Rider account created successfully!\n\n";
    echo "═══════════════════════════════════════\n";
    echo "User ID:        {$userId}\n";
    echo "Rider ID:       {$riderId}\n";
    echo "Username:       {$username}\n";
    echo "Password:       {$password}\n";
    echo "Full Name:      {$fullName}\n";
    echo "Email:          {$email}\n";
    echo "Phone:          {$phone}\n";
    echo "Vehicle Type:   {$vehicleType}\n";
    echo "Vehicle Number: {$vehicleNumber}\n";
    echo "Status:         available\n";
    echo "═══════════════════════════════════════\n\n";
    echo "🔗 Rider Login URL:\n";
    echo "   " . APP_URL . "/rider-login.php\n\n";
    echo "📱 Share these credentials with the rider:\n";
    echo "   Username: {$username}\n";
    echo "   Password: {$password}\n\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
