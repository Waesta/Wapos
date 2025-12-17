<?php
require_once 'config.php';

// Generate correct password hashes
$superadminPassword = 'Thepurpose@2025';
$adminPassword = 'admin';

$superadminHash = password_hash($superadminPassword, PASSWORD_DEFAULT);
$adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);

echo "Superadmin hash: $superadminHash\n";
echo "Admin hash: $adminHash\n\n";

// Update database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Update superadmin
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'superadmin'");
    $stmt->execute([$superadminHash]);
    echo "✓ Superadmin password updated\n";
    
    // Update admin
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$adminHash]);
    echo "✓ Admin password updated\n";
    
    // Verify
    echo "\nVerifying passwords:\n";
    
    $stmt = $pdo->prepare("SELECT username, password FROM users WHERE username IN ('superadmin', 'admin')");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $testPassword = ($user['username'] === 'superadmin') ? $superadminPassword : $adminPassword;
        if (password_verify($testPassword, $user['password'])) {
            echo "✓ {$user['username']}: Password verification SUCCESSFUL\n";
        } else {
            echo "✗ {$user['username']}: Password verification FAILED\n";
        }
    }
    
    echo "\n✓ All passwords updated successfully!\n";
    echo "You can now login at: http://localhost/wapos/login.php\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
