<?php
echo "<h1>WAPOS Password Reset</h1>";

if ($_POST['action'] ?? '' === 'reset') {
    $username = $_POST['username'] ?? '';
    $newPassword = $_POST['password'] ?? '';
    
    if ($username && $newPassword) {
        try {
            $pdo = new PDO(
                'mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1
            ]);
            
            // Update the password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            $result = $stmt->execute([$hashedPassword, $username]);
            
            if ($stmt->rowCount() > 0) {
                echo "<div style='background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
                echo "✅ Password updated successfully for user: <strong>$username</strong>";
                echo "</div>";
                echo "<p>You can now log in with:</p>";
                echo "<ul><li><strong>Username:</strong> $username</li><li><strong>Password:</strong> $newPassword</li></ul>";
                echo '<p><a href="login.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login Page</a></p>';
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
                echo "❌ User not found: $username";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ Error: " . $e->getMessage();
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ Please fill in both username and password";
        echo "</div>";
    }
}
?>

<form method="POST" style="max-width: 400px; margin: 20px 0;">
    <input type="hidden" name="action" value="reset">
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Username:</label>
        <select name="username" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Select user...</option>
            <option value="admin">admin</option>
            <option value="developer">developer</option>
        </select>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: bold;">New Password:</label>
        <input type="text" name="password" required 
               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
               placeholder="Enter new password">
    </div>
    
    <button type="submit" 
            style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%;">
        Reset Password
    </button>
</form>

<div style="background: #e2e3e5; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3>Quick Reset Options:</h3>
    <p>Click one of these to quickly reset a password:</p>
    
    <form method="POST" style="display: inline-block; margin-right: 10px;">
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="username" value="admin">
        <input type="hidden" name="password" value="admin">
        <button type="submit" style="background: #007bff; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
            Set admin/admin
        </button>
    </form>
    
    <form method="POST" style="display: inline-block;">
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="username" value="admin">
        <input type="hidden" name="password" value="password">
        <button type="submit" style="background: #6c757d; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
            Set admin/password
        </button>
    </form>
</div>

<hr>
<p><a href="login.php">Go to Login Page</a> | <a href="test-connection.php">Test Connection</a></p>
