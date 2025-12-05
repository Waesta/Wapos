<?php
require_once 'includes/bootstrap.php';
require_once __DIR__ . '/includes/promotion-spotlight.php';
$auth->requireLogin();

use App\Services\RoomBookingService;

$db = Database::getInstance();
$pdo = $db->getConnection();
$activePromotions = loadActivePromotions($pdo);
$canManagePromotions = $auth->hasRole(['admin','manager']);

$bookingService = new RoomBookingService($pdo);
$migrationSummary = null;
$schemaReady = false;
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;

unset($_SESSION['success_message'], $_SESSION['error_message']);

// Ensure schema and migrate legacy bookings once
try {
    $bookingService->ensureSchema();
    $migrationSummary = $bookingService->migrateLegacyBookings();
    $schemaReady = $bookingService->schemaReady();
} catch (Exception $e) {
    $migrationSummary = ['error' => $e->getMessage()];
    $schemaReady = $bookingService->schemaReady();
    // Debug: Log the actual error
    error_log("Room booking error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
}

// Calendar range configuration
$today = date('Y-m-d');
$calendarDays = 7;
$calendarStart = new DateTimeImmutable($today);
$calendarEndDate = $calendarStart->modify("+{$calendarDays} days");
$calendarEnd = $calendarEndDate->format('Y-m-d');

// Calendar bookings window (today + horizon)

if ($schemaReady) {
    $calendarStmt = $pdo->prepare('
        SELECT b.*, r.room_number, rt.name AS room_type_name
        FROM room_bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.check_in_date < :end_date
          AND b.check_out_date > :start_date
        ORDER BY r.room_number, b.check_in_date
    ');
    $calendarStmt->execute([
        ':start_date' => $today,
        ':end_date' => $calendarEnd,
    ]);
    $calendarBookings = $calendarStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $calendarStmt = $pdo->prepare('
        SELECT b.*, r.room_number, rt.name AS room_type_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.check_in_date < :end_date
          AND b.check_out_date > :start_date
        ORDER BY r.room_number, b.check_in_date
    ');
    $calendarStmt->execute([
        ':start_date' => $today,
        ':end_date' => $calendarEnd,
    ]);
    $calendarBookings = $calendarStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Precompute calendar dates & room lookup
$calendarDates = [];
for ($i = 0; $i < $calendarDays; $i++) {
    $calendarDates[] = $calendarStart->modify("+{$i} days");
}
$calendarDisplayStart = $calendarStart;
$calendarDisplayEnd = $calendarDates ? $calendarDates[count($calendarDates) - 1] : $calendarStart;

$calendarByRoom = [];
foreach ($calendarBookings as $booking) {
    $roomId = (int)($booking['room_id'] ?? 0);
    $calendarByRoom[$roomId][] = $booking;
}

function bookingStatusBadge(string $status): string
{
    return match ($status) {
        'checked_in', 'checked-in' => 'success',
        'checked_out', 'checked-out' => 'secondary',
        'confirmed' => 'info',
        'cancelled' => 'danger',
        'pending' => 'warning',
        default => 'primary',
    };
}

function bookingSpansDate(array $booking, string $date): bool
{
    $checkIn = $booking['check_in_date'] ?? null;
    $checkOut = $booking['check_out_date'] ?? null;
    if (!$checkIn || !$checkOut) {
        return false;
    }
    // Treat checkout as exclusive boundary
    return $date >= $checkIn && $date < $checkOut;
}

// Fetch room types and rooms
$roomTypes = $pdo->query("SELECT * FROM room_types WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$roomsStmt = $pdo->query("
    SELECT r.*, rt.name AS type_name, rt.base_price
    FROM rooms r
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE r.is_active = 1
    ORDER BY r.room_number
");
$rooms = $roomsStmt ? $roomsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$roomStatusCounts = [
    'available' => count(array_filter($rooms, fn($r) => ($r['status'] ?? '') === 'available')),
    'occupied' => count(array_filter($rooms, fn($r) => ($r['status'] ?? '') === 'occupied')),
    'reserved' => count(array_filter($rooms, fn($r) => ($r['status'] ?? '') === 'reserved')),
    'maintenance' => count(array_filter($rooms, fn($r) => ($r['status'] ?? '') === 'maintenance')),
];

$currencyManager = CurrencyManager::getInstance();
$currencySymbol = $currencyManager->getCurrencySymbol();
$currencyCode = $currencyManager->getCurrencyCode();
$currencyJsConfig = $currencyManager->getJavaScriptConfig();

$gatewayProvider = strtolower((string)(settings('payments_gateway_provider') ?? ''));
$isGatewayEnabled = in_array($gatewayProvider, ['relworx', 'pesapal'], true);

// Active bookings using available structure
if ($schemaReady) {
    $activeStmt = $pdo->prepare("
        SELECT b.*, r.room_number, rt.name AS room_type_name, COALESCE(SUM(f.amount), 0) AS folio_balance
        FROM room_bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        LEFT JOIN room_folios f ON f.booking_id = b.id
        WHERE b.status IN ('confirmed','checked_in')
        AND b.check_out_date >= :today
        GROUP BY b.id
        ORDER BY b.check_in_date
    ");
    $activeStmt->execute([':today' => $today]);
    $activeBookings = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $activeStmt = $pdo->prepare("
        SELECT b.*, r.room_number, rt.name AS room_type_name, 0 AS folio_balance
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.booking_status IN ('confirmed', 'checked-in')
        AND b.check_out_date >= :today
        ORDER BY b.check_in_date
    ");
    $activeStmt->execute([':today' => $today]);
    $activeBookings = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
}

$activeBookingsFeed = array_map(function ($booking) {
    $statusRaw = $booking['status'] ?? $booking['booking_status'] ?? 'pending';
    return [
        'id' => (int)($booking['id'] ?? 0),
        'guest_name' => $booking['guest_name'] ?? '',
        'room_number' => $booking['room_number'] ?? '',
        'room_type_name' => $booking['room_type_name'] ?? '',
        'status' => $statusRaw,
        'check_in_date' => $booking['check_in_date'] ?? null,
        'check_out_date' => $booking['check_out_date'] ?? null,
        'total_nights' => (int)($booking['total_nights'] ?? 0),
        'total_amount' => (float)($booking['total_amount'] ?? 0),
        'payment_status' => $booking['payment_status'] ?? 'pending',
    ];
}, $activeBookings);

$roomsLiveConfig = [
    'summary' => [
        'total_rooms' => count($rooms),
        'available' => $roomStatusCounts['available'],
        'occupied' => $roomStatusCounts['occupied'],
        'reserved' => $roomStatusCounts['reserved'],
        'maintenance' => $roomStatusCounts['maintenance'],
        'active_bookings' => count($activeBookings),
        'timestamp' => date(DATE_ATOM),
    ],
    'active_bookings' => array_slice($activeBookingsFeed, 0, 12),
];

$pageTitle = 'Room Booking';
include 'includes/header.php';
?>

<script>
    window.ROOMS_GATEWAY_CONFIG = {
        enabled: <?= $isGatewayEnabled ? 'true' : 'false' ?>,
        provider: '<?= addslashes($gatewayProvider) ?>',
        currency: '<?= addslashes($currencyCode) ?>'
    };
</script>

<script>
    window.ROOMS_LIVE_CONFIG = <?= json_encode($roomsLiveConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<style>
    .rooms-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    .room-summary-grid {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .rooms-calendar-card {
        margin-bottom: var(--spacing-lg);
    }
    .live-sync-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #20c997;
    }
    .live-sync-dot[data-state="syncing"] {
        background: #0d6efd;
        box-shadow: 0 0 0 4px rgba(13,110,253,.2);
    }
    .live-sync-dot[data-state="error"] {
        background: #dc3545;
    }
    .rooms-main-columns {
        display: grid;
        gap: var(--spacing-lg);
    }
    .rooms-main-columns > * {
        min-width: 0;
    }
    .rooms-main-column {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    @media (min-width: 992px) {
        .rooms-main-columns {
            grid-template-columns: minmax(0, 2.5fr) minmax(0, 1.2fr);
            align-items: start;
        }
    }
    .stat-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm);
    }
    .room-card {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 180px;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        box-shadow: var(--shadow-sm);
        background: var(--color-surface);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .room-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
    }
    .room-card[data-status="available"] { border-left: 4px solid var(--color-success); }
    .room-card[data-status="occupied"] { border-left: 4px solid var(--color-danger); }
    .room-card[data-status="reserved"] { border-left: 4px solid var(--color-warning); }
    .room-card[data-status="maintenance"] { border-left: 4px solid var(--color-secondary); }
    .calendar-wrapper {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        background: var(--color-surface);
    }
    .calendar-table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    .calendar-table th,
    .calendar-table td {
        vertical-align: middle;
        position: relative;
        border-right: 1px solid var(--color-border);
    }
    .calendar-table th:last-child,
    .calendar-table td:last-child {
        border-right: none;
    }
    .calendar-cell {
        min-height: 90px;
        padding: 0.5rem 0.25rem;
        background: rgba(13, 110, 253, 0.03);
    }
    .calendar-booking {
        background: rgba(13,110,253,0.12);
        border-left: 3px solid var(--color-primary);
        padding: 0.45rem 0.5rem;
        border-radius: var(--radius-md);
        min-height: 70px;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .calendar-booking.start { border-left-color: var(--color-success); }
    .calendar-booking.end { border-left-color: var(--color-secondary); }
    .calendar-slot {
        opacity: 0.3;
    }
    .badge-sm {
        font-size: 0.7rem;
        padding: 0.2rem 0.65rem;
        border-radius: 999px;
    }
</style>

<div class="container-fluid py-4 rooms-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-building me-2"></i>Room Management</h1>
            <p class="text-muted mb-0">Monitor room availability, bookings, and upcoming calendar at a glance.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary btn-icon" onclick="window.location.reload()">
                <i class="bi bi-arrow-clockwise"></i><span>Refresh</span>
            </button>
            <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#bookingModal" onclick="resetBookingForm()">
                <i class="bi bi-plus-circle"></i><span>New Booking</span>
            </button>
        </div>
    </div>

<?php if ($successMessage): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <div><?= htmlspecialchars($successMessage) ?></div>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div><?= htmlspecialchars($errorMessage) ?></div>
    </div>
<?php endif; ?>

<?php if ($migrationSummary && isset($migrationSummary['error'])): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
            Migration failed: <?= htmlspecialchars($migrationSummary['error']) ?>
        </div>
    </div>
<?php elseif ($migrationSummary && ($migrationSummary['migrated'] ?? 0) > 0): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <div>
            Migrated <?= (int)$migrationSummary['migrated'] ?> legacy bookings to room folio records.
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12">
        <?php
        renderPromotionSpotlight(
            $activePromotions,
            [
                'title' => 'Room & Stay Promotions',
                'description' => 'Long-stay bundles, off-peak rates, and guest perks applied automatically.',
                'icon' => 'bi-door-open',
                'context' => 'rooms',
                'max_items' => 4,
                'show_manage_link' => $canManagePromotions,
            ]
        );
        ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="room-summary-grid">
    <div class="stat-card">
        <div>
            <p class="text-muted mb-1 small">Total Rooms</p>
            <h3 class="mb-0 fw-semibold"><?= count($rooms) ?></h3>
        </div>
        <span class="app-status" data-color="primary"><i class="bi bi-door-open"></i></span>
    </div>
    <div class="stat-card">
        <div>
            <p class="text-muted mb-1 small">Available</p>
            <h3 class="mb-0 fw-semibold text-success">
                <?= count(array_filter($rooms, fn($r) => $r['status'] === 'available')) ?>
            </h3>
        </div>
        <span class="app-status" data-color="success"><i class="bi bi-check-circle"></i></span>
    </div>
    <div class="stat-card">
        <div>
            <p class="text-muted mb-1 small">Occupied</p>
            <h3 class="mb-0 fw-semibold text-danger">
                <?= count(array_filter($rooms, fn($r) => $r['status'] === 'occupied')) ?>
            </h3>
        </div>
        <span class="app-status" data-color="danger"><i class="bi bi-person-fill"></i></span>
    </div>
    <div class="stat-card">
        <div>
            <p class="text-muted mb-1 small">Active Bookings</p>
            <h3 class="mb-0 fw-semibold text-info"><?= count($activeBookings) ?></h3>
        </div>
        <span class="app-status" data-color="info"><i class="bi bi-calendar-check"></i></span>
        </div>
    </div>
</div>

<div class="row g-3 align-items-stretch mb-4">
    <div class="col-lg-4">
        <div class="app-card h-100 d-flex flex-column" aria-live="polite">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <p class="text-muted mb-1 small">Live Occupancy</p>
                    <h4 class="mb-0" id="roomsLiveOccupancyCount">0 / 0</h4>
                </div>
                <div class="text-end">
                    <small class="text-muted">Updated</small>
                    <div class="fw-semibold small" id="roomsLiveOccupancyTime">—</div>
                </div>
            </div>
            <div class="bg-light rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Available</span>
                    <strong class="text-success" id="roomsLiveAvailable">0</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Occupied</span>
                    <strong class="text-danger" id="roomsLiveOccupied">0</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Reserved</span>
                    <strong class="text-warning" id="roomsLiveReserved">0</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Maintenance</span>
                    <strong class="text-secondary" id="roomsLiveMaintenance">0</strong>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="app-card h-100 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="live-sync-dot" id="roomsLiveStatusDot"></div>
                    <span class="fw-semibold">Live Booking Feed</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="roomsLiveToggleBtn" type="button">Pause</button>
                    <button class="btn btn-sm btn-outline-primary" id="roomsLiveRefreshBtn" type="button">Refresh</button>
                </div>
            </div>
            <div class="small text-muted mb-2" id="roomsLiveStatusLabel">Live updates on</div>
            <div class="flex-grow-1" style="overflow-y: auto; max-height: 220px;">
                <ul class="list-group list-group-flush" id="roomsLiveFeed">
                    <li class="list-group-item text-muted">Waiting for updates…</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="app-card calendar-wrapper rooms-calendar-card">
    <div class="section-heading px-3 pt-3">
        <div>
            <h6 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Next 7 Days</h6>
            <small class="text-muted">Updated <?= $today ?></small>
        </div>
        <span class="app-status" data-color="info"><?= $calendarDisplayStart->format('d M') ?> – <?= $calendarDisplayEnd->format('d M') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 calendar-table">
            <thead class="table-light">
                <tr>
                    <th style="width: 160px;">Room</th>
                    <?php foreach ($calendarDates as $dateObj): ?>
                        <th class="text-center" style="min-width: 110px;">
                            <div class="fw-semibold"><?= $dateObj->format('D') ?></div>
                            <small class="text-muted"><?= $dateObj->format('d M') ?></small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                    <?php $roomBookings = $calendarByRoom[$room['id']] ?? []; ?>
                    <tr>
                        <td class="fw-semibold align-middle">
                            <?= htmlspecialchars($room['room_number']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($room['type_name']) ?></small>
                        </td>
                        <?php foreach ($calendarDates as $dateObj): ?>
                            <?php
                                $dateStr = $dateObj->format('Y-m-d');
                                $bookingForDay = null;
                                foreach ($roomBookings as $booking) {
                                    if (bookingSpansDate($booking, $dateStr)) {
                                        $bookingForDay = $booking;
                                        break;
                                    }
                                }
                            ?>
                            <td class="align-middle text-center calendar-cell">
                                <?php if ($bookingForDay): ?>
                                    <?php
                                        $status = $bookingForDay['status'] ?? $bookingForDay['booking_status'] ?? 'confirmed';
                                        $badgeClass = 'badge bg-' . bookingStatusBadge($status);
                                        $isStart = $bookingForDay['check_in_date'] === $dateStr;
                                        $isEnd = $bookingForDay['check_out_date'] === $dateStr;
                                    ?>
                                    <div class="calendar-booking <?= $isStart ? 'start' : '' ?> <?= $isEnd ? 'end' : '' ?>">
                                        <div class="small fw-semibold">
                                            <?= htmlspecialchars($bookingForDay['guest_name'] ?? 'Guest') ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= $isStart ? 'Check-in' : ($isEnd ? 'Check-out' : '') ?>
                                        </div>
                                        <span class="<?= $badgeClass ?> badge-sm"><?= ucfirst(str_replace(['_', '-'], ' ', $status)) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="calendar-slot text-muted small">•</div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="rooms-main-columns">
    <div class="rooms-main-column">
        <div class="app-card">
        <div class="section-heading">
            <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Available Rooms</h6>
            <span class="text-muted small">Click a room to pre-fill the booking form</span>
        </div>
        <div class="row g-3">
            <?php foreach ($rooms as $room): ?>
                <div class="col-md-4">
                    <div class="room-card" data-status="<?= htmlspecialchars($room['status']) ?>" onclick='selectRoom(<?= json_encode($room) ?>)'>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($room['room_number']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($room['type_name']) ?></small>
                            </div>
                            <i class="bi bi-<?= $room['status'] === 'available' ? 'check-circle text-success' : ($room['status'] === 'occupied' ? 'person-fill text-danger' : 'exclamation-circle text-warning') ?> fs-4"></i>
                        </div>
                        <p class="mb-2 small text-muted">
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
            <?php endforeach; ?>
        </div>
    </div>
    <div class="rooms-main-column">
        <div class="app-card h-100 d-flex flex-column">
        <div class="section-heading">
            <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Active Bookings</h6>
            <span class="text-muted small"><?= count($activeBookings) ?> in progress</span>
        </div>
        <div class="flex-grow-1" style="max-height: 600px; overflow-y: auto;">
            <?php if (empty($activeBookings)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">No active bookings</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($activeBookings as $booking): ?>
                        <?php
                            $statusRaw = $booking['status'] ?? $booking['booking_status'] ?? 'pending';
                            $statusLabel = ucwords(str_replace('_', ' ', $statusRaw));
                            $statusBadge = match ($statusRaw) {
                                'checked_in' => 'success',
                                'checked-out', 'checked_out' => 'secondary',
                                'cancelled' => 'danger',
                                'pending' => 'secondary',
                                default => 'warning'
                            };
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($booking['guest_name']) ?></h6>
                                    <p class="mb-1 small text-muted">
                                        <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($booking['room_number']) ?>
                                        - <?= htmlspecialchars($booking['room_type_name']) ?>
                                    </p>
                                </div>
                                <span class="badge bg-<?= $statusBadge ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
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
                                    <strong><?= formatMoney($booking['total_amount']) ?></strong>
                                    <?php if ($booking['payment_status'] !== 'paid'): ?>
                                        <span class="badge bg-warning text-dark ms-2">Pending</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <?php if (($booking['status'] ?? $booking['booking_status'] ?? '') === 'confirmed'): ?>
                                    <button class="btn btn-sm btn-success" onclick="checkIn(<?= $booking['id'] ?>)">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                                    </button>
                                <?php elseif (($booking['status'] ?? $booking['booking_status'] ?? '') === 'checked_in'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="checkOut(<?= $booking['id'] ?>)">
                                        <i class="bi bi-box-arrow-right me-1"></i>Check Out
                                    </button>
                                <?php endif; ?>
                                <a href="room-invoice.php?booking_id=<?= $booking['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-receipt"></i> View Invoice
                                </a>
                                <a href="room-invoice-pdf.php?booking_id=<?= $booking['id'] ?>&download=1" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                                    <i class="bi bi-file-earmark-pdf"></i> Download PDF
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                                        <strong class="text-primary fs-5" id="total_amount">
                                            <?= formatMoney(0, false) ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-1">Deposit / Prepayment</h6>
                                            <small class="text-muted">Record an upfront payment to secure the booking.</small>
                                        </div>
                                        <?php if ($isGatewayEnabled): ?>
                                            <span class="badge bg-info text-dark">Gateway Enabled</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Deposit Amount</label>
                                            <input type="number" class="form-control" name="deposit_amount" id="deposit_amount" min="0" step="0.01" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Deposit Method</label>
                                            <select class="form-select" name="deposit_method" id="deposit_method">
                                                <option value="">Select method</option>
                                                <option value="mobile_money">Mobile Money</option>
                                                <option value="cash">Cash</option>
                                                <option value="card">Card</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Reference</label>
                                            <input type="text" class="form-control" name="deposit_reference" id="deposit_reference" placeholder="Txn ID / receipt">
                                        </div>
                                        <div class="col-md-6" id="depositPhoneWrap" hidden>
                                            <label class="form-label">Customer Phone</label>
                                            <input type="tel" class="form-control" name="deposit_customer_phone" id="deposit_customer_phone" placeholder="e.g. +2567...">
                                            <div class="form-text">Required for mobile money prompts.</div>
                                        </div>
                                        <?php if ($isGatewayEnabled): ?>
                                        <div class="col-md-6">
                                            <div class="alert alert-info py-2 mb-0 small">
                                                Mobile money deposits will trigger a payment prompt to the customer phone.
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="alert alert-info d-none mt-3" id="bookingDepositStatus"></div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="payment_gateway_reference" id="payment_gateway_reference">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bookingSubmitBtn">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Folio Modal Placeholder -->
<div class="modal fade" id="folioModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Room Folio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="folio-loading text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 mb-0 small text-muted">Loading folio details…</p>
                </div>
                <div class="folio-content d-none">
                    <!-- Dynamic table injected via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const currencyConfig = <?= json_encode($currencyJsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const currencyDecimals = Number.isInteger(currencyConfig.decimal_places)
    ? currencyConfig.decimal_places
    : 2;
const ROOMS_GATEWAY = window.ROOMS_GATEWAY_CONFIG || { enabled: false, provider: null, currency: currencyConfig.code };
const ROOMS_LIVE_CONFIG = window.ROOMS_LIVE_CONFIG || {
    summary: {
        total_rooms: 0,
        available: 0,
        occupied: 0,
        reserved: 0,
        maintenance: 0,
        active_bookings: 0,
        timestamp: null,
    },
    active_bookings: []
};
const ROOMS_LIVE_POLL_INTERVAL = 15000;
const ROOMS_LIVE_FETCH_LIMIT = 12;
let bookingGatewayReference = null;
let bookingGatewayPollTimeout = null;
let bookingGatewayPollAttempts = 0;
const BOOKING_GATEWAY_POLL_INTERVAL = 4000;
const BOOKING_GATEWAY_MAX_ATTEMPTS = 30;
const roomsLiveElements = {
    occupancyCount: null,
    updatedAt: null,
    available: null,
    occupied: null,
    reserved: null,
    maintenance: null,
    statusDot: null,
    statusLabel: null,
    toggleBtn: null,
    refreshBtn: null,
    feed: null,
};
const roomsLiveState = {
    paused: false,
    timer: null,
    channel: null,
};

let folioModal;
let folioModalElement;

const depositMethodField = document.getElementById('deposit_method');
const depositPhoneWrap = document.getElementById('depositPhoneWrap');
const depositPhoneField = document.getElementById('deposit_customer_phone');

function handleDepositMethodChange() {
    if (!depositMethodField || !depositPhoneWrap) {
        return;
    }
    const isMobileMoney = depositMethodField.value === 'mobile_money';
    depositPhoneWrap.hidden = !isMobileMoney;
    if (!isMobileMoney && depositPhoneField) {
        depositPhoneField.value = '';
    }
}

depositMethodField?.addEventListener('change', handleDepositMethodChange);
handleDepositMethodChange();

function setBookingStatus(message, type = 'info') {
    const statusEl = document.getElementById('bookingDepositStatus');
    if (!statusEl) return;
    statusEl.className = `alert alert-${type}`;
    statusEl.textContent = message;
    statusEl.classList.remove('d-none');
}

function clearBookingStatus() {
    const statusEl = document.getElementById('bookingDepositStatus');
    if (statusEl) {
        statusEl.classList.add('d-none');
        statusEl.textContent = '';
    }
    if (bookingGatewayPollTimeout) {
        clearTimeout(bookingGatewayPollTimeout);
        bookingGatewayPollTimeout = null;
    }
    bookingGatewayReference = null;
    bookingGatewayPollAttempts = 0;
}

function setBookingSubmitState(label, disabled) {
    const btn = document.getElementById('bookingSubmitBtn');
    if (!btn) return;
    if (label) {
        btn.innerHTML = label;
    }
    if (typeof disabled === 'boolean') {
        btn.disabled = disabled;
    }
}

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
            document.getElementById('total_amount').textContent = formatNeutralAmount(total);
        }
    }
}

function resetBookingForm() {
    document.getElementById('bookingForm').reset();
    document.getElementById('total_nights').textContent = '0';
    document.getElementById('total_amount').textContent = formatNeutralAmount(0);
    handleDepositMethodChange();
}

function initRoomsLiveWidgets() {
    roomsLiveElements.occupancyCount = document.getElementById('roomsLiveOccupancyCount');
    if (!roomsLiveElements.occupancyCount) {
        return;
    }
    roomsLiveElements.updatedAt = document.getElementById('roomsLiveOccupancyTime');
    roomsLiveElements.available = document.getElementById('roomsLiveAvailable');
    roomsLiveElements.occupied = document.getElementById('roomsLiveOccupied');
    roomsLiveElements.reserved = document.getElementById('roomsLiveReserved');
    roomsLiveElements.maintenance = document.getElementById('roomsLiveMaintenance');
    roomsLiveElements.statusDot = document.getElementById('roomsLiveStatusDot');
    roomsLiveElements.statusLabel = document.getElementById('roomsLiveStatusLabel');
    roomsLiveElements.toggleBtn = document.getElementById('roomsLiveToggleBtn');
    roomsLiveElements.refreshBtn = document.getElementById('roomsLiveRefreshBtn');
    roomsLiveElements.feed = document.getElementById('roomsLiveFeed');

    updateRoomsLiveSummary(ROOMS_LIVE_CONFIG.summary || {}, ROOMS_LIVE_CONFIG.summary?.timestamp);
    renderRoomsLiveFeed(ROOMS_LIVE_CONFIG.active_bookings || []);

    roomsLiveElements.toggleBtn?.addEventListener('click', toggleRoomsLive);
    roomsLiveElements.refreshBtn?.addEventListener('click', () => fetchRoomsLiveData(true));

    if ('BroadcastChannel' in window) {
        roomsLiveState.channel = new BroadcastChannel('wapos_rooms_updates');
        roomsLiveState.channel.onmessage = event => {
            if (event.data?.type === 'rooms_live_update') {
                fetchRoomsLiveData(true);
            }
        };
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !roomsLiveState.paused) {
            fetchRoomsLiveData(true);
        }
    });

    fetchRoomsLiveData(true);
}

function toggleRoomsLive() {
    roomsLiveState.paused = !roomsLiveState.paused;
    if (roomsLiveState.paused) {
        clearTimeout(roomsLiveState.timer);
        roomsLiveElements.toggleBtn && (roomsLiveElements.toggleBtn.textContent = 'Resume');
        setRoomsLiveIndicator('paused', 'Updates paused');
    } else {
        roomsLiveElements.toggleBtn && (roomsLiveElements.toggleBtn.textContent = 'Pause');
        fetchRoomsLiveData(true);
    }
}

function setRoomsLiveIndicator(state, label) {
    roomsLiveElements.statusDot?.setAttribute('data-state', state);
    if (roomsLiveElements.statusLabel) {
        roomsLiveElements.statusLabel.textContent = label || '';
    }
}

async function fetchRoomsLiveData(forceImmediate = false) {
    if (roomsLiveState.paused) {
        return;
    }
    clearTimeout(roomsLiveState.timer);
    if (!forceImmediate) {
        setRoomsLiveIndicator('syncing', 'Syncing…');
    }
    try {
        const response = await fetch('api/live-rooms-feed.php', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to load rooms data');
        }
        updateRoomsLiveSummary(data.summary || {}, data.summary?.timestamp);
        renderRoomsLiveFeed(data.active_bookings || []);
        setRoomsLiveIndicator('live', data.summary?.timestamp ? `Updated ${new Date(data.summary.timestamp).toLocaleTimeString()}` : 'Live updates on');
    } catch (error) {
        console.error('Rooms live fetch error:', error);
        setRoomsLiveIndicator('error', 'Sync failed – retrying');
    } finally {
        scheduleRoomsLivePoll();
    }
}

function scheduleRoomsLivePoll() {
    clearTimeout(roomsLiveState.timer);
    roomsLiveState.timer = setTimeout(() => fetchRoomsLiveData(false), ROOMS_LIVE_POLL_INTERVAL);
}

function updateRoomsLiveSummary(summary, timestamp) {
    const totalRooms = Number(summary.total_rooms || 0);
    const occupied = Number(summary.occupied || 0);
    roomsLiveElements.occupancyCount.textContent = `${occupied} / ${totalRooms}`;
    roomsLiveElements.available.textContent = Number(summary.available || 0).toString();
    roomsLiveElements.occupied.textContent = occupied.toString();
    roomsLiveElements.reserved.textContent = Number(summary.reserved || 0).toString();
    roomsLiveElements.maintenance.textContent = Number(summary.maintenance || 0).toString();
    if (roomsLiveElements.updatedAt) {
        roomsLiveElements.updatedAt.textContent = timestamp ? formatRelativeTime(timestamp) : '—';
    }
}

function renderRoomsLiveFeed(bookings) {
    if (!roomsLiveElements.feed) {
        return;
    }
    roomsLiveElements.feed.innerHTML = '';
    if (!Array.isArray(bookings) || bookings.length === 0) {
        const placeholder = document.createElement('li');
        placeholder.className = 'list-group-item text-muted text-center';
        placeholder.textContent = 'No live bookings to display.';
        roomsLiveElements.feed.appendChild(placeholder);
        return;
    }

    bookings.slice(0, ROOMS_LIVE_FETCH_LIMIT).forEach(booking => {
        const listItem = document.createElement('li');
        listItem.className = 'list-group-item d-flex justify-content-between align-items-start gap-3';

        const info = document.createElement('div');
        info.className = 'flex-grow-1';
        const guest = document.createElement('div');
        guest.className = 'fw-semibold';
        guest.textContent = booking.guest_name || 'Guest';
        const roomMeta = document.createElement('div');
        roomMeta.className = 'text-muted small';
        roomMeta.textContent = `${booking.room_number || 'Room'} · ${booking.room_type_name || ''}`;
        const stayMeta = document.createElement('div');
        stayMeta.className = 'small';
        stayMeta.textContent = formatBookingRange(booking);
        info.appendChild(guest);
        info.appendChild(roomMeta);
        info.appendChild(stayMeta);

        const actions = document.createElement('div');
        actions.className = 'text-end';
        const statusBadge = document.createElement('span');
        const badge = getRoomStatusBadge(booking.status);
        statusBadge.className = `badge bg-${badge.class}`;
        statusBadge.textContent = badge.label;
        actions.appendChild(statusBadge);
        const amount = document.createElement('div');
        amount.className = 'text-muted small mt-2';
        amount.textContent = `${formatNeutralAmount(Number(booking.total_amount || 0))}`;
        actions.appendChild(amount);

        listItem.appendChild(info);
        listItem.appendChild(actions);
        roomsLiveElements.feed.appendChild(listItem);
    });
}

function formatBookingRange(booking) {
    const checkIn = booking.check_in_date ? new Date(booking.check_in_date) : null;
    const checkOut = booking.check_out_date ? new Date(booking.check_out_date) : null;
    const nights = booking.total_nights || 0;
    if (!checkIn || Number.isNaN(checkIn.getTime()) || !checkOut || Number.isNaN(checkOut.getTime())) {
        return 'Schedule TBA';
    }
    const options = { month: 'short', day: 'numeric' };
    return `${checkIn.toLocaleDateString(undefined, options)} → ${checkOut.toLocaleDateString(undefined, options)} (${nights} night${nights === 1 ? '' : 's'})`;
}

function getRoomStatusBadge(status) {
    const normalized = (status || '').toLowerCase();
    switch (normalized) {
        case 'checked_in':
        case 'checked-in':
            return { class: 'success', label: 'Checked In' };
        case 'checked_out':
        case 'checked-out':
            return { class: 'secondary', label: 'Checked Out' };
        case 'confirmed':
            return { class: 'info', label: 'Confirmed' };
        case 'cancelled':
            return { class: 'danger', label: 'Cancelled' };
        case 'pending':
            return { class: 'warning', label: 'Pending' };
        default:
            return { class: 'primary', label: normalized ? normalized.replace(/_/g, ' ') : 'Status' };
    }
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

function viewFolio(bookingId) {
    if (!folioModalElement) {
        folioModalElement = document.getElementById('folioModal');
        folioModal = new bootstrap.Modal(folioModalElement);
    }

    const loadingState = folioModalElement.querySelector('.folio-loading');
    const contentState = folioModalElement.querySelector('.folio-content');
    contentState.classList.add('d-none');
    loadingState.classList.remove('d-none');
    loadingState.querySelector('p').textContent = 'Loading folio details…';
    folioModal.show();

    fetch(`api/room-folio.php?id=${bookingId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load folio');
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load folio details');
            }

            const { booking, folio, totals, mode } = data;
            const rows = Array.isArray(folio) && folio.length > 0 ? folio.map(entry => {
                const amount = parseFloat(entry.amount ?? 0);
                const amountClass = amount < 0 ? 'text-success' : '';
                const formattedAmount = formatNeutralAmount(amount);
                const typeLabel = formatFolioType(entry.item_type);
                const date = entry.date_charged ? formatDate(entry.date_charged) : '';
                const details = entry.description || typeLabel;
                return `
                    <tr>
                        <td>${date}</td>
                        <td>${escapeHtml(typeLabel)}</td>
                        <td>${escapeHtml(details)}</td>
                        <td class="text-end ${amountClass}">${formattedAmount}</td>
                    </tr>
                `;
            }).join('') : `
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        <i class="bi bi-journal-minus fs-2 d-block mb-2"></i>
                        No folio entries yet
                    </td>
                </tr>
            `;

            const totalsHtml = `
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1 small">Total Charges</p>
                                <h4 class="mb-0">${formatNeutralAmount(totals.total_charges ?? 0)}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1 small">Payments & Deposits</p>
                                <h4 class="mb-0 text-success">${formatNeutralAmount(totals.total_payments ?? 0)}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1 small">Balance Due</p>
                                <h4 class="mb-0 ${((totals.balance_due ?? 0) > 0.01) ? 'text-danger' : 'text-success'}">
                                    ${formatNeutralAmount(totals.balance_due ?? 0)}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            contentState.innerHTML = `
                ${mode === 'legacy' ? '<div class="alert alert-warning py-2 small">This booking is using the legacy folio format. Upgrade in progress.</div>' : ''}
                ${booking ? `<p class="small text-muted mb-2">Booking #${escapeHtml(booking.booking_number ?? '')} · Room ${escapeHtml(booking.room_number ?? '')}</p>` : ''}
                ${totalsHtml}
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 20%;">Type</th>
                                <th>Description</th>
                                <th class="text-end" style="width: 20%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;

            loadingState.classList.add('d-none');
            contentState.classList.remove('d-none');
        })
        .catch(error => {
            loadingState.querySelector('p').textContent = error.message;
        });
}

function formatFolioType(type) {
    const map = {
        room_charge: 'Room Charge',
        service: 'Service',
        tax: 'Tax',
        deposit: 'Deposit',
        payment: 'Payment',
        adjustment: 'Adjustment'
    };
    return map[type] || (type ? type.replace(/_/g, ' ') : 'Entry');
}

function escapeHtml(str) {
    return (str ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatNeutralAmount(value) {
    const amount = Number(value) || 0;
    return amount.toLocaleString(undefined, {
        minimumFractionDigits: currencyDecimals,
        maximumFractionDigits: currencyDecimals
    });
}

function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) {
            return dateString;
        }
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (err) {
        return dateString;
    }
}

// Form submission
document.getElementById('bookingForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const depositAmount = parseFloat(document.getElementById('deposit_amount').value) || 0;
    const depositMethod = depositMethodField ? depositMethodField.value : '';
    const depositPhone = depositPhoneField ? depositPhoneField.value.trim() : '';

    clearBookingStatus();

    if (depositAmount > 0 && !depositMethod) {
        setBookingStatus('Select a deposit payment method.', 'danger');
        depositMethodField?.focus();
        return;
    }

    const isMobileMoney = depositMethod === 'mobile_money';
    if (depositAmount > 0 && isMobileMoney && depositPhone.length < 7) {
        setBookingStatus('Enter the customer phone number for the mobile money deposit.', 'danger');
        depositPhoneField?.focus();
        return;
    }

    if (ROOMS_GATEWAY.enabled && depositAmount > 0 && isMobileMoney) {
        await initiateBookingDeposit(form, depositAmount, depositPhone);
        return;
    }

    await submitBooking(form);
});

async function initiateBookingDeposit(form, amount, phone) {
    try {
        setBookingStatus('Sending mobile money prompt to customer...', 'info');
        setBookingSubmitState('<i class="bi bi-phone me-2"></i>Waiting for approval', true);

        const payload = {
            amount,
            currency: ROOMS_GATEWAY.currency,
            customer_phone: phone,
            customer_name: form.guest_name.value || null,
            customer_email: form.guest_email.value || null,
            metadata: {
                source: 'room_booking_deposit',
                room_id: form.select_room_id.value,
                check_in: form.check_in_date.value,
                check_out: form.check_out_date.value,
                guest_phone: form.guest_phone.value,
                deposit_form: true
            },
            context_type: 'room_booking',
            context_id: Date.now()
        };

        const response = await fetch('api/payments/initiate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Unable to initiate payment');
        }

        const data = result.data || {};
        bookingGatewayReference = data.reference;
        bookingGatewayPollAttempts = 0;
        document.getElementById('payment_gateway_reference').value = data.reference;

        if (data.instructions) {
            setBookingStatus(data.instructions, 'info');
        } else {
            setBookingStatus('Prompt sent. Waiting for customer confirmation...', 'info');
        }

        if (['success', 'completed', 'paid', 'approved'].includes((data.status || '').toLowerCase())) {
            await submitBooking(form);
            return;
        }

        pollBookingGatewayStatus(form);
    } catch (error) {
        console.error('Booking deposit initiation error:', error);
        setBookingStatus(`Failed to initiate payment: ${error.message}`, 'danger');
        setBookingSubmitState('Create Booking', false);
    }
}

async function pollBookingGatewayStatus(form) {
    if (!bookingGatewayReference) {
        setBookingSubmitState('Create Booking', false);
        return;
    }

    bookingGatewayPollAttempts += 1;
    if (bookingGatewayPollAttempts > BOOKING_GATEWAY_MAX_ATTEMPTS) {
        setBookingStatus('Payment is still pending. Please confirm with the customer or try again.', 'warning');
        setBookingSubmitState('Create Booking', false);
        return;
    }

    try {
        const response = await fetch(`api/payments/status.php?reference=${encodeURIComponent(bookingGatewayReference)}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            throw new Error(result.message || 'Unable to fetch payment status');
        }

        const record = result.data;
        const status = (record.status || '').toLowerCase();

        if (['success', 'completed', 'paid', 'approved'].includes(status)) {
            setBookingStatus('Deposit confirmed! Completing booking...', 'success');
            document.getElementById('payment_gateway_reference').value = record.reference;
            await submitBooking(form);
            return;
        }

        if (['failed', 'declined', 'cancelled', 'expired', 'error'].includes(status)) {
            setBookingStatus(`Payment failed: ${record.status}`, 'danger');
            setBookingSubmitState('Create Booking', false);
            return;
        }

        if (record.instructions) {
            setBookingStatus(record.instructions, 'info');
        } else {
            setBookingStatus('Awaiting customer confirmation...', 'info');
        }

        bookingGatewayPollTimeout = setTimeout(() => {
            pollBookingGatewayStatus(form);
        }, BOOKING_GATEWAY_POLL_INTERVAL);
    } catch (error) {
        console.error('Booking deposit status error:', error);
        setBookingStatus(`Error checking payment status: ${error.message}`, 'danger');
        setBookingSubmitState('Create Booking', false);
    }
}

async function submitBooking(form) {
    setBookingSubmitState('<i class="bi bi-hourglass-split me-2"></i>Submitting...', true);
    try {
        const formData = new FormData(form);
        if (bookingGatewayReference && !formData.get('deposit_amount')) {
            formData.set('deposit_amount', document.getElementById('deposit_amount').value || '0');
        }
        const response = await fetch('api/create-booking.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            clearBookingStatus();
            alert('Booking created successfully!');
            window.location.reload();
        } else {
            setBookingStatus(result.message || 'Failed to create booking', 'danger');
            setBookingSubmitState('Create Booking', false);
        }
    } catch (error) {
        setBookingStatus(error.message, 'danger');
        setBookingSubmitState('Create Booking', false);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
