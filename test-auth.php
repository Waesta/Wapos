<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Test database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connected<br><br>";
} catch (PDOException $e) {
    die("✗ Database error: " . $e->getMessage());
}

// Test user lookup
$username = 'superadmin';
$password = 'Thepurpose@2025';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✓ User found: " . $user['username'] . "<br>";
    echo "Full name: " . $user['full_name'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "<br>";
    echo "Password hash: " . substr($user['password'], 0, 30) . "...<br><br>";
    
    // Test password verification
    if (password_verify($password, $user['password'])) {
        echo "✓ Password verification SUCCESSFUL!<br>";
        echo "<strong style='color:green;'>Login should work!</strong><br>";
    } else {
        echo "✗ Password verification FAILED!<br>";
        echo "<strong style='color:red;'>Password does not match!</strong><br>";
    }
} else {
    echo "✗ User not found!<br>";
}
