<?php
/**
 * Guest Self Check-in/Check-out
 * 
 * QR code based self-service for hotel guests:
 * - Scan QR to check-in
 * - View booking details
 * - Request services
 * - Check-out
 */

require_once 'includes/bootstrap.php';

$db = Database::getInstance();
$error = null;
$success = null;
$booking = null;
$action = $_GET['action'] ?? 'checkin';

// Get booking by token or booking number
$token = $_GET['token'] ?? null;
$bookingNumber = $_GET['booking'] ?? null;

if ($token) {
    // Validate token (token = md5(booking_number + secret))
    $booking = $db->fetchOne("
        SELECT rb.*, r.room_number, r.room_type, r.floor,
               rt.name as room_type_name
        FROM room_bookings rb
        JOIN rooms r ON rb.room_id = r.id
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE MD5(CONCAT(rb.booking_number, 'guest_checkin_secret')) = ?
    ", [$token]);
} elseif ($bookingNumber) {
    $booking = $db->fetchOne("
        SELECT rb.*, r.room_number, r.room_type, r.floor,
               rt.name as room_type_name
        FROM room_bookings rb
        JOIN rooms r ON rb.room_id = r.id
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE rb.booking_number = ?
    ", [$bookingNumber]);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'checkin' && $booking['status'] === 'confirmed') {
        // Process check-in
        $db->update('room_bookings', [
            'status' => 'checked_in',
            'actual_check_in' => date('Y-m-d H:i:s')
        ], 'id = ?', [$booking['id']]);
        
        // Update room status
        $db->update('rooms', ['status' => 'occupied'], 'id = ?', [$booking['room_id']]);
        
        $success = "Check-in successful! Welcome to Room " . $booking['room_number'];
        $booking['status'] = 'checked_in';
        
    } elseif ($postAction === 'checkout' && $booking['status'] === 'checked_in') {
        // Check if balance is paid
        if ($booking['balance_due'] > 0) {
            $error = "Please settle your outstanding balance of " . CURRENCY_SYMBOL . number_format($booking['balance_due'], 2) . " at the front desk.";
        } else {
            $db->update('room_bookings', [
                'status' => 'checked_out',
                'actual_check_out' => date('Y-m-d H:i:s')
            ], 'id = ?', [$booking['id']]);
            
            // Update room status
            $db->update('rooms', ['status' => 'dirty'], 'id = ?', [$booking['room_id']]);
            
            $success = "Check-out complete! Thank you for staying with us.";
            $booking['status'] = 'checked_out';
        }
    }
}

$pageTitle = 'Guest Self-Service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .checkin-card {
            background: white;
            border-radius: 1.5rem;
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .checkin-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .checkin-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .room-number {
            font-size: 4rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .checkin-body {
            padding: 2rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
        }
        
        .status-confirmed { background: #fff3cd; color: #856404; }
        .status-checked_in { background: #d4edda; color: #155724; }
        .status-checked_out { background: #cce5ff; color: #004085; }
        
        .action-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 1rem;
            margin-top: 1rem;
        }
        
        .lookup-form {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        
        .qr-scanner {
            width: 100%;
            max-width: 300px;
            margin: 1rem auto;
            border-radius: 1rem;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <?php if (!$booking): ?>
        <!-- Booking Lookup -->
        <div class="lookup-form">
            <i class="bi bi-building text-primary" style="font-size: 4rem;"></i>
            <h2 class="mt-3 mb-4">Guest Self-Service</h2>
            
            <form method="GET">
                <div class="mb-3">
                    <label class="form-label">Enter Booking Number</label>
                    <input type="text" name="booking" class="form-control form-control-lg text-center" 
                           placeholder="e.g., BK-2024-001" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-search me-2"></i>Find Booking
                </button>
            </form>
            
            <hr class="my-4">
            
            <p class="text-muted mb-3">Or scan your booking QR code</p>
            <div class="qr-scanner bg-dark d-flex align-items-center justify-content-center" style="height: 200px;">
                <div class="text-white text-center">
                    <i class="bi bi-qr-code-scan" style="font-size: 3rem;"></i>
                    <p class="mt-2 mb-0">Camera access required</p>
                </div>
            </div>
            
            <a href="<?= APP_URL ?>/" class="btn btn-link mt-3">
                <i class="bi bi-arrow-left me-1"></i>Back to Home
            </a>
        </div>
        
    <?php else: ?>
        <!-- Booking Details -->
        <div class="checkin-card">
            <div class="checkin-header">
                <h1><?= APP_NAME ?></h1>
                <div class="room-number"><?= htmlspecialchars($booking['room_number']) ?></div>
                <p class="mb-0"><?= htmlspecialchars($booking['room_type_name'] ?? $booking['room_type'] ?? 'Standard Room') ?></p>
            </div>
            
            <div class="checkin-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <div class="text-center mb-4">
                    <span class="status-badge status-<?= $booking['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $booking['status'])) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Booking #</span>
                    <span class="info-value"><?= htmlspecialchars($booking['booking_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Guest Name</span>
                    <span class="info-value"><?= htmlspecialchars($booking['guest_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Check-in Date</span>
                    <span class="info-value"><?= date('D, M j, Y', strtotime($booking['check_in_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Check-out Date</span>
                    <span class="info-value"><?= date('D, M j, Y', strtotime($booking['check_out_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Guests</span>
                    <span class="info-value"><?= $booking['adults'] ?? $booking['guests'] ?> Adults<?= ($booking['children'] ?? 0) > 0 ? ', ' . $booking['children'] . ' Children' : '' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Amount</span>
                    <span class="info-value"><?= CURRENCY_SYMBOL ?><?= number_format($booking['total_amount'], 2) ?></span>
                </div>
                <?php if ($booking['balance_due'] > 0): ?>
                <div class="info-row">
                    <span class="info-label">Balance Due</span>
                    <span class="info-value text-danger"><?= CURRENCY_SYMBOL ?><?= number_format($booking['balance_due'], 2) ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Actions based on status -->
                <?php if ($booking['status'] === 'confirmed'): ?>
                    <?php 
                        $checkinDate = strtotime($booking['check_in_date']);
                        $today = strtotime(date('Y-m-d'));
                        $canCheckin = $checkinDate <= $today;
                    ?>
                    <?php if ($canCheckin): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="checkin">
                            <button type="submit" class="action-btn btn btn-success">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Check In Now
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            Check-in available from <?= date('M j, Y', $checkinDate) ?>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($booking['status'] === 'checked_in'): ?>
                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <a href="room-service-menu.php?room=<?= $booking['room_id'] ?>" class="btn btn-outline-primary w-100 py-3">
                                <i class="bi bi-cup-hot d-block" style="font-size: 1.5rem;"></i>
                                Room Service
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="maintenance-request.php?room=<?= $booking['room_id'] ?>" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-tools d-block" style="font-size: 1.5rem;"></i>
                                Maintenance
                            </a>
                        </div>
                    </div>
                    
                    <?php 
                        $checkoutDate = strtotime($booking['check_out_date']);
                        $canCheckout = $checkoutDate <= $today || $checkoutDate == strtotime(date('Y-m-d', strtotime('+1 day')));
                    ?>
                    <?php if ($canCheckout): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="action-btn btn btn-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Check Out
                            </button>
                        </form>
                    <?php endif; ?>
                    
                <?php elseif ($booking['status'] === 'checked_out'): ?>
                    <div class="text-center mt-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-2">Thank you for staying with us!</h5>
                        <p class="text-muted">We hope to see you again soon.</p>
                        <a href="feedback.php?booking=<?= $booking['id'] ?>" class="btn btn-outline-primary">
                            <i class="bi bi-star me-1"></i>Leave Feedback
                        </a>
                    </div>
                <?php endif; ?>
                
                <a href="guest-checkin.php" class="btn btn-link w-100 mt-3">
                    <i class="bi bi-arrow-left me-1"></i>Look up another booking
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
