<?php
/**
 * Quick diagnostic script to check if new module tables exist
 */

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

$tables_to_check = [
    // Events tables
    'event_venues',
    'event_types',
    'event_bookings',
    'event_services',
    'event_booking_services',
    'event_payments',
    
    // Security tables
    'security_personnel',
    'security_posts',
    'security_shifts',
    'security_schedules',
    'security_incidents',
    'security_patrols',
    'security_visitor_log',
    
    // HR tables
    'hr_departments',
    'hr_positions',
    'hr_employees',
    'hr_leave_types',
    'hr_leave_applications',
    'hr_payroll_runs',
    'hr_payroll_details'
];

$results = [];
$missing_tables = [];

foreach ($tables_to_check as $table) {
    try {
        $result = $db->fetchOne("SHOW TABLES LIKE ?", [$table]);
        $exists = !empty($result);
        $results[$table] = $exists;
        
        if (!$exists) {
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        $results[$table] = false;
        $missing_tables[] = $table;
    }
}

$total = count($tables_to_check);
$existing = count($tables_to_check) - count($missing_tables);

echo json_encode([
    'success' => true,
    'total_tables' => $total,
    'existing_tables' => $existing,
    'missing_tables' => $missing_tables,
    'all_tables_status' => $results,
    'message' => count($missing_tables) > 0 
        ? "Missing {count($missing_tables)} tables. Run migrations 020, 021, 022, 023, 024" 
        : "All tables exist!"
], JSON_PRETTY_PRINT);
