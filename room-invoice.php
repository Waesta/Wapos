<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

use App\Services\RoomBookingService;

$db = Database::getInstance();
$pdo = $db->getConnection();
$bookingService = new RoomBookingService($pdo);

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    die('Invalid booking reference');
}

$invoiceData = $bookingService->getInvoiceData($bookingId);
$booking = $invoiceData['booking'];
$folioEntries = $invoiceData['folio'] ?? [];
$totals = $invoiceData['totals'] ?? ['total_charges' => 0, 'total_payments' => 0, 'balance_due' => 0];
$mode = $invoiceData['mode'] ?? 'legacy';

$redirectUrl = $_GET['redirect'] ?? 'rooms.php';
if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $redirectUrl)) {
    $redirectUrl = 'rooms.php';
}

if (!$booking) {
    die('Booking not found');
}

$ratePerNight = (float)($booking['room_rate'] ?? $booking['rate_per_night'] ?? 0);
$totalNights = (int)($booking['total_nights'] ?? 1);
if ($totalNights <= 0) {
    $totalNights = 1;
}

$totalCharges = (float)$totals['total_charges'];
$totalPayments = (float)$totals['total_payments'];
$balanceDue = (float)$totals['balance_due'];

$paymentStatus = strtolower($booking['payment_status'] ?? '');
if ($balanceDue <= 0.01) {
    $paymentStatus = 'paid';
} elseif ($paymentStatus === '') {
    $paymentStatus = 'pending';
}
$statusBadgeClass = $paymentStatus === 'paid' ? 'status-paid' : 'status-pending';

$displayTotalAmount = $totalCharges > 0 ? $totalCharges : (float)($booking['total_amount'] ?? 0);
$displayPaidAmount = $totalPayments > 0 ? $totalPayments : (float)($booking['amount_paid'] ?? 0);
$balanceClass = $balanceDue > 0.01 ? 'text-danger' : 'text-success';

$checkInRecorded = $booking['actual_check_in'] ?? $booking['check_in_time'] ?? null;
$checkOutRecorded = $booking['actual_check_out'] ?? $booking['check_out_time'] ?? null;

$folioTypeLabels = [
    'room_charge' => 'Room Charge',
    'service' => 'Service',
    'tax' => 'Tax',
    'deposit' => 'Deposit',
    'payment' => 'Payment',
    'adjustment' => 'Adjustment',
];

$guestName = $booking['guest_name'] ?? '';
$guestPhone = $booking['guest_phone'] ?? '';
$guestEmail = $booking['guest_email'] ?? '';
$guestIdNumber = $booking['guest_id_number'] ?? '';
$bookingNumber = $booking['booking_number'] ?? '';
$bookingCreatedAt = $booking['created_at'] ?? null;
$roomNumber = $booking['room_number'] ?? '';
$roomType = $booking['room_type_name'] ?? '';
$checkInDate = $booking['check_in_date'] ?? null;
$checkOutDate = $booking['check_out_date'] ?? null;
$specialRequests = $booking['special_requests'] ?? '';

$adultsCount = (int)($booking['adults'] ?? 1);
$childrenCount = (int)($booking['children'] ?? 0);
$guestCountLabel = $adultsCount . ' Adult' . ($adultsCount === 1 ? '' : 's');
if ($childrenCount > 0) {
    $guestCountLabel .= ', ' . $childrenCount . ' Child' . ($childrenCount === 1 ? '' : 'ren');
}

