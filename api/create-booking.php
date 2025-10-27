<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    $checkInDate = $_POST['check_in_date'];
    $checkOutDate = $_POST['check_out_date'];
    $roomId = $_POST['room_id'];
    $roomRate = $_POST['room_rate'];
    
    // Calculate nights
    $date1 = new DateTime($checkInDate);
    $date2 = new DateTime($checkOutDate);
    $nights = $date1->diff($date2)->days;
    
    if ($nights <= 0) {
        throw new Exception('Invalid date range');
    }
    
    $totalAmount = $nights * $roomRate;
    
    // Generate booking number
    $bookingNumber = 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Create booking
    $bookingId = $db->insert('bookings', [
        'booking_number' => $bookingNumber,
        'room_id' => $roomId,
        'guest_name' => sanitizeInput($_POST['guest_name']),
        'guest_phone' => sanitizeInput($_POST['guest_phone']),
        'guest_email' => sanitizeInput($_POST['guest_email'] ?? ''),
        'guest_id_number' => sanitizeInput($_POST['guest_id_number'] ?? ''),
        'check_in_date' => $checkInDate,
        'check_out_date' => $checkOutDate,
        'adults' => $_POST['adults'] ?? 1,
        'children' => $_POST['children'] ?? 0,
        'room_rate' => $roomRate,
        'total_nights' => $nights,
        'total_amount' => $totalAmount,
        'booking_status' => 'confirmed',
        'payment_status' => 'pending',
        'special_requests' => sanitizeInput($_POST['special_requests'] ?? ''),
        'user_id' => $auth->getUserId()
    ]);
    
    // Update room status
    $db->update('rooms', 
        ['status' => 'reserved'],
        'id = :id',
        ['id' => $roomId]
    );
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'booking_number' => $bookingNumber,
        'message' => 'Booking created successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
