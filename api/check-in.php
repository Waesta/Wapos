<?php
require_once '../includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$bookingId = $_GET['id'] ?? 0;

try {
    $db->beginTransaction();
    
    // Get booking
    $booking = $db->fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Update booking
    $db->update('bookings',
        [
            'booking_status' => 'checked-in',
            'check_in_time' => date('Y-m-d H:i:s')
        ],
        'id = :id',
        ['id' => $bookingId]
    );
    
    // Update room
    $db->update('rooms',
        ['status' => 'occupied'],
        'id = :id',
        ['id' => $booking['room_id']]
    );
    
    $db->commit();
    
    $_SESSION['success_message'] = 'Guest checked in successfully';
    header('Location: ../rooms.php');
    
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error_message'] = 'Check-in failed: ' . $e->getMessage();
    header('Location: ../rooms.php');
}
