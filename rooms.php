<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get room types and rooms
$roomTypes = $db->fetchAll("SELECT * FROM room_types WHERE is_active = 1 ORDER BY name");
$rooms = $db->fetchAll("
    SELECT r.*, rt.name as type_name, rt.base_price 
    FROM rooms r 
    JOIN room_types rt ON r.room_type_id = rt.id 
    WHERE r.is_active = 1 
    ORDER BY r.room_number
");

// Get current bookings
$today = date('Y-m-d');
$activeBookings = $db->fetchAll("
    SELECT b.*, r.room_number, rt.name as room_type_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE b.booking_status IN ('confirmed', 'checked-in')
    AND b.check_out_date >= ?
    ORDER BY b.check_in_date
", [$today]);

$pageTitle = 'Room Booking';
include 'includes/header.php';
?>

<style>
    .room-card {
        cursor: pointer;
        transition: all 0.3s;
        height: 180px;
    }
    .room-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }
    .room-available { border-left: 4px solid #28a745; }
    .room-occupied { border-left: 4px solid #dc3545; }
    .room-reserved { border-left: 4px solid #ffc107; }
    .room-maintenance { border-left: 4px solid #6c757d; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Room Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal" onclick="resetBookingForm()">
        <i class="bi bi-plus-circle me-2"></i>New Booking
    </button>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Rooms</p>
                        <h3 class="mb-0 fw-bold"><?= count($rooms) ?></h3>
                    </div>
                    <i class="bi bi-door-open text-primary fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Available</p>
                        <h3 class="mb-0 fw-bold text-success">
                            <?= count(array_filter($rooms, fn($r) => $r['status'] === 'available')) ?>
                        </h3>
                    </div>
                    <i class="bi bi-check-circle text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Occupied</p>
                        <h3 class="mb-0 fw-bold text-danger">
                            <?= count(array_filter($rooms, fn($r) => $r['status'] === 'occupied')) ?>
                        </h3>
                    </div>
                    <i class="bi bi-person-fill text-danger fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Active Bookings</p>
                        <h3 class="mb-0 fw-bold text-info"><?= count($activeBookings) ?></h3>
                    </div>
                    <i class="bi bi-calendar-check text-info fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Rooms Grid -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Available Rooms</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($rooms as $room): ?>
                    <div class="col-md-4">
                        <div class="card room-card room-<?= $room['status'] ?>" 
                             onclick='selectRoom(<?= json_encode($room) ?>)'>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-0"><?= htmlspecialchars($room['room_number']) ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($room['type_name']) ?></small>
                                    </div>
                                    <i class="bi bi-<?= $room['status'] === 'available' ? 'check-circle text-success' : 'x-circle text-danger' ?> fs-3"></i>
                                </div>
                                <p class="mb-2 small">
                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($room['floor']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?= $room['status'] === 'available' ? 'success' : ($room['status'] === 'occupied' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($room['status']) ?>
                                    </span>
                                    <strong class="text-primary"><?= formatMoney($room['base_price']) ?>/night</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Bookings -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Active Bookings</h6>
            </div>
            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($activeBookings)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No active bookings</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activeBookings as $booking): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($booking['guest_name']) ?></h6>
                                    <p class="mb-1 small text-muted">
                                        <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($booking['room_number']) ?>
                                        - <?= htmlspecialchars($booking['room_type_name']) ?>
                                    </p>
                                </div>
                                <span class="badge bg-<?= $booking['booking_status'] === 'checked-in' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($booking['booking_status']) ?>
                                </span>
                            </div>
                            <div class="small">
                                <p class="mb-1">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= formatDate($booking['check_in_date'], 'd M') ?> - 
                                    <?= formatDate($booking['check_out_date'], 'd M') ?>
                                    (<?= $booking['total_nights'] ?> nights)
                                </p>
                                <p class="mb-0">
                                    <strong>KES <?= formatMoney($booking['total_amount']) ?></strong>
                                    <?php if ($booking['payment_status'] !== 'paid'): ?>
                                        <span class="badge bg-warning text-dark ms-2">Pending</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mt-2">
                                <?php if ($booking['booking_status'] === 'confirmed'): ?>
                                    <button class="btn btn-sm btn-success" onclick="checkIn(<?= $booking['id'] ?>)">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                                    </button>
                                <?php elseif ($booking['booking_status'] === 'checked-in'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="checkOut(<?= $booking['id'] ?>)">
                                        <i class="bi bi-box-arrow-right me-1"></i>Check Out
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="api/create-booking.php" id="bookingForm">
                <div class="modal-header">
                    <h5 class="modal-title">New Room Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="room_id" id="room_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Room *</label>
                            <select class="form-select" name="room_id" id="select_room_id" required onchange="updateRoomDetails()">
                                <option value="">Select Room</option>
                                <?php foreach ($rooms as $room): ?>
                                    <?php if ($room['status'] === 'available'): ?>
                                    <option value="<?= $room['id'] ?>" 
                                            data-price="<?= $room['base_price'] ?>"
                                            data-type="<?= htmlspecialchars($room['type_name']) ?>">
                                        <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['type_name']) ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room Rate</label>
                            <input type="text" class="form-control" id="room_rate_display" readonly>
                            <input type="hidden" name="room_rate" id="room_rate">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Guest Name *</label>
                            <input type="text" class="form-control" name="guest_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guest Phone *</label>
                            <input type="tel" class="form-control" name="guest_phone" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Guest Email</label>
                            <input type="email" class="form-control" name="guest_email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID Number</label>
                            <input type="text" class="form-control" name="guest_id_number">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Check-In Date *</label>
                            <input type="date" class="form-control" name="check_in_date" id="check_in_date" 
                                   min="<?= date('Y-m-d') ?>" required onchange="calculateTotal()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-Out Date *</label>
                            <input type="date" class="form-control" name="check_out_date" id="check_out_date" 
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required onchange="calculateTotal()">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Adults</label>
                            <input type="number" class="form-control" name="adults" value="1" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Children</label>
                            <input type="number" class="form-control" name="children" value="0" min="0">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Special Requests</label>
                            <textarea class="form-control" name="special_requests" rows="2"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total Nights:</span>
                                        <strong id="total_nights">0</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span><strong>Total Amount:</strong></span>
                                        <strong class="text-primary fs-5" id="total_amount"><?= formatMoney(0) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Get currency from PHP
const currencySymbol = '<?= $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'currency'")['setting_value'] ?? '$' ?>';

function selectRoom(room) {
    if (room.status === 'available') {
        document.getElementById('select_room_id').value = room.id;
        updateRoomDetails();
        new bootstrap.Modal(document.getElementById('bookingModal')).show();
    } else {
        alert('This room is not available');
    }
}

function updateRoomDetails() {
    const select = document.getElementById('select_room_id');
    const option = select.options[select.selectedIndex];
    const price = option.dataset.price || 0;
    
    document.getElementById('room_rate').value = price;
    document.getElementById('room_rate_display').value = parseFloat(price).toFixed(2);
    
    calculateTotal();
}

function calculateTotal() {
    const checkIn = document.getElementById('check_in_date').value;
    const checkOut = document.getElementById('check_out_date').value;
    const rate = parseFloat(document.getElementById('room_rate').value) || 0;
    
    if (checkIn && checkOut && rate > 0) {
        const date1 = new Date(checkIn);
        const date2 = new Date(checkOut);
        const nights = Math.ceil((date2 - date1) / (1000 * 60 * 60 * 24));
        
        if (nights > 0) {
            const total = nights * rate;
            document.getElementById('total_nights').textContent = nights;
            document.getElementById('total_amount').textContent = currencySymbol + ' ' + total.toFixed(2);
        }
    }
}

function resetBookingForm() {
    document.getElementById('bookingForm').reset();
    document.getElementById('total_nights').textContent = '0';
    document.getElementById('total_amount').textContent = currencySymbol + ' 0.00';
}

function checkIn(bookingId) {
    if (confirm('Check in this guest?')) {
        window.location.href = `api/check-in.php?id=${bookingId}`;
    }
}

function checkOut(bookingId) {
    if (confirm('Check out this guest and generate invoice?')) {
        window.location.href = `api/check-out.php?id=${bookingId}`;
    }
}

// Form submission
document.getElementById('bookingForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('api/create-booking.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Booking created successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (result.message || 'Failed to create booking'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
