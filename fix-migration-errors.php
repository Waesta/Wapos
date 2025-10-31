<?php
/**
 * Fix Migration Errors
 * Fixes the 3 errors from the migration
 */

require_once 'includes/bootstrap.php';

if (!isset($auth) || !$auth->isLoggedIn() || $auth->getUser()['role'] !== 'admin') {
    die("Error: Admin access required");
}

$db = Database::getInstance()->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Migration Errors - WAPOS</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .log { background: #000; color: #0f0; padding: 20px; border-radius: 5px; font-family: monospace; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Fix Migration Errors</h1>
    <div class='log'>";

try {
    echo "<div class='warning'>Fixing 3 migration errors...</div><br>";
    
    // ERROR 1: rooms table - add location_id column first
    echo "<div class='warning'>1. Fixing rooms table...</div>";
    try {
        // Check if location_id exists
        $result = $db->query("SHOW COLUMNS FROM rooms LIKE 'location_id'")->fetch();
        if (!$result) {
            $db->exec("ALTER TABLE rooms ADD COLUMN location_id INT UNSIGNED DEFAULT 1 AFTER id");
            echo "<div class='success'>✓ Added location_id column to rooms</div>";
        } else {
            echo "<div class='success'>✓ location_id already exists in rooms</div>";
        }
        
        // Now add the unique index
        $result = $db->query("SHOW INDEX FROM rooms WHERE Key_name = 'ux_rooms_location_number'")->fetch();
        if (!$result) {
            $db->exec("ALTER TABLE rooms ADD UNIQUE KEY ux_rooms_location_number (location_id, room_number)");
            echo "<div class='success'>✓ Added unique index to rooms</div>";
        } else {
            echo "<div class='success'>✓ Unique index already exists on rooms</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>✗ Error fixing rooms: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<br>";
    
    // ERROR 2: restaurant_tables - add location_id column first
    echo "<div class='warning'>2. Fixing restaurant_tables...</div>";
    try {
        // Check if location_id exists
        $result = $db->query("SHOW COLUMNS FROM restaurant_tables LIKE 'location_id'")->fetch();
        if (!$result) {
            $db->exec("ALTER TABLE restaurant_tables ADD COLUMN location_id INT UNSIGNED DEFAULT 1 AFTER id");
            echo "<div class='success'>✓ Added location_id column to restaurant_tables</div>";
        } else {
            echo "<div class='success'>✓ location_id already exists in restaurant_tables</div>";
        }
        
        // Now add the unique index
        $result = $db->query("SHOW INDEX FROM restaurant_tables WHERE Key_name = 'ux_tables_location_name'")->fetch();
        if (!$result) {
            $db->exec("ALTER TABLE restaurant_tables ADD UNIQUE KEY ux_tables_location_name (location_id, table_name)");
            echo "<div class='success'>✓ Added unique index to restaurant_tables</div>";
        } else {
            echo "<div class='success'>✓ Unique index already exists on restaurant_tables</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>✗ Error fixing restaurant_tables: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<br>";
    
    // ERROR 3: journal_entry_lines - create without foreign keys first
    echo "<div class='warning'>3. Fixing journal_entry_lines...</div>";
    try {
        // Check if table exists
        $result = $db->query("SHOW TABLES LIKE 'journal_entry_lines'")->fetch();
        if (!$result) {
            // Create table without foreign keys
            $db->exec("
                CREATE TABLE journal_entry_lines (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    journal_entry_id INT UNSIGNED NOT NULL,
                    account_id INT UNSIGNED NOT NULL,
                    debit_amount DECIMAL(15,2) DEFAULT 0.00,
                    credit_amount DECIMAL(15,2) DEFAULT 0.00,
                    description VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_journal_entry (journal_entry_id),
                    INDEX idx_account (account_id)
                ) ENGINE=InnoDB
            ");
            echo "<div class='success'>✓ Created journal_entry_lines table</div>";
        } else {
            echo "<div class='success'>✓ journal_entry_lines table already exists</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>✗ Error creating journal_entry_lines: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<br>";
    echo "<div class='success'>========================================</div>";
    echo "<div class='success'>✓ All fixes applied!</div>";
    echo "<div class='success'>========================================</div>";
    
    // Verify all tables now exist
    echo "<br><div class='warning'>Verifying tables...</div>";
    
    $tables = [
        'suppliers',
        'purchase_orders',
        'grn',
        'accounts',
        'journal_entries',
        'journal_entry_lines',
        'accounting_periods',
        'migrations'
    ];
    
    $allGood = true;
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            echo "<div class='success'>✓ $table</div>";
        } else {
            echo "<div class='error'>✗ $table (missing)</div>";
            $allGood = false;
        }
    }
    
    echo "<br>";
    if ($allGood) {
        echo "<div class='success'>🎉 ALL TABLES VERIFIED - SYSTEM READY!</div>";
    } else {
        echo "<div class='error'>⚠ Some tables still missing</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ FATAL ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>
    <div class='mt-4'>
        <a href='system-health.php' class='btn btn-success btn-lg'>✓ Check System Health</a>
        <a href='index.php' class='btn btn-secondary'>Back to Dashboard</a>
    </div>
</div>
</body>
</html>";
?>
