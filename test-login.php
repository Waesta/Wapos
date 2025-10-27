<?php
// Quick login diagnostics
require_once 'config.php';

echo "<h2>WAPOS Login Diagnostics</h2>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Check if users table exists
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Users table exists</p>";
        
        // Check for users
        $users = $pdo->query("SELECT id, username, full_name, role, is_active FROM users")->fetchAll();
        
        if (count($users) > 0) {
            echo "<p style='color: green;'>✅ Found " . count($users) . " user(s):</p>";
            echo "<table border='1' cellpadding='10'>";
            echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th><th>Action</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . $user['id'] . "</td>";
                echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "<td><a href='?reset=" . $user['id'] . "' style='color: blue;'>Reset Password</a></td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Handle password reset
            if (isset($_GET['reset'])) {
                $userId = $_GET['reset'];
                $newPassword = 'admin123';
                $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                
                echo "<p style='color: green; background: #d4edda; padding: 10px; margin-top: 20px;'>";
                echo "✅ <strong>Password reset successful!</strong><br>";
                echo "Username: <strong>" . htmlspecialchars($users[$userId-1]['username'] ?? 'user') . "</strong><br>";
                echo "New Password: <strong>admin123</strong><br><br>";
                echo "<a href='login.php' style='color: blue; font-size: 18px;'>➜ Go to Login Page</a>";
                echo "</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ No users found! You need to install the database.</p>";
            echo "<p><a href='install.php' style='background: blue; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Install Database Now</a></p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Users table doesn't exist!</p>";
        echo "<p>You need to install the database first.</p>";
        echo "<p><a href='install.php' style='background: blue; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Install Database Now</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p><strong>The database 'wapos' doesn't exist yet!</strong></p>";
        echo "<p><a href='install.php' style='background: blue; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Install Database Now</a></p>";
    }
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 50px auto;
        padding: 20px;
    }
    table {
        width: 100%;
        margin: 20px 0;
    }
    th {
        background: #f5f5f5;
    }
</style>
