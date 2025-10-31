<?php
/**
 * Run Database Migration
 * Execute this file to apply database changes
 */

require_once 'includes/bootstrap.php';

// Only allow admin access
if (!isset($auth) || !$auth->isLoggedIn() || $auth->getUser()['role'] !== 'admin') {
    die("Error: Admin access required");
}

$db = Database::getInstance();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Run Migration - WAPOS</title>
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
    <h1>Database Migration</h1>
    <div class='log'>";

try {
    // Read migration file
    $migrationFile = __DIR__ . '/database/migrations/001_add_uniqueness_constraints.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    echo "<div class='success'>✓ Found migration file</div>";
    
    $sql = file_get_contents($migrationFile);
    
    // Split by semicolons but handle prepared statements
    $statements = [];
    $current = '';
    $inPrepare = false;
    
    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        
        $current .= $line . "\n";
        
        // Track PREPARE/EXECUTE blocks
        if (stripos($line, 'PREPARE') !== false) {
            $inPrepare = true;
        }
        
        if (stripos($line, 'DEALLOCATE PREPARE') !== false) {
            $inPrepare = false;
            $statements[] = $current;
            $current = '';
            continue;
        }
        
        // Regular statement end
        if (!$inPrepare && substr($line, -1) === ';') {
            $statements[] = $current;
            $current = '';
        }
    }
    
    echo "<div class='success'>✓ Parsed " . count($statements) . " SQL statements</div>";
    echo "<div class='warning'>⚠ Executing migration...</div>";
    
    $executed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $db->getConnection()->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<div class='success'>✓ Created/verified table: $tableName</div>";
            } elseif (stripos($statement, 'ALTER TABLE') !== false) {
                preg_match('/ALTER TABLE `?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<div class='success'>✓ Modified table: $tableName</div>";
            }
            
        } catch (PDOException $e) {
            // Check if it's a "already exists" error (which is OK)
            if (stripos($e->getMessage(), 'already exists') !== false || 
                stripos($e->getMessage(), 'Duplicate') !== false) {
                $skipped++;
            } else {
                $errors++;
                echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    echo "<br>";
    echo "<div class='success'>========================================</div>";
    echo "<div class='success'>✓ Migration completed!</div>";
    echo "<div class='success'>  - Executed: $executed statements</div>";
    echo "<div class='warning'>  - Skipped: $skipped (already exist)</div>";
    if ($errors > 0) {
        echo "<div class='error'>  - Errors: $errors</div>";
    }
    echo "<div class='success'>========================================</div>";
    
    // Verify critical tables
    echo "<br><div class='warning'>Verifying tables...</div>";
    
    $criticalTables = ['suppliers', 'permission_modules', 'migrations', 'accounts', 'journal_entries'];
    foreach ($criticalTables as $table) {
        $result = $db->getConnection()->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            echo "<div class='success'>✓ Table exists: $table</div>";
        } else {
            echo "<div class='error'>✗ Table missing: $table</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ FATAL ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'>Stack trace: " . htmlspecialchars($e->getTraceAsString()) . "</div>";
}

echo "</div>
    <div class='mt-4'>
        <a href='system-health.php' class='btn btn-primary'>Check System Health</a>
        <a href='index.php' class='btn btn-secondary'>Back to Dashboard</a>
    </div>
</div>
</body>
</html>";
?>
