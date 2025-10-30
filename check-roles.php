<?php
/**
 * Check Current Role ENUM Values
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Check Roles</title>";
echo "<style>body{font-family:Arial;max-width:900px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".warning{background:#fff3cd;color:#856404;padding:15px;border-radius:5px;margin:10px 0;}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "th,td{padding:12px;text-align:left;border:1px solid #ddd;}";
echo "th{background:#007bff;color:white;}";
echo ".available{background:#d4edda;}";
echo ".missing{background:#f8d7da;}";
echo "</style></head><body>";

echo "<h1>🔍 Current Role Configuration</h1>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database connected</div>";
    
    // Get current ENUM definition
    echo "<h2>📋 Current Role ENUM Definition:</h2>";
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    
    // Parse ENUM values
    $enumString = $result['Type'];
    preg_match("/^enum\(\'(.*)\'\)$/", $enumString, $matches);
    $enumValues = explode("','", $matches[1]);
    
    echo "<div style='background:#f5f5f5;padding:15px;border-radius:5px;'>";
    echo "<strong>Database Column Type:</strong> " . htmlspecialchars($result['Type']) . "<br>";
    echo "<strong>Default Value:</strong> " . htmlspecialchars($result['Default']) . "<br>";
    echo "<strong>Nullable:</strong> " . htmlspecialchars($result['Null']);
    echo "</div>";
    
    // Expected roles for the system
    $expectedRoles = [
        'admin' => 'Full system access - Admin Dashboard',
        'manager' => 'Business operations - Manager Dashboard',
        'accountant' => 'Financial management - Accountant Dashboard',
        'cashier' => 'POS operations - Cashier Dashboard',
        'waiter' => 'Restaurant service - Waiter Dashboard',
        'inventory_manager' => 'Inventory control',
        'rider' => 'Delivery operations'
    ];
    
    echo "<h2>📊 Role Availability Check:</h2>";
    echo "<table>";
    echo "<tr><th>Role</th><th>Purpose</th><th>Status</th></tr>";
    
    $missingRoles = [];
    foreach ($expectedRoles as $role => $purpose) {
        $isAvailable = in_array($role, $enumValues);
        $statusClass = $isAvailable ? 'available' : 'missing';
        $statusText = $isAvailable ? '✅ Available' : '❌ Missing';
        
        if (!$isAvailable) {
            $missingRoles[] = $role;
        }
        
        echo "<tr class='{$statusClass}'>";
        echo "<td><strong>" . htmlspecialchars($role) . "</strong></td>";
        echo "<td>" . htmlspecialchars($purpose) . "</td>";
        echo "<td><strong>{$statusText}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show existing users and their roles
    echo "<h2>👥 Existing Users in Database:</h2>";
    $users = $pdo->query("SELECT id, username, full_name, role, is_active FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td><span style='background:#007bff;color:white;padding:4px 8px;border-radius:3px;'>" . htmlspecialchars($user['role']) . "</span></td>";
            echo "<td>" . ($user['is_active'] ? '✅ Active' : '❌ Inactive') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p><strong>Total Users:</strong> " . count($users) . "</p>";
    } else {
        echo "<div class='warning'>No users found in database</div>";
    }
    
    // Summary
    if (count($missingRoles) > 0) {
        echo "<div class='error'>";
        echo "<h3>⚠️ Missing Roles Detected!</h3>";
        echo "<p>The following roles are missing from the database ENUM:</p>";
        echo "<ul>";
        foreach ($missingRoles as $role) {
            echo "<li><strong>" . htmlspecialchars($role) . "</strong> - " . htmlspecialchars($expectedRoles[$role]) . "</li>";
        }
        echo "</ul>";
        echo "<p><strong>This is why you can't add users with these roles!</strong></p>";
        echo "</div>";
        
        echo "<hr>";
        echo "<h3>🔧 Fix This Issue:</h3>";
        echo "<p><a href='fix-role-enum.php' style='display:inline-block;padding:15px 30px;background:#dc3545;color:white;text-decoration:none;border-radius:5px;font-size:18px;'>Run Fix Script Now</a></p>";
    } else {
        echo "<div class='success'>";
        echo "<h3>✅ All Roles Available!</h3>";
        echo "<p>All expected roles are present in the database.</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<p><a href='users.php' style='display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;'>Go to Users Page</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
