<?php
require_once 'includes/bootstrap.php';

$db = Database::getInstance();
$user = $db->fetchOne("SELECT username, role FROM users WHERE username = 'superadmin'");

echo "<h3>Database Role for 'superadmin':</h3>";
echo "<pre>";
print_r($user);
echo "</pre>";

echo "<h3>Current Session:</h3>";
if ($auth->isLoggedIn()) {
    $rawRole = $auth->getUser()['role'] ?? 'N/A';
    $normalizedRole = $auth->getRole();
    
    echo "Logged in as: <strong>" . $auth->getUsername() . "</strong><br>";
    echo "Raw role from DB: <strong>" . $rawRole . "</strong><br>";
    echo "Normalized role: <strong>" . $normalizedRole . "</strong><br>";
    
    $privilegedRoles = ['super_admin', 'developer'];
    $isPrivileged = in_array($normalizedRole, $privilegedRoles, true);
    echo "Is Privileged: <strong>" . ($isPrivileged ? 'YES' : 'NO') . "</strong><br>";
    
    echo "<br><h4>Payment Gateways Access Check:</h4>";
    echo "Required role: super_admin<br>";
    echo "Your role: " . $normalizedRole . "<br>";
    echo "Access: <strong>" . ($normalizedRole === 'super_admin' ? 'GRANTED' : 'DENIED') . "</strong>";
} else {
    echo "Not logged in - <a href='login.php'>Login here</a>";
}
