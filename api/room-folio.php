<?php
require_once '../includes/bootstrap.php';

use App\Services\RoomBookingService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking reference']);
    exit;
}

$database = Database::getInstance();
$pdo = $database->getConnection();
$bookingService = new RoomBookingService($pdo);

try {
    $invoiceData = $bookingService->getInvoiceData($bookingId);
    if (!$invoiceData['booking']) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'mode' => $invoiceData['mode'],
        'booking' => $invoiceData['booking'],
        'folio' => $invoiceData['folio'],
        'totals' => $invoiceData['totals'],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
