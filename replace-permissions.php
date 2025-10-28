<?php
/**
 * Replace Permissions File
 * Replaces the old permissions.php with the clean version
 */

echo "<h1>🔄 Replace Permissions File</h1>";

try {
    $sourceFile = 'permissions-new.php';
    $targetFile = 'permissions.php';
    $backupFile = 'permissions-backup-' . date('Y-m-d-H-i-s') . '.php';
    
    // Check if source exists
    if (!file_exists($sourceFile)) {
        throw new Exception("Source file $sourceFile not found");
    }
    
    // Backup existing file if it exists
    if (file_exists($targetFile)) {
        if (copy($targetFile, $backupFile)) {
            echo "<p style='color: blue;'>✅ Backed up existing file to: $backupFile</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not create backup, proceeding anyway</p>";
        }
    }
    
    // Read source content
    $content = file_get_contents($sourceFile);
    if ($content === false) {
        throw new Exception("Could not read source file");
    }
    
    // Write to target
    if (file_put_contents($targetFile, $content) !== false) {
        echo "<p style='color: green;'>✅ Successfully replaced $targetFile</p>";
        echo "<p style='color: green;'>✅ File size: " . strlen($content) . " bytes</p>";
        
        // Verify the replacement worked
        if (file_exists($targetFile)) {
            $newContent = file_get_contents($targetFile);
            if (strpos($newContent, 'systemManager') === false && strpos($newContent, 'getPermissionGroups') === false) {
                echo "<p style='color: green;'>✅ Verified: No SystemManager references found</p>";
            } else {
                echo "<p style='color: red;'>❌ Warning: SystemManager references still found</p>";
            }
        }
        
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🎉 Permissions File Replaced Successfully!</h3>";
        echo "<p>The permissions.php file has been replaced with a clean version that has:</p>";
        echo "<ul>";
        echo "<li>✅ No SystemManager dependencies</li>";
        echo "<li>✅ Direct database queries only</li>";
        echo "<li>✅ Proper error handling</li>";
        echo "<li>✅ Working permission templates button</li>";
        echo "<li>✅ Clean, modern interface</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        throw new Exception("Could not write to target file");
    }
    
    echo "<h2>🚀 Test the Fixed File</h2>";
    echo "<p>";
    echo "<a href='permissions.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Permissions Page</a>";
    echo "<a href='create-permission-templates.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Permission Templates</a>";
    echo "<a href='system-health.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>System Health</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Error Replacing File</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<h2>Manual Steps</h2>";
    echo "<p>If the automatic replacement failed, you can manually:</p>";
    echo "<ol>";
    echo "<li>Delete the current permissions.php file</li>";
    echo "<li>Rename permissions-new.php to permissions.php</li>";
    echo "<li>Or use the test version: <a href='test-permissions-fixed.php'>test-permissions-fixed.php</a></li>";
    echo "</ol>";
}
?>