// Get settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Invoice - <?= htmlspecialchars($booking['booking_number']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .invoice-info div {
            flex: 1;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table th,
        .details-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .details-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .totals {
            text-align: right;
            margin-top: 30px;
        }
        .totals table {
            margin-left: auto;
            width: 300px;
        }
        .totals td {
            padding: 8px;
        }
        .total-row {
            font-size: 1.2em;
            font-weight: bold;
            border-top: 2px solid #333;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?></h1>
        <?php if (!empty($settings['business_address'])): ?>
        <p><?= nl2br(htmlspecialchars($settings['business_address'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($settings['business_phone'])): ?>
        <p>Tel: <?= htmlspecialchars($settings['business_phone']) ?> 
           <?php if (!empty($settings['business_email'])): ?>
           | Email: <?= htmlspecialchars($settings['business_email']) ?>
           <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>

    <h2>ROOM BOOKING INVOICE</h2>

    <div class="invoice-info">
        <div>
            <h3>Guest Information</h3>
            <p><strong>Name:</strong> <?= htmlspecialchars($guestName) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($guestPhone) ?></p>
            <?php if ($guestEmail): ?>
            <p><strong>Email:</strong> <?= htmlspecialchars($guestEmail) ?></p>
            <?php endif; ?>
            <?php if ($guestIdNumber): ?>
            <p><strong>ID Number:</strong> <?= htmlspecialchars($guestIdNumber) ?></p>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h3>Booking Details</h3>
            <p><strong>Booking #:</strong> <?= htmlspecialchars($bookingNumber) ?></p>
            <p><strong>Date:</strong> <?= $bookingCreatedAt ? formatDate($bookingCreatedAt, 'd/m/Y') : '—' ?></p>
            <p><strong>Status:</strong> 
                <span class="status-badge status-<?= ($paymentStatus === 'paid') ? 'paid' : 'pending' ?>">
                    <?= ucfirst($paymentStatus) ?>
                </span>
            </p>
        </div>
    </div>

    <table class="details-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Details</th>
                <th>Rate</th>
                <th>Nights</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Room <?= htmlspecialchars($roomNumber) ?></strong><br>
                    <?= htmlspecialchars($roomType) ?>
                </td>
                <td>
                    Check-in: <?= $checkInDate ? formatDate($checkInDate, 'd/m/Y') : '—' ?><br>
                    Check-out: <?= $checkOutDate ? formatDate($checkOutDate, 'd/m/Y') : '—' ?><br>
                    Guests: <?= htmlspecialchars($guestCountLabel) ?>
                </td>
                <td><?= formatCurrency($ratePerNight) ?></td>
                <td><?= $totalNights ?></td>
                <td><?= formatCurrency($displayTotalAmount) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($specialRequests)): ?>
    <div style="margin-bottom: 20px;">
        <strong>Special Requests:</strong>
        <p><?= nl2br(htmlspecialchars($specialRequests)) ?></p>
    </div>
    <?php endif; ?>

    <div class="totals">
        <table>
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td><strong><?= formatCurrency($displayTotalAmount) ?></strong></td>
            </tr>
            <tr class="total-row">
                <td>TOTAL:</td>
                <td><?= formatCurrency($displayTotalAmount) ?></td>
            </tr>
            <tr>
                <td>Amount Paid:</td>
                <td><?= formatCurrency($displayPaidAmount) ?></td>
            </tr>
            <?php if ($balanceDue > 0.01): ?>
            <tr class="<?= $balanceClass ?>">
                <td><strong>Balance Due:</strong></td>
                <td><strong><?= formatCurrency($balanceDue) ?></strong></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($checkInRecorded): ?>
    <div style="margin-top: 30px;">
        <p><strong>Checked In:</strong> <?= formatDate($checkInRecorded, 'd/m/Y H:i') ?></p>
        <?php if ($checkOutRecorded): ?>
        <p><strong>Checked Out:</strong> <?= formatDate($checkOutRecorded, 'd/m/Y H:i') ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Thank you for choosing <?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?>!</p>
        <p>We hope you enjoyed your stay.</p>
        <p style="margin-top: 20px; font-size: 0.9em;">
            This is a computer-generated invoice. Powered by <?= APP_NAME ?>
        </p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; margin-right: 10px;">
            Print Invoice
        </button>
        <button onclick="window.location.href='<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>'" style="padding: 10px 30px; font-size: 16px; cursor: pointer;">
            Close
        </button>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
