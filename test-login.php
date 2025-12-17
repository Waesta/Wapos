<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting test...<br>";

try {
    echo "Loading bootstrap...<br>";
    require_once 'includes/bootstrap.php';
    echo "Bootstrap loaded successfully<br>";
    
    echo "Loading RateLimiter...<br>";
    require_once 'includes/RateLimiter.php';
    echo "RateLimiter loaded successfully<br>";
    
    echo "Testing auth...<br>";
    if ($auth->isLoggedIn()) {
        echo "User is logged in<br>";
    } else {
        echo "User is not logged in<br>";
    }
    
    echo "<br>All tests passed!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
