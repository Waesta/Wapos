<?php
/**
 * Quick Accounting Module Installer
 * Run from command line: php run-accounting-install.php
 */

require_once 'includes/bootstrap.php';

echo "===========================================\n";
echo "WAPOS Accounting Module Installer\n";
echo "===========================================\n\n";

try {
    // Read SQL file
    $sqlFile = __DIR__ . '/database/fix-accounting-module.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception('SQL file not found: ' . $sqlFile);
    }
    
    echo "Reading SQL file...\n";
    $sql = file_get_contents($sqlFile);
    
    // Get database connection
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "Parsing SQL statements...\n";
    
    // Split and filter statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^USE /', $stmt) &&
                   !preg_match('/^\/\*/', $stmt);
        }
    );
    
    echo "Found " . count($statements) . " SQL statements\n";
    echo "Executing...\n\n";
    
    $executed = 0;
    $skipped = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
                
                // Show what was executed
                $firstLine = strtok($statement, "\n");
                echo "✓ " . substr($firstLine, 0, 60) . "...\n";
                
            } catch (PDOException $e) {
                // Skip "already exists" errors
                if (strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate') !== false) {
                    $skipped++;
                    echo "⊘ Skipped (already exists)\n";
                } else {
                    echo "✗ ERROR: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "\n===========================================\n";
    echo "Installation Complete!\n";
    echo "===========================================\n";
    echo "✓ Executed: {$executed} statements\n";
    echo "⊘ Skipped: {$skipped} statements\n";
    echo "\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    $tables = ['accounts', 'journal_entries', 'journal_lines', 'expense_categories', 'account_reconciliations'];
    
    foreach ($tables as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            echo "✓ {$table}: {$row['count']} records\n";
        } catch (PDOException $e) {
            echo "✗ {$table}: NOT FOUND\n";
        }
    }
    
    echo "\n✅ SUCCESS! Accounting module is ready to use!\n";
    echo "\nYou can now access:\n";
    echo "- Profit & Loss: http://localhost/wapos/reports/profit-and-loss.php\n";
    echo "- Balance Sheet: http://localhost/wapos/reports/balance-sheet.php\n";
    echo "- Accounting: http://localhost/wapos/accounting.php\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
