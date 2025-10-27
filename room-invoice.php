<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$bookingId = $_GET['booking_id'] ?? 0;

// Get booking details
$booking = $db->fetchOne("
    SELECT b.*, r.room_number, rt.name as room_type_name, u.full_name as booked_by
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN room_types rt ON r.room_type_id = rt.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
", [$bookingId]);

if (!$booking) {
    die('Booking not found');
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
            <p><strong>Name:</strong> <?= htmlspecialchars($booking['guest_name']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($booking['guest_phone']) ?></p>
            <?php if ($booking['guest_email']): ?>
            <p><strong>Email:</strong> <?= htmlspecialchars($booking['guest_email']) ?></p>
            <?php endif; ?>
            <?php if ($booking['guest_id_number']): ?>
            <p><strong>ID Number:</strong> <?= htmlspecialchars($booking['guest_id_number']) ?></p>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h3>Booking Details</h3>
            <p><strong>Booking #:</strong> <?= htmlspecialchars($booking['booking_number']) ?></p>
            <p><strong>Date:</strong> <?= formatDate($booking['created_at'], 'd/m/Y') ?></p>
            <p><strong>Status:</strong> 
                <span class="status-badge status-<?= $booking['payment_status'] === 'paid' ? 'paid' : 'pending' ?>">
                    <?= ucfirst($booking['payment_status']) ?>
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
                    <strong>Room <?= htmlspecialchars($booking['room_number']) ?></strong><br>
                    <?= htmlspecialchars($booking['room_type_name']) ?>
                </td>
                <td>
                    Check-in: <?= formatDate($booking['check_in_date'], 'd/m/Y') ?><br>
                    Check-out: <?= formatDate($booking['check_out_date'], 'd/m/Y') ?><br>
                    Guests: <?= $booking['adults'] ?> Adults<?= $booking['children'] > 0 ? ', ' . $booking['children'] . ' Children' : '' ?>
                </td>
                <td>KES <?= formatMoney($booking['room_rate']) ?></td>
                <td><?= $booking['total_nights'] ?></td>
                <td>KES <?= formatMoney($booking['total_amount']) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($booking['special_requests']): ?>
    <div style="margin-bottom: 20px;">
        <strong>Special Requests:</strong>
        <p><?= nl2br(htmlspecialchars($booking['special_requests'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="totals">
        <table>
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td><strong>KES <?= formatMoney($booking['total_amount']) ?></strong></td>
            </tr>
            <tr class="total-row">
                <td>TOTAL:</td>
                <td>KES <?= formatMoney($booking['total_amount']) ?></td>
            </tr>
            <tr>
                <td>Amount Paid:</td>
                <td>KES <?= formatMoney($booking['amount_paid']) ?></td>
            </tr>
            <?php if ($booking['amount_paid'] < $booking['total_amount']): ?>
            <tr style="color: #dc3545;">
                <td><strong>Balance Due:</strong></td>
                <td><strong>KES <?= formatMoney($booking['total_amount'] - $booking['amount_paid']) ?></strong></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($booking['check_in_time']): ?>
    <div style="margin-top: 30px;">
        <p><strong>Checked In:</strong> <?= formatDate($booking['check_in_time'], 'd/m/Y H:i') ?></p>
        <?php if ($booking['check_out_time']): ?>
        <p><strong>Checked Out:</strong> <?= formatDate($booking['check_out_time'], 'd/m/Y H:i') ?></p>
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
        <button onclick="window.close()" style="padding: 10px 30px; font-size: 16px; cursor: pointer;">
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
