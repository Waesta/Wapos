<?php
echo "<h1>WAPOS Connection Test</h1>";

// Test 1: PHP is working
echo "<h2>✅ PHP is working!</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test 2: Database connection
echo "<h2>Testing Database Connection...</h2>";

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
    
    echo "<p>✅ Database connection successful!</p>";
    
    // Test 3: Check if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<p>✅ Database has " . count($tables) . " tables</p>";
        echo "<details><summary>Show tables</summary><ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul></details>";
    } else {
        echo "<p>❌ Database exists but has no tables. Run the installation first.</p>";
        echo '<p><a href="install.php">Click here to install WAPOS</a></p>';
    }
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p>❌ Database 'wapos' does not exist.</p>";
        echo '<p><a href="install.php">Click here to install WAPOS</a></p>';
    } else {
        echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
    }
}

// Test 4: Check if includes work
echo "<h2>Testing Include Files...</h2>";

if (file_exists('includes/bootstrap.php')) {
    echo "<p>✅ bootstrap.php exists</p>";
    
    try {
        require_once 'includes/bootstrap.php';
        echo "<p>✅ bootstrap.php loaded successfully</p>";
        
        if (class_exists('Database')) {
            echo "<p>✅ Database class loaded</p>";
        } else {
            echo "<p>❌ Database class not found</p>";
        }
        
        if (class_exists('Auth')) {
            echo "<p>✅ Auth class loaded</p>";
        } else {
            echo "<p>❌ Auth class not found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error loading bootstrap.php: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ bootstrap.php not found</p>";
}

echo "<hr>";
echo '<p><a href="index.php">Try accessing index.php again</a></p>';
echo '<p><a href="install.php">Go to installation</a></p>';
?>
