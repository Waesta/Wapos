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
            'booking_status' => 'checked-out',
            'check_out_time' => date('Y-m-d H:i:s'),
            'payment_status' => 'paid'
        ],
        'id = :id',
        ['id' => $bookingId]
    );
    
    // Update room
    $db->update('rooms',
        ['status' => 'available'],
        'id = :id',
        ['id' => $booking['room_id']]
    );
    
    $db->commit();
    
    // Redirect to invoice
    header('Location: ../room-invoice.php?booking_id=' . $bookingId);
    
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error_message'] = 'Check-out failed: ' . $e->getMessage();
    header('Location: ../rooms.php');
}
