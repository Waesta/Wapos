<?php
/**
 * Test User Creation - Debug Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Test User Creation</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;}";
echo "</style></head><body>";

echo "<h1>🧪 Test User Creation</h1>";

try {
    require_once 'includes/bootstrap.php';
    
    echo "<div class='success'>✅ Bootstrap loaded successfully</div>";
    
    $db = Database::getInstance();
    echo "<div class='success'>✅ Database connection successful</div>";
    
    // Test data
    $testData = [
        'username' => 'test_accountant_' . time(),
        'full_name' => 'Test Accountant',
        'email' => 'test@accountant.com',
        'phone' => '1234567890',
        'role' => 'accountant',
        'location_id' => null,
        'is_active' => 1,
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo "<h2>Test Data:</h2>";
    echo "<pre>" . print_r($testData, true) . "</pre>";
    
    echo "<h2>Attempting to insert user...</h2>";
    
    $result = $db->insert('users', $testData);
    
    if ($result) {
        echo "<div class='success'>";
        echo "<h3>✅ SUCCESS!</h3>";
        echo "<p>User created with ID: <strong>{$result}</strong></p>";
        echo "<p>Username: <strong>{$testData['username']}</strong></p>";
        echo "<p>Password: <strong>password123</strong></p>";
        echo "</div>";
        
        // Verify the user was created
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$result]);
        echo "<h3>Verification:</h3>";
        echo "<pre>" . print_r($user, true) . "</pre>";
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ FAILED</h3>";
        echo "<p>Insert returned false but no exception was thrown.</p>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code:</strong> " . $e->getCode() . "</p>";
    echo "<h4>Stack Trace:</h4>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    
    echo "<h3>💡 Common Solutions:</h3>";
    echo "<ul>";
    echo "<li><strong>Duplicate username:</strong> Username already exists in database</li>";
    echo "<li><strong>Missing column:</strong> Database table missing required field</li>";
    echo "<li><strong>Data too long:</strong> Value exceeds column length</li>";
    echo "<li><strong>Invalid default:</strong> Column has invalid default value</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ General Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>📋 Database Table Structure</h3>";

try {
    $db = Database::getInstance();
    $columns = $db->fetchAll("DESCRIBE users");
    
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
    echo "<tr style='background:#007bff;color:white;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='error'>Could not fetch table structure: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><a href='users.php' style='display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;'>Go to Users Page</a></p>";

echo "</body></html>";
?>
