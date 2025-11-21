<?php
require_once __DIR__ . '/includes/bootstrap.php';
$auth->requireLogin();

header('Content-Type: text/plain; charset=utf-8');

$user = $auth->getUser();
$role = $auth->getRole();
$isPrivileged = in_array($role, ['super_admin', 'developer'], true);

$systemManager = SystemManager::getInstance();
$modules = $systemManager->getSystemModules(true);

$output = [
    'username' => $user['username'] ?? null,
    'stored_role' => $user['role'] ?? null,
    'normalized_role' => $role,
    'is_privileged' => $isPrivileged,
    'current_page' => basename($_SERVER['PHP_SELF'] ?? ''),
    'nav_groups_total' => 9,
    'module_statuses' => array_map(function ($module) {
        return [
            'module_key' => $module['module_key'] ?? $module['name'] ?? 'unknown',
            'is_enabled' => (int)($module['is_enabled'] ?? $module['is_active'] ?? 0),
        ];
    }, $modules),
];

echo json_encode($output, JSON_PRETTY_PRINT);
