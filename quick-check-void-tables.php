<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

header('Content-Type: text/plain');
echo "=== CHECKING VOID TABLES ===\n\n";

$tables = ['void_reason_codes', 'void_settings', 'void_transactions'];

foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
    if ($result) {
        $count = $db->query("SELECT COUNT(*) as count FROM $table")->fetch(PDO::FETCH_ASSOC);
        echo "✓ $table EXISTS ({$count['count']} records)\n";
    } else {
        echo "✗ $table MISSING\n";
    }
}

echo "\n=== ACTION NEEDED ===\n";
echo "Visit: http://localhost/wapos/create-all-void-tables.php\n";
echo "This will create all missing void tables.\n";
?>
