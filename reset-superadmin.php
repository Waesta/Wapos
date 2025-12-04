<?php
/**
 * Reset superadmin password - DELETE THIS FILE AFTER USE
 */

require_once 'includes/bootstrap.php';

$db = Database::getInstance();

// First, ensure super_admin exists in the ENUM
try {
    $db->query("ALTER TABLE users MODIFY COLUMN role ENUM(
        'super_admin',
        'admin',
        'manager',
        'accountant',
        'cashier',
        'waiter',
        'inventory_manager',
        'rider',
        'frontdesk',
        'housekeeping_manager',
        'housekeeping_staff',
        'maintenance_manager',
        'maintenance_staff',
        'technician',
        'engineer',
        'developer',
        'front_office_manager',
        'guest_relations_manager',
        'concierge',
        'spa_manager',
        'spa_staff',
        'events_manager',
        'banquet_supervisor',
        'room_service_manager',
        'room_service_staff',
        'security_manager',
        'security_staff',
        'hr_manager',
        'hr_staff',
        'revenue_manager',
        'sales_manager',
        'sales_executive'
    ) DEFAULT 'cashier'");
    echo "<p style='color:green;'>✓ Database schema updated - super_admin role added</p>";
} catch (Exception $e) {
    echo "<p style='color:orange;'>Schema update skipped: " . $e->getMessage() . "</p>";
}

$newPassword = 'Thepurpose@2025';
$hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 1
]);

// Check if superadmin exists
$user = $db->fetchOne("SELECT id, username, role FROM users WHERE username = 'superadmin'");

if ($user) {
    // Update existing superadmin
    $db->query("UPDATE users SET password = ?, is_active = 1 WHERE username = 'superadmin'", [$hashedPassword]);
    echo "<h2>Password updated for existing superadmin user</h2>";
} else {
    // Create superadmin user
    $db->query("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)", [
        'superadmin',
        $hashedPassword,
        'Super Administrator',
        'superadmin@wapos.local',
        'admin'  // Using 'admin' role as super_admin might not be in ENUM
    ]);
    echo "<h2>Created new superadmin user</h2>";
}

// Check if super_admin role exists in ENUM, if not use admin
// First try to set as super_admin
try {
    $db->query("ALTER TABLE users MODIFY COLUMN role ENUM(
        'super_admin',
        'admin',
        'manager',
        'accountant',
        'cashier',
        'waiter',
        'inventory_manager',
        'rider',
        'frontdesk',
        'housekeeping_manager',
        'housekeeping_staff',
        'maintenance_manager',
        'maintenance_staff',
        'technician',
        'engineer',
        'developer',
        'front_office_manager',
        'guest_relations_manager',
        'concierge',
        'spa_manager',
        'spa_staff',
        'events_manager',
        'banquet_supervisor',
        'room_service_manager',
        'room_service_staff',
        'security_manager',
        'security_staff',
        'hr_manager',
        'hr_staff',
        'revenue_manager',
        'sales_manager',
        'sales_executive'
    ) DEFAULT 'cashier'");
    
    $db->query("UPDATE users SET role = 'super_admin' WHERE username = 'superadmin'");
    echo "<p><strong>Role:</strong> super_admin</p>";
} catch (Exception $e) {
    // Fallback to admin if ENUM modification fails
    $db->query("UPDATE users SET role = 'admin' WHERE username = 'superadmin'");
    echo "<p><strong>Role:</strong> admin (super_admin not available in schema)</p>";
}

echo "<p><strong>Username:</strong> superadmin</p>";
echo "<p><strong>Password:</strong> Thepurpose@2025</p>";
echo "<p><strong>Status:</strong> Active</p>";
echo "<br><p style='color:red;'><strong>IMPORTANT: Delete this file (reset-superadmin.php) after use!</strong></p>";
echo "<br><a href='login.php'>Go to Login</a>";
