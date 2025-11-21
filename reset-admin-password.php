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
    
    // Hash new password for privileged accounts
    $newPassword = 'Thepurposes@2025';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Setting default password for privileged accounts</h4>";
    echo "<p><strong>New Password:</strong> " . htmlspecialchars($newPassword) . "</p>";
    echo "</div>";

    // Update all super_admin accounts
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'super_admin'");
    $stmt->execute([$hashedPassword]);
    $superAdminsUpdated = $stmt->rowCount();

    if ($superAdminsUpdated > 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Super Admin passwords reset</h3>";
        echo "<p>All accounts with the <strong>super_admin</strong> role now use the default password.</p>";
        echo "</div>";
        $primarySuperAdminUsername = null;
    } else {
        $primarySuperAdminUsername = 'superadmin';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$primarySuperAdminUsername]);
        $existingSuperAdminId = $stmt->fetchColumn();

        if ($existingSuperAdminId) {
            $promoteSuperAdmin = $pdo->prepare("UPDATE users SET role = 'super_admin', password = ?, is_active = 1 WHERE id = ?");
            $promoteSuperAdmin->execute([$hashedPassword, $existingSuperAdminId]);
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ Super Admin role assigned</h3>";
            echo "<p>User <strong>" . htmlspecialchars($primarySuperAdminUsername) . "</strong> was promoted to <strong>super_admin</strong> with the default password.</p>";
            echo "</div>";
        } else {
            $createSuperAdmin = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active, created_at) VALUES (?, ?, 'Default Super Admin', 'super_admin', 1, NOW())");
            $createSuperAdmin->execute([$primarySuperAdminUsername, $hashedPassword]);
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ Super Admin user created</h3>";
            echo "<p><strong>Username:</strong> " . htmlspecialchars($primarySuperAdminUsername) . "</p>";
            echo "<p><strong>Password:</strong> " . htmlspecialchars($newPassword) . "</p>";
            echo "</div>";
        }
    }

    // Ensure the developer account exists and reset its password
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'developer' LIMIT 1");
    $stmt->execute();
    $developerExists = (bool) $stmt->fetchColumn();

    if (!$developerExists) {
        $createDeveloper = $pdo->prepare("INSERT INTO users (username, password, role, is_active, created_at) VALUES ('developer', ?, 'developer', 1, NOW())");
        $createDeveloper->execute([$hashedPassword]);
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Developer user created</h3>";
        echo "<p><strong>Username:</strong> developer</p>";
        echo "<p><strong>Password:</strong> " . htmlspecialchars($newPassword) . "</p>";
        echo "</div>";
    } else {
        $updateDeveloper = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'developer'");
        $updateDeveloper->execute([$hashedPassword]);
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Developer password reset</h3>";
        echo "<p><strong>Username:</strong> developer</p>";
        echo "<p><strong>Password:</strong> " . htmlspecialchars($newPassword) . "</p>";
        echo "</div>";
    }
    
    echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📝 Updated Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>All super_admin accounts" . ($primarySuperAdminUsername ? " (including {$primarySuperAdminUsername})" : '') . "</strong> – password: <strong>" . htmlspecialchars($newPassword) . "</strong></li>";
    echo "<li><strong>developer</strong> – password: <strong>" . htmlspecialchars($newPassword) . "</strong></li>";
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
