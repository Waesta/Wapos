<?php
/**
 * Add location tracking columns to riders table
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
        <title>Add Rider Location Columns</title>
        <style>
            body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
            .success { color: #0f0; }
            .error { color: #f00; }
        </style>
    </head>
    <body>
    <h1>Adding Rider Location Tracking Columns</h1>
    <pre>";
    
    // Check if riders table exists
    $result = $pdo->query("SHOW TABLES LIKE 'riders'")->fetch();
    if (!$result) {
        echo "<span class='error'>✗ riders table doesn't exist!</span>\n";
        echo "This table needs to be created first.\n";
    } else {
        echo "✓ riders table exists\n\n";
        
        // Check and add current_latitude
        $result = $pdo->query("SHOW COLUMNS FROM riders LIKE 'current_latitude'")->fetch();
        if (!$result) {
            echo "Adding current_latitude column...\n";
            $pdo->exec("ALTER TABLE riders ADD COLUMN current_latitude DECIMAL(10, 8) DEFAULT NULL");
            echo "✓ Added current_latitude\n";
        } else {
            echo "✓ current_latitude already exists\n";
        }
        
        // Check and add current_longitude
        $result = $pdo->query("SHOW COLUMNS FROM riders LIKE 'current_longitude'")->fetch();
        if (!$result) {
            echo "Adding current_longitude column...\n";
            $pdo->exec("ALTER TABLE riders ADD COLUMN current_longitude DECIMAL(11, 8) DEFAULT NULL");
            echo "✓ Added current_longitude\n";
        } else {
            echo "✓ current_longitude already exists\n";
        }
        
        // Check and add last_location_update
        $result = $pdo->query("SHOW COLUMNS FROM riders LIKE 'last_location_update'")->fetch();
        if (!$result) {
            echo "Adding last_location_update column...\n";
            $pdo->exec("ALTER TABLE riders ADD COLUMN last_location_update TIMESTAMP NULL DEFAULT NULL");
            echo "✓ Added last_location_update\n";
        } else {
            echo "✓ last_location_update already exists\n";
        }
        
        echo "\n========================================\n";
        echo "✓ Rider location tracking ready!\n";
        echo "========================================\n\n";
        
        // Show current structure
        echo "Current riders table structure:\n";
        $result = $pdo->query("SHOW COLUMNS FROM riders");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  ✓ " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
    
    echo "\n</pre>
    <h2 style='color: #0f0;'>✓ SUCCESS! Location tracking enabled!</h2>
    <p><a href='/wapos/enhanced-delivery-tracking.php' style='color: #0ff;'>→ Test Delivery Tracking Page</a></p>
    <p><a href='/wapos/index.php' style='color: #fff;'>→ Back to Dashboard</a></p>
    </body>
    </html>";
    
} catch (PDOException $e) {
    echo "<span class='error'>ERROR: " . $e->getMessage() . "</span>\n";
}
?>
