<?php
/**
 * Setup Test Users for Dashboard Testing
 * Creates users for each role with password: password
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Setup Test Users</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".info{background:#d1ecf1;color:#0c5460;padding:15px;border-radius:5px;margin:10px 0;}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd;}";
echo "th{background:#007bff;color:white;}";
echo ".btn{display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin:5px;}";
echo "</style></head><body>";

echo "<h1>🔧 Setup Test Users for Dashboard Testing</h1>";

try {
    // Connect to database
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database connection successful</div>";
    
    // Password hash for "password"
    $passwordHash = password_hash('password', PASSWORD_DEFAULT);
    
    // Test users to create
    $testUsers = [
        ['username' => 'accountant', 'full_name' => 'Test Accountant', 'role' => 'accountant'],
        ['username' => 'cashier', 'full_name' => 'Test Cashier', 'role' => 'cashier'],
        ['username' => 'waiter', 'full_name' => 'Test Waiter', 'role' => 'waiter'],
        ['username' => 'manager', 'full_name' => 'Test Manager', 'role' => 'manager']
    ];
    
    echo "<h2>Creating Test Users...</h2>";
    
    $created = [];
    $skipped = [];
    
    foreach ($testUsers as $user) {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$user['username']]);
        
        if ($stmt->fetch()) {
            $skipped[] = $user;
            echo "<div class='info'>ℹ️ User '{$user['username']}' already exists - skipped</div>";
        } else {
            // Create user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                $user['username'],
                $passwordHash,
                $user['full_name'],
                $user['role']
            ]);
            
            $created[] = $user;
            echo "<div class='success'>✅ Created user: {$user['username']} ({$user['role']})</div>";
        }
    }
    
    // Summary
    echo "<hr>";
    echo "<h2>📋 Summary</h2>";
    echo "<p><strong>Created:</strong> " . count($created) . " users</p>";
    echo "<p><strong>Skipped:</strong> " . count($skipped) . " users (already exist)</p>";
    
    // Login credentials table
    echo "<h2>🔑 Test Login Credentials</h2>";
    echo "<p>Use these credentials to test each dashboard:</p>";
    
    echo "<table>";
    echo "<tr><th>Username</th><th>Password</th><th>Role</th><th>Dashboard</th><th>Action</th></tr>";
    
    $dashboards = [
        'accountant' => 'Accountant Dashboard (Financial)',
        'cashier' => 'Cashier Dashboard (POS)',
        'waiter' => 'Waiter Dashboard (Restaurant)',
        'manager' => 'Manager Dashboard (Operations)'
    ];
    
    foreach ($testUsers as $user) {
        $dashboardUrl = "dashboards/{$user['role']}-dashboard.php";
        echo "<tr>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>password</td>";
        echo "<td><span style='background:#007bff;color:white;padding:4px 8px;border-radius:3px;'>{$user['role']}</span></td>";
        echo "<td>{$dashboards[$user['role']]}</td>";
        echo "<td><a href='login.php' class='btn' style='padding:5px 10px;margin:0;'>Login</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Quick test links
    echo "<h2>🚀 Quick Test Links</h2>";
    echo "<p>Direct links to each dashboard (must be logged in):</p>";
    echo "<div style='display:flex;flex-wrap:wrap;gap:10px;'>";
    echo "<a href='dashboards/accountant-dashboard.php' class='btn'>Accountant Dashboard</a>";
    echo "<a href='dashboards/cashier-dashboard.php' class='btn'>Cashier Dashboard</a>";
    echo "<a href='dashboards/waiter-dashboard.php' class='btn'>Waiter Dashboard</a>";
    echo "<a href='dashboards/manager-dashboard.php' class='btn'>Manager Dashboard</a>";
    echo "<a href='dashboards/admin-dashboard.php' class='btn'>Admin Dashboard</a>";
    echo "</div>";
    
    // Testing instructions
    echo "<hr>";
    echo "<h2>📝 How to Test</h2>";
    echo "<ol>";
    echo "<li><strong>Log out</strong> if you're currently logged in</li>";
    echo "<li><strong>Go to login page:</strong> <a href='login.php'>login.php</a></li>";
    echo "<li><strong>Use credentials above</strong> to log in as different roles</li>";
    echo "<li><strong>You'll be automatically redirected</strong> to the appropriate dashboard</li>";
    echo "<li><strong>Test features</strong> specific to each role</li>";
    echo "</ol>";
    
    echo "<div class='info'>";
    echo "<h3>💡 Tips:</h3>";
    echo "<ul>";
    echo "<li>Each role sees different data and features</li>";
    echo "<li>Cashiers and waiters only see their own sales/orders</li>";
    echo "<li>Accountants see all financial data</li>";
    echo "<li>Managers see business overview</li>";
    echo "<li>Admins see everything</li>";
    echo "</ul>";
    echo "</div>";
    
    // Navigation
    echo "<hr>";
    echo "<div style='text-align:center;margin:30px 0;'>";
    echo "<a href='login.php' class='btn' style='background:#28a745;'>Go to Login Page</a>";
    echo "<a href='index.php' class='btn'>Go to Dashboard</a>";
    echo "<a href='reset-password.php' class='btn' style='background:#6c757d;'>Reset Passwords</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure XAMPP MySQL is running</li>";
    echo "<li>Make sure the 'wapos' database exists</li>";
    echo "<li>Make sure the 'users' table exists</li>";
    echo "<li>Check database credentials in config.php</li>";
    echo "</ul>";
}

echo "</body></html>";
?>
