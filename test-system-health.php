<?php
echo "<h1>Testing System Health Fix</h1>";

try {
    // Test if the system-health.php file loads without errors
    ob_start();
    include 'system-health.php';
    $output = ob_get_clean();
    
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "✅ system-health.php loaded successfully without errors!";
    echo "</div>";
    
    echo "<h2>Preview of system-health.php output:</h2>";
    echo "<div style='border: 1px solid #ddd; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars(substr($output, 0, 1000)) . (strlen($output) > 1000 ? '...' : '');
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ Error loading system-health.php: " . $e->getMessage();
    echo "</div>";
} catch (Error $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ Fatal error in system-health.php: " . $e->getMessage();
    echo "</div>";
}

echo '<hr><p><a href="system-health.php">Go to System Health Page</a></p>';
?>
