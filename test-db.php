<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing database connection...<br><br>";

try {
    require_once 'config.php';
    echo "✓ Config loaded<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br><br>";
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Database connection successful!<br><br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Users table accessible. Found " . $result['count'] . " users<br>";
    
} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}
