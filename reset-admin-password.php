<?php
/**
 * Quick Admin Password Reset
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔑 Quick Admin Password Reset</h1>";

try {
    // Direct database connection
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hash new password
    $newPassword = 'admin';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hashedPassword]);
    
    if ($stmt->rowCount() > 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Password Reset Successful!</h3>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin</p>";
        echo "</div>";
        
        echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
        
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>❌ No admin user found</h3>";
        echo "<p>Creating new admin user...</p>";
        echo "</div>";
        
        // Create admin user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'admin', 1)");
        $result = $stmt->execute(['admin', $hashedPassword]);
        
        if ($result) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ Admin User Created!</h3>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Password:</strong> admin</p>";
            echo "</div>";
        }
    }
    
    // Also reset developer user
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'developer'");
    $stmt->execute([$hashedPassword]);
    
    echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📝 Available Login Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>admin/admin</strong> - Full administrative access</li>";
    echo "<li><strong>developer/admin</strong> - Development access</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🎯 Quick Test</h2>";
echo "<form method='post' action='login.php' style='background: #f8f9fa; padding: 20px; border-radius: 5px;'>";
echo "<h4>Test Login (admin/admin):</h4>";
echo "<input type='hidden' name='username' value='admin'>";
echo "<input type='hidden' name='password' value='admin'>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Auto-Login as Admin</button>";
echo "</form>";

echo "<p style='margin-top: 20px;'><a href='login.php'>Manual Login Page</a></p>";
?>
