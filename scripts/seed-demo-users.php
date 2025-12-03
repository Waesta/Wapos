<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    echo "This seeding script must be run from the command line.\n";
    exit(1);
}

$db = Database::getInstance();

$seedUsers = [
    [
        'username' => 'Receptionist',
        'full_name' => 'Front Desk Receptionist',
        'role' => 'frontdesk',
    ],
    [
        'username' => 'Cashier',
        'full_name' => 'Point of Sale Cashier',
        'role' => 'cashier',
    ],
    [
        'username' => 'Technician',
        'full_name' => 'On-site Technician',
        'role' => 'technician',
    ],
    [
        'username' => 'RoomAttendant',
        'full_name' => 'Room Attendant',
        'role' => 'housekeeping_staff',
    ],
    [
        'username' => 'Electrician',
        'full_name' => 'Maintenance Electrician',
        'role' => 'maintenance_staff',
    ],
    [
        'username' => 'Waiter',
        'full_name' => 'Service Waiter/Waitress',
        'role' => 'waiter',
    ],
    [
        'username' => 'Concierge',
        'full_name' => 'Guest Concierge',
        'role' => 'frontdesk',
    ],
    [
        'username' => 'LaundryAttendant',
        'full_name' => 'Laundry Attendant',
        'role' => 'housekeeping_staff',
    ],
    [
        'username' => 'Chef',
        'full_name' => 'Chef / Cook',
        'role' => 'manager',
    ],
    [
        'username' => 'Barista',
        'full_name' => 'Barista / Barman',
        'role' => 'waiter',
    ],
    [
        'username' => 'StockController',
        'full_name' => 'Stock Controller',
        'role' => 'inventory_manager',
    ],
    [
        'username' => 'Accountant',
        'full_name' => 'Finance Accountant',
        'role' => 'accountant',
    ],
    [
        'username' => 'Manager',
        'full_name' => 'Operations Manager',
        'role' => 'manager',
    ],
    [
        'username' => 'Supervisor',
        'full_name' => 'Floor Supervisor',
        'role' => 'manager',
    ],
];

$now = date('Y-m-d H:i:s');
$inserted = 0;
$skipped = 0;

foreach ($seedUsers as $user) {
    $existing = $db->fetchOne('SELECT id FROM users WHERE username = ?', [$user['username']]);
    if ($existing) {
        $skipped++;
        echo "Skipping {$user['username']} (already exists)" . PHP_EOL;
        continue;
    }

    $passwordPlain = $user['username'] . '1234';
    $data = [
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'password' => Auth::hashPassword($passwordPlain),
        'email' => strtolower($user['username']) . '@example.com',
        'phone' => null,
        'is_active' => 1,
        'created_at' => $now,
    ];

    try {
        $db->insert('users', $data);
        $inserted++;
        echo "Created user {$user['username']} with password {$passwordPlain}" . PHP_EOL;
    } catch (Exception $e) {
        echo "Failed to create {$user['username']}: {$e->getMessage()}" . PHP_EOL;
    }
}

echo PHP_EOL . "Seed complete: {$inserted} inserted, {$skipped} skipped." . PHP_EOL;
