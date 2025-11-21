<?php
require_once '../includes/bootstrap.php';
$auth->requireLogin();

use App\Services\RoomBookingService;

$database = Database::getInstance();
$pdo = $database->getConnection();
$bookingService = new RoomBookingService($pdo);

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bookingId <= 0) {
    $_SESSION['error_message'] = 'Invalid booking selected for check-in.';
    header('Location: ../rooms.php');
    exit;
}

try {
    $bookingService->checkIn($bookingId, (int)$auth->getUserId());
    $_SESSION['success_message'] = 'Guest checked in successfully';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Check-in failed: ' . $e->getMessage();
}

header('Location: ../rooms.php');
