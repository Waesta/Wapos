<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = Database::getInstance();
$response = [
    'timestamp' => date('c'),
    'super_admin_updates' => 0,
    'developer_updates' => 0,
    'errors' => []
];

try {
    $superAdminStmt = $pdo->query(
        "UPDATE users SET role = 'super_admin' WHERE username = 'superadmin' OR role IS NULL OR role = ''"
    );
    $response['super_admin_updates'] = $superAdminStmt ? $superAdminStmt->rowCount() : 0;

    $developerStmt = $pdo->query(
        "UPDATE users SET role = 'developer' WHERE username = 'developer' OR role = 'dev'"
    );
    $response['developer_updates'] = $developerStmt ? $developerStmt->rowCount() : 0;

    $response['sample_users'] = $pdo->fetchAll(
        "SELECT id, username, role FROM users WHERE username IN ('superadmin', 'developer')"
    );
} catch (Throwable $e) {
    $response['errors'][] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
