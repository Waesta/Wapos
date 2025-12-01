<?php
require_once '../includes/bootstrap.php';

use App\Services\RoomBookingService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$service = new RoomBookingService($pdo);

try {
    $service->ensureSchema();

    $roomsStmt = $pdo->query(
        "SELECT status FROM rooms WHERE is_active = 1"
    );
    $roomStatuses = $roomsStmt ? $roomsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $summary = [
        'total_rooms' => count($roomStatuses),
        'available' => 0,
        'occupied' => 0,
        'reserved' => 0,
        'maintenance' => 0,
        'active_bookings' => 0,
        'timestamp' => date(DATE_ATOM),
    ];

    foreach ($roomStatuses as $status) {
        $key = strtolower($status ?? '');
        if (isset($summary[$key])) {
            $summary[$key] += 1;
        }
    }

    $today = date('Y-m-d');

    $activeStmt = $pdo->prepare(
        "SELECT b.*, r.room_number, rt.name AS room_type_name
         FROM room_bookings b
         JOIN rooms r ON b.room_id = r.id
         JOIN room_types rt ON r.room_type_id = rt.id
         WHERE b.status IN ('confirmed', 'checked_in')
           AND b.check_out_date >= :today
         ORDER BY b.check_in_date ASC
         LIMIT 20"
    );
    $activeStmt->execute([':today' => $today]);
    $activeBookings = $activeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $summary['active_bookings'] = count($activeBookings);

    $feed = array_map(static function ($booking) {
        $statusRaw = $booking['status'] ?? 'confirmed';
        return [
            'id' => (int)($booking['id'] ?? 0),
            'guest_name' => $booking['guest_name'] ?? '',
            'room_number' => $booking['room_number'] ?? '',
            'room_type_name' => $booking['room_type_name'] ?? '',
            'status' => $statusRaw,
            'check_in_date' => $booking['check_in_date'] ?? null,
            'check_out_date' => $booking['check_out_date'] ?? null,
            'total_nights' => (int)($booking['total_nights'] ?? 0),
            'total_amount' => (float)($booking['total_amount'] ?? 0),
            'payment_status' => $booking['payment_status'] ?? 'pending',
        ];
    }, $activeBookings);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'active_bookings' => $feed,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load rooms live feed',
        'error' => $e->getMessage(),
    ]);
}
