<?php
require_once '../includes/bootstrap.php';

use App\Services\RoomBookingService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$database = Database::getInstance();
$pdo = $database->getConnection();
$bookingService = new RoomBookingService($pdo);

$payload = [
    'room_id' => (int)($_POST['room_id'] ?? 0),
    'guest_name' => sanitizeInput($_POST['guest_name'] ?? ''),
    'guest_phone' => sanitizeInput($_POST['guest_phone'] ?? ''),
    'guest_email' => sanitizeInput($_POST['guest_email'] ?? ''),
    'guest_id_number' => sanitizeInput($_POST['guest_id_number'] ?? ''),
    'check_in_date' => $_POST['check_in_date'] ?? null,
    'check_out_date' => $_POST['check_out_date'] ?? null,
    'adults' => $_POST['adults'] ?? 1,
    'children' => $_POST['children'] ?? 0,
    'rate_per_night' => (float)($_POST['room_rate'] ?? 0),
    'special_requests' => sanitizeInput($_POST['special_requests'] ?? ''),
    'deposit_amount' => isset($_POST['deposit_amount']) ? (float)$_POST['deposit_amount'] : 0.0,
    'deposit_method' => sanitizeInput($_POST['deposit_method'] ?? ''),
    'deposit_reference' => sanitizeInput($_POST['deposit_reference'] ?? ''),
    'deposit_customer_phone' => sanitizeInput($_POST['deposit_customer_phone'] ?? ''),
    'deposit_notes' => sanitizeInput($_POST['deposit_notes'] ?? ''),
];

try {
    $result = $bookingService->createBooking($payload, (int)$auth->getUserId());

    // Send WhatsApp confirmation if phone provided and WhatsApp enabled
    $whatsappSent = false;
    if (!empty($payload['guest_phone']) && (settings('whatsapp_enabled') ?? '0') === '1') {
        try {
            $whatsappSent = $bookingService->sendWhatsAppConfirmation($result['booking_id']);
        } catch (Exception $e) {
            error_log("WhatsApp confirmation failed: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'booking_id' => $result['booking_id'],
        'booking_number' => $result['booking_number'],
        'total_amount' => $result['total_amount'],
        'total_nights' => $result['total_nights'],
        'whatsapp_sent' => $whatsappSent,
        'message' => 'Booking created successfully' . ($whatsappSent ? ' - WhatsApp confirmation sent' : '')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
