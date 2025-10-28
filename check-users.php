<?php
echo "<h1>WAPOS User Check</h1>";

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
    
    // Check users
    $stmt = $pdo->query("SELECT id, username, role, is_active FROM users LIMIT 10");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<h2>✅ Found " . count($users) . " users:</h2>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Active</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h2>Login Instructions:</h2>";
        echo "<p>You can log in with any of the users above.</p>";
        echo "<p>If you don't know the password, you can:</p>";
        echo "<ol>";
        echo "<li>Try common passwords like: <code>admin</code>, <code>password</code>, <code>123456</code></li>";
        echo "<li>Or reset the admin password by running the password reset script</li>";
        echo "</ol>";
        
    } else {
        echo "<h2>❌ No users found in database</h2>";
        echo "<p>The database is installed but has no users. You need to create a user first.</p>";
    }
    
    echo '<hr><p><a href="login.php">Go to Login Page</a></p>';
    echo '<p><a href="install.php">Go to Installation</a></p>';
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
