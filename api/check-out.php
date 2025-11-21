<?php
require_once '../includes/bootstrap.php';
$auth->requireLogin();

use App\Services\RoomBookingService;

$database = Database::getInstance();
$pdo = $database->getConnection();
$bookingService = new RoomBookingService($pdo);

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bookingId <= 0) {
    $_SESSION['error_message'] = 'Invalid booking selected for check-out.';
    header('Location: ../rooms.php');
    exit;
}

try {
    $bookingService->checkOut($bookingId, (int)$auth->getUserId());
    header('Location: ../room-invoice.php?booking_id=' . $bookingId);
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Check-out failed: ' . $e->getMessage();
    header('Location: ../rooms.php');
}
