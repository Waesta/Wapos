<?php
/**
 * Add affects_inventory column to void_reason_codes
 */

$host = 'localhost';
$dbname = 'wapos';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Add affects_inventory Column</title>
        <style>
            body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
            .success { color: #0f0; }
            .error { color: #f00; }
        </style>
    </head>
    <body>
    <h1>Adding affects_inventory Column</h1>
    <pre>";
    
    // Check if column exists
    $result = $pdo->query("SHOW COLUMNS FROM void_reason_codes LIKE 'affects_inventory'")->fetch();
    
    if (!$result) {
        echo "Adding affects_inventory column...\n";
        $pdo->exec("ALTER TABLE void_reason_codes ADD COLUMN affects_inventory TINYINT(1) DEFAULT 0 AFTER requires_manager_approval");
        echo "✓ Column added successfully\n\n";
        
        // Update specific reasons that should affect inventory
        echo "Updating inventory-affecting reasons...\n";
        $pdo->exec("UPDATE void_reason_codes SET affects_inventory = 1 WHERE code IN ('OUT_OF_STOCK', 'KITCHEN_ERROR', 'WRONG_ORDER')");
        echo "✓ Updated 3 reasons to affect inventory\n";
    } else {
        echo "✓ Column already exists\n";
    }
    
    echo "\n========================================\n";
    echo "✓ affects_inventory column ready!\n";
    echo "========================================\n\n";
    
    // Show current structure
    echo "Current void_reason_codes structure:\n";
    $result = $pdo->query("SHOW COLUMNS FROM void_reason_codes");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  ✓ " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    echo "\n</pre>
    <h2 style='color: #0f0;'>✓ SUCCESS! No more warnings!</h2>
    <p><a href='/wapos/void-settings.php' style='color: #0ff;'>→ Test Void Settings Page (No Warnings)</a></p>
    <p><a href='/wapos/index.php' style='color: #fff;'>→ Back to Dashboard</a></p>
    </body>
    </html>";
    
} catch (PDOException $e) {
    echo "<span class='error'>ERROR: " . $e->getMessage() . "</span>\n";
}
?>
