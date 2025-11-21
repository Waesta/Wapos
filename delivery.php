<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle rider actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_rider') {
        $db->insert('riders', [
            'name' => sanitizeInput($_POST['name']),
            'phone' => sanitizeInput($_POST['phone']),
            'vehicle_type' => sanitizeInput($_POST['vehicle_type']),
            'vehicle_number' => sanitizeInput($_POST['vehicle_number']),
            'status' => 'available'
        ]);
        $_SESSION['success_message'] = 'Rider added successfully';
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get riders
$hasAssignedAtColumn = $db->fetchOne("SHOW COLUMNS FROM deliveries LIKE 'assigned_at'");
$lastAssignedColumn = !empty($hasAssignedAtColumn) ? 'd2.assigned_at' : 'd2.updated_at';

$riders = $db->fetchAll("
    SELECT 
        r.*, 
        (
            SELECT COUNT(*)
            FROM deliveries d
            WHERE d.rider_id = r.id
              AND d.status IN ('assigned', 'picked-up', 'in-transit')
        ) AS active_jobs,
        (
            SELECT MAX({$lastAssignedColumn})
            FROM deliveries d2
            WHERE d2.rider_id = r.id
        ) AS last_assigned_at
    FROM riders r
    WHERE r.is_active = 1
    ORDER BY r.name
");

$deliverySettingKeys = [
    'delivery_max_active_jobs',
    'delivery_sla_pending_limit',
    'delivery_sla_assigned_limit',
    'delivery_sla_delivery_limit',
    'delivery_sla_slack_minutes',
];
$placeholders = implode(',', array_fill(0, count($deliverySettingKeys), '?'));
$deliverySettingsRows = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)",
    $deliverySettingKeys
);
$deliverySettings = [];
foreach ($deliverySettingsRows as $row) {
    $deliverySettings[$row['setting_key']] = $row['setting_value'];
}

$deliveryConfig = [
    'max_active_jobs' => max(1, (int)($deliverySettings['delivery_max_active_jobs'] ?? 3)),
    'sla' => [
        'pending' => max(1, (int)($deliverySettings['delivery_sla_pending_limit'] ?? 15)),
        'assigned' => max(1, (int)($deliverySettings['delivery_sla_assigned_limit'] ?? 10)),
        'delivery' => max(1, (int)($deliverySettings['delivery_sla_delivery_limit'] ?? 45)),
        'slack' => max(0, (int)($deliverySettings['delivery_sla_slack_minutes'] ?? 5)),
    ],
];

$slaTargetsText = sprintf(
    'SLA: pending %d min • assigned %d min • delivery %d min (+%d slack)',
    $deliveryConfig['sla']['pending'],
    $deliveryConfig['sla']['assigned'],
    $deliveryConfig['sla']['delivery'],
    $deliveryConfig['sla']['slack']
);

$availableRiderCount = count(array_filter($riders, fn($r) => strtolower($r['status'] ?? '') === 'available'));
$busyRiderCount = count(array_filter($riders, fn($r) => strtolower($r['status'] ?? '') === 'busy'));
$offlineRiderCount = count(array_filter($riders, fn($r) => !in_array(strtolower($r['status'] ?? ''), ['available', 'busy'], true)));

$currencyConfig = CurrencyManager::getInstance()->getJavaScriptConfig();
$csrfToken = generateCSRFToken();

// Get delivery orders
$deliveryOrders = $db->fetchAll("
    SELECT 
        o.*,
        d.status as delivery_status,
        d.rider_id,
        r.name as rider_name,
        r.phone as rider_phone
    FROM orders o
    LEFT JOIN deliveries d ON o.id = d.order_id
    LEFT JOIN riders r ON d.rider_id = r.id
    WHERE o.order_type = 'delivery'
    AND o.status NOT IN ('completed', 'cancelled')
    ORDER BY o.created_at DESC
");

// Get pending deliveries
$pendingDeliveries = $db->fetchAll("
    SELECT 
        d.*,
        o.order_number,
        o.total_amount,
        o.customer_name,
        o.customer_phone,
        o.created_at as order_time,
        r.name as rider_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN riders r ON d.rider_id = r.id
    WHERE d.status NOT IN ('delivered', 'failed')
    ORDER BY d.created_at DESC
");

$pageTitle = 'Delivery Management';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 2000;"></div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Delivery Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#riderModal">
        <i class="bi bi-plus-circle me-2"></i>Add Rider
    </button>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Active Riders</p>
                        <h3 class="mb-0 fw-bold" id="activeRidersCount">
                            <?= count(array_filter($riders, fn($r) => ($r['status'] ?? '') === 'available')) ?>
                        </h3>
                    </div>
                    <i class="bi bi-person-check text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Pending Orders</p>
                        <h3 class="mb-0 fw-bold text-warning" id="pendingOrdersCount"><?= count($deliveryOrders) ?></h3>
                        <div class="d-flex flex-column gap-1">
                            <div class="d-flex align-items-center gap-2">
                                <p class="mb-0 small text-muted" id="pendingBreakdown"></p>
                                <span class="badge bg-danger-subtle text-danger small" id="slaRiskBadge" style="display:none;">0 SLA risk</span>
                            </div>
                            <span class="small text-muted" id="slaTargetsLabel"><?= htmlspecialchars($slaTargetsText) ?></span>
                        </div>
                    </div>
                    <i class="bi bi-clock-history text-warning fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">In Transit</p>
                        <h3 class="mb-0 fw-bold text-info" id="inTransitCount">
                            <?= count(array_filter($pendingDeliveries, fn($d) => ($d['status'] ?? '') === 'in-transit')) ?>
                        </h3>
                        <p class="mb-0 small text-muted" id="distanceSummary"></p>
                    </div>
                    <i class="bi bi-truck text-info fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Today Delivered</p>
                        <h3 class="mb-0 fw-bold text-success" id="todayDeliveredCount">
                            <?php 
                            $todayDelivered = $db->fetchOne("
                                SELECT COUNT(*) as count FROM deliveries 
                                WHERE status = 'delivered' 
                                AND DATE(actual_delivery_time) = CURDATE()
                            ");
                            echo $todayDelivered['count'] ?? 0;
                            ?>
                        </h3>
                    </div>
                    <i class="bi bi-check-circle text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label for="riderSearchInput" class="form-label text-muted small mb-1">Search Riders</label>
                <input type="search" class="form-control" id="riderSearchInput" placeholder="Name, phone, or vehicle">
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="riderStatusFilter" class="form-label text-muted small mb-1">Rider Status</label>
                <select class="form-select" id="riderStatusFilter">
                    <option value="all">All statuses</option>
                    <option value="available">Available</option>
                    <option value="busy">Busy</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="riderSortSelect" class="form-label text-muted small mb-1">Sort Riders</label>
                <select class="form-select" id="riderSortSelect">
                    <option value="workload">Workload (ascending)</option>
                    <option value="rating">Rating</option>
                    <option value="name">Name</option>
                    <option value="recent">Recently assigned</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="maxRiderWorkload" class="form-label text-muted small mb-1">Max Active Jobs</label>
                <select class="form-select" id="maxRiderWorkload">
                    <option value="all">Any</option>
                    <option value="0">0</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3+</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 text-lg-end">
                <button class="btn btn-outline-secondary mt-3 mt-lg-0" id="clearRiderFiltersBtn">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Rider Filters
                </button>
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="orderSearchInput" class="form-label text-muted small mb-1">Search Delivery Orders</label>
                <input type="search" class="form-control" id="orderSearchInput" placeholder="Order #, customer, phone, zone">
            </div>
            <div class="col-lg-3 col-md-3">
                <label for="orderStatusFilter" class="form-label text-muted small mb-1">Order Status</label>
                <select class="form-select" id="orderStatusFilter">
                    <option value="all">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="picked-up">Picked Up</option>
                    <option value="in-transit">In Transit</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-3">
                <label for="orderSortSelect" class="form-label text-muted small mb-1">Sort Orders</label>
                <select class="form-select" id="orderSortSelect">
                    <option value="age_desc">Longest waiting</option>
                    <option value="age_asc">Newest</option>
                    <option value="amount_desc">Amount (high → low)</option>
                    <option value="amount_asc">Amount (low → high)</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <div class="form-check mt-4 pt-2">
                    <input class="form-check-input" type="checkbox" id="orderUnassignedOnly">
                    <label class="form-check-label small" for="orderUnassignedOnly">
                        Show unassigned only
                    </label>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <div class="form-check mt-4 pt-2">
                    <input class="form-check-input" type="checkbox" id="orderSlaRiskOnly">
                    <label class="form-check-label small" for="orderSlaRiskOnly">
                        Show SLA risk only
                    </label>
                </div>
            </div>
            <div class="col-lg-12 text-lg-end">
                <button class="btn btn-outline-secondary" id="clearOrderFiltersBtn">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Order Filters
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Riders List -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Rider Workload</h6>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="badge bg-success-subtle text-success" id="ridersAvailableBadge">Avail: <?= $availableRiderCount ?></span>
                    <span class="badge bg-warning-subtle text-warning" id="ridersBusyBadge">Busy: <?= $busyRiderCount ?></span>
                    <span class="badge bg-secondary-subtle text-secondary" id="ridersOfflineBadge">Offline: <?= $offlineRiderCount ?></span>
                    <span class="badge bg-primary-subtle text-primary" id="maxActiveJobsBadge">Max active: <?= $deliveryConfig['max_active_jobs'] ?></span>
                    <span class="badge bg-info-subtle text-info" id="avgIdleBadge">Avg idle: --</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="ridersList">
                    <?php foreach ($riders as $rider): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($rider['name']) ?></h6>
                                <p class="mb-1 small text-muted">
                                    <i class="bi bi-phone me-1"></i><?= htmlspecialchars($rider['phone']) ?>
                                </p>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-scooter me-1"></i><?= htmlspecialchars($rider['vehicle_type']) ?> 
                                    <?= htmlspecialchars($rider['vehicle_number']) ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?= ($rider['status'] ?? '') === 'available' ? 'success' : (($rider['status'] ?? '') === 'busy' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($rider['status'] ?? 'unknown') ?>
                                </span>
                                <p class="mb-0 small text-muted mt-1">
                                    <?= (int)($rider['total_deliveries'] ?? 0) ?> deliveries
                                </p>
                                <p class="mb-0 small">
                                    <i class="bi bi-star-fill text-warning"></i> <?= number_format((float)($rider['rating'] ?? 0), 1) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Orders -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Delivery Orders</h6>
            </div>
            <div class="card-body">
                <?php if (empty($deliveryOrders)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No delivery orders</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Distance</th>
                                    <th>Zone</th>
                                    <th>Amount</th>
                                    <th>Rider</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                                <?php foreach ($deliveryOrders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['customer_phone'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if (isset($order['estimated_distance_km'])): ?>
                                            <?= number_format((float)$order['estimated_distance_km'], 2) ?> km
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['delivery_zone_name'])): ?>
                                            <?= htmlspecialchars($order['delivery_zone_name']) ?>
                                        <?php elseif (!empty($order['delivery_zone_id'])): ?>
                                            Zone #<?= htmlspecialchars($order['delivery_zone_id']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= formatMoney($order['total_amount'], true) ?></td>
                                    <td>
                                        <?php if ($order['rider_name']): ?>
                                            <?= htmlspecialchars($order['rider_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            ($order['delivery_status'] ?? '') === 'delivered' ? 'success' : 
                                            (($order['delivery_status'] ?? '') === 'in-transit' ? 'info' : 'warning') 
                                        ?>">
                                            <?= ucfirst(str_replace('-', ' ', $order['delivery_status'] ?? 'pending')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (empty($order['rider_id'])): ?>
                                            <button class="btn btn-sm btn-primary" onclick="assignRider(<?= $order['id'] ?>)">
                                                <i class="bi bi-person-plus"></i> Assign
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="trackDelivery(<?= $order['id'] ?>)">
                                                <i class="bi bi-geo-alt"></i> Track
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rider Modal -->
<div class="modal fade" id="riderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_rider">
                <div class="modal-header">
                    <h5 class="modal-title">Add Delivery Rider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rider Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Type *</label>
                        <select class="form-select" name="vehicle_type" required>
                            <option value="">Select...</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Car">Car</option>
                            <option value="Van">Van</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Number</label>
                        <input type="text" class="form-control" name="vehicle_number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Rider Modal -->
<div class="modal fade" id="assignRiderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Rider to Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Available Rider</label>
                    <select class="form-select" id="selectedRider">
                        <option value="">Choose rider...</option>
                        <?php foreach (array_filter($riders, fn($r) => ($r['status'] ?? '') === 'available') as $rider): ?>
                            <option value="<?= $rider['id'] ?>"><?= htmlspecialchars($rider['name']) ?> - <?= htmlspecialchars($rider['vehicle_type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="recommendedRiderHint" style="display:none;"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Estimated Delivery Time</label>
                    <input type="datetime-local" class="form-control" id="estimatedTime" 
                           value="<?= date('Y-m-d\TH:i', strtotime('+30 minutes')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Delivery Notes</label>
                    <textarea class="form-control" id="deliveryNotes" rows="2" placeholder="Special delivery instructions..."></textarea>
                </div>
                <div class="alert alert-light border small" id="assignmentOrderSummary" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmAssignment()">Assign Rider</button>
            </div>
        </div>
    </div>
</div>

<!-- Delivery Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delivery Tracking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="trackingContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading tracking information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
const initialRiders = <?= json_encode($riders) ?>;
const initialOrders = <?= json_encode($deliveryOrders) ?>;
const deliveryConfig = <?= json_encode($deliveryConfig) ?>;

let currentOrderId = null;
let latestRiders = Array.isArray(initialRiders) ? initialRiders : [];
let supportsDeliveryMetrics = false;
const currencyConfig = <?= json_encode($currencyConfig) ?>;
const deliveryCsrfToken = '<?= $csrfToken ?>';

let latestOrders = Array.isArray(initialOrders) ? initialOrders : [];
let MAX_ACTIVE_JOBS = deliveryConfig.max_active_jobs ?? 3;
let riderFilters = {
    search: '',
    status: 'all',
    sort: 'workload',
    maxWorkload: 'all'
};

let orderFilters = {
    search: '',
    status: 'all',
    sort: 'age_desc',
    unassignedOnly: false,
    slaRiskOnly: false
};

let lastSlaRiskIds = new Set();

function isRiderAssignable(rider) {
    const status = (rider.status || '').toLowerCase();
    if (status === 'available') {
        return true;
    }
    return rider.activeJobs < MAX_ACTIVE_JOBS;
}

function registerFilterControls() {
    const riderSearchInput = document.getElementById('riderSearchInput');
    const riderStatusFilter = document.getElementById('riderStatusFilter');
    const riderSortSelect = document.getElementById('riderSortSelect');
    const maxRiderWorkload = document.getElementById('maxRiderWorkload');
    const clearRiderFiltersBtn = document.getElementById('clearRiderFiltersBtn');

    if (riderSearchInput) {
        riderSearchInput.addEventListener('input', debounce(event => {
            riderFilters.search = event.target.value.trim().toLowerCase();
            renderRiders(latestRiders);
        }, 150));
    }

    if (riderStatusFilter) {
        riderStatusFilter.addEventListener('change', event => {
            riderFilters.status = event.target.value;
            renderRiders(latestRiders);
        });
    }

    if (riderSortSelect) {
        riderSortSelect.addEventListener('change', event => {
            riderFilters.sort = event.target.value;
            renderRiders(latestRiders);
        });
    }

    if (maxRiderWorkload) {
        maxRiderWorkload.addEventListener('change', event => {
            riderFilters.maxWorkload = event.target.value;
            renderRiders(latestRiders);
        });
    }

    if (clearRiderFiltersBtn) {
        clearRiderFiltersBtn.addEventListener('click', () => {
            riderFilters = { search: '', status: 'all', sort: 'workload', maxWorkload: 'all' };
            if (riderSearchInput) riderSearchInput.value = '';
            if (riderStatusFilter) riderStatusFilter.value = 'all';
            if (riderSortSelect) riderSortSelect.value = 'workload';
            if (maxRiderWorkload) maxRiderWorkload.value = 'all';
            renderRiders(latestRiders);
        });
    }

    const orderSearchInput = document.getElementById('orderSearchInput');
    const orderStatusFilter = document.getElementById('orderStatusFilter');
    const orderSortSelect = document.getElementById('orderSortSelect');
    const orderUnassignedOnly = document.getElementById('orderUnassignedOnly');
    const orderSlaRiskOnly = document.getElementById('orderSlaRiskOnly');
    const clearOrderFiltersBtn = document.getElementById('clearOrderFiltersBtn');

    if (orderSearchInput) {
        orderSearchInput.addEventListener('input', debounce(event => {
            orderFilters.search = event.target.value.trim().toLowerCase();
            renderOrders(latestOrders);
        }, 150));
    }

    if (orderStatusFilter) {
        orderStatusFilter.addEventListener('change', event => {
            orderFilters.status = event.target.value;
            renderOrders(latestOrders);
        });
    }

    if (orderSortSelect) {
        orderSortSelect.addEventListener('change', event => {
            orderFilters.sort = event.target.value;
            renderOrders(latestOrders);
        });
    }

    if (orderUnassignedOnly) {
        orderUnassignedOnly.addEventListener('change', event => {
            orderFilters.unassignedOnly = event.target.checked;
            renderOrders(latestOrders);
        });
    }

    if (orderSlaRiskOnly) {
        orderSlaRiskOnly.addEventListener('change', event => {
            orderFilters.slaRiskOnly = event.target.checked;
            renderOrders(latestOrders);
        });
    }

    if (clearOrderFiltersBtn) {
        clearOrderFiltersBtn.addEventListener('click', () => {
            orderFilters = { search: '', status: 'all', sort: 'age_desc', unassignedOnly: false, slaRiskOnly: false };
            if (orderSearchInput) orderSearchInput.value = '';
            if (orderStatusFilter) orderStatusFilter.value = 'all';
            if (orderSortSelect) orderSortSelect.value = 'age_desc';
            if (orderUnassignedOnly) orderUnassignedOnly.checked = false;
            if (orderSlaRiskOnly) orderSlaRiskOnly.checked = false;
            renderOrders(latestOrders);
        });
    }
}

function debounce(fn, delay) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

function formatCurrencyValue(value, withSymbol = true) {
    const amount = Number(value || 0);
    const decimals = Number(currencyConfig.decimal_places ?? 2);
    const formatted = amount.toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });

    if (!withSymbol || !currencyConfig.symbol) {
        return formatted;
    }

    const symbol = currencyConfig.symbol;
    return (currencyConfig.position === 'after') ? `${formatted} ${symbol}` : `${symbol} ${formatted}`;
}

function assignRider(orderId) {
    currentOrderId = orderId;
    const order = findOrderById(orderId);
    populateRiderSelect(order);
    updateAssignmentSummary(order);
    new bootstrap.Modal(document.getElementById('assignRiderModal')).show();
}

function getRecommendedRider(order, candidateList) {
    const normalizedCandidates = (candidateList && candidateList.length ? candidateList : (latestRiders || []).map(normalizeRider))
        .filter(isRiderAssignable);

    if (order && Array.isArray(order.recommended_riders) && order.recommended_riders.length) {
        const recommendedId = Number(order.recommended_rider_id ?? order.recommended_riders[0].rider_id);
        const recommendationMeta = order.recommended_riders.find(rec => Number(rec.rider_id) === recommendedId) || order.recommended_riders[0];

        let matched = normalizedCandidates.find(rider => Number(rider.id) === recommendedId);
        if (!matched && latestRiders) {
            const direct = latestRiders.find(rider => Number(rider.id) === recommendedId);
            if (direct) {
                matched = normalizeRider(direct);
            }
        }

        if (matched) {
            return {
                ...matched,
                recommendation: recommendationMeta
            };
        }
    }

    if (!normalizedCandidates.length) {
        return null;
    }

    const fallback = [...normalizedCandidates].sort((a, b) => {
        if (a.activeJobs !== b.activeJobs) {
            return a.activeJobs - b.activeJobs;
        }
        if (b.rating !== a.rating) {
            return b.rating - a.rating;
        }
        const aLast = a.lastAssignedAt ? new Date(a.lastAssignedAt).getTime() : 0;
        const bLast = b.lastAssignedAt ? new Date(b.lastAssignedAt).getTime() : 0;
        if (aLast !== bLast) {
            return aLast - bLast;
        }
        return a.name.localeCompare(b.name);
    })[0];

    return fallback || null;
}

function populateRiderSelect(order) {
    const select = document.getElementById('selectedRider');
    const assignableRiders = (latestRiders || []).map(normalizeRider).filter(isRiderAssignable);

    assignableRiders.sort((a, b) => {
        if (a.activeJobs !== b.activeJobs) {
            return a.activeJobs - b.activeJobs;
        }
        if (b.rating !== a.rating) {
            return b.rating - a.rating;
        }
        const aLast = a.lastAssignedAt ? new Date(a.lastAssignedAt).getTime() : 0;
        const bLast = b.lastAssignedAt ? new Date(b.lastAssignedAt).getTime() : 0;
        if (aLast !== bLast) {
            return aLast - bLast;
        }
        return a.name.localeCompare(b.name);
    });

    const recommended = getRecommendedRider(order, assignableRiders);

    select.innerHTML = '<option value="">Choose rider...</option>' + assignableRiders.map(rider => (
        `<option value="${rider.id}">${escapeHtml(rider.name)} - ${escapeHtml(rider.vehicle_type || 'N/A')} (${rider.activeJobs} active)</option>`
    )).join('');

    if (recommended) {
        select.value = String(recommended.id);
    } else {
        select.value = '';
    }

    updateRecommendedHint(order, recommended);

    return recommended;
}

function updateRecommendedHint(order, recommended) {
    const hint = document.getElementById('recommendedRiderHint');
    if (!hint) {
        return;
    }

    if (!recommended) {
        hint.textContent = '';
        hint.style.display = 'none';
        return;
    }

    const metrics = [];
    metrics.push(`${recommended.activeJobs} active job${recommended.activeJobs === 1 ? '' : 's'}`);

    if (recommended.rating) {
        metrics.push(`rating ${(Number(recommended.rating)).toFixed(1)}`);
    }

    if (recommended.lastAssignedAt) {
        const relative = formatRelativeTimeFromNow(recommended.lastAssignedAt);
        if (relative) {
            metrics.push(`last assigned ${relative}`);
        }
    }

    if (recommended.recommendation) {
        const score = recommended.recommendation.score ? Number(recommended.recommendation.score).toFixed(1) : null;
        if (score) {
            metrics.push(`score ${score}`);
        }
        if (recommended.recommendation.distance_display) {
            metrics.push(`distance ${recommended.recommendation.distance_display}`);
        }
        if (recommended.recommendation.idle_display) {
            metrics.push(recommended.recommendation.idle_display);
        }
    }

    const sla = order.sla || {};
    const slack = deliveryConfig.sla.slack;
    const slaBadge = `<span class="badge bg-${sla.is_at_risk ? 'danger' : (sla.is_late ? 'warning' : 'secondary')}">SLA ${sla.is_at_risk ? 'risk' : (sla.is_late ? 'late' : 'ok')}</span>`;

    const orderLabel = order ? ` for ${escapeHtml(order.order_number || `Order #${order.order_id || order.id}`)}` : '';
    hint.innerHTML = `Suggested rider${orderLabel}: <strong>${escapeHtml(recommended.name)}</strong> (${metrics.join(' · ')}) ${slaBadge} (+${slack} min slack)`;
    hint.style.display = 'block';
}

async function confirmAssignment() {
    const riderId = document.getElementById('selectedRider').value;
    const estimatedTime = document.getElementById('estimatedTime').value;
    const notes = document.getElementById('deliveryNotes').value;
    
    if (!riderId) {
        alert('Please select a rider');
        return;
    }
    
    try {
        const response = await fetch('api/assign-delivery-rider.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: currentOrderId,
                rider_id: riderId,
                estimated_time: estimatedTime,
                notes: notes,
                csrf_token: deliveryCsrfToken
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignRiderModal')).hide();
            document.getElementById('deliveryNotes').value = '';
            refreshDeliveryData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function trackDelivery(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('trackingModal'));
    modal.show();
    document.getElementById('trackingContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading tracking information...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`api/get-delivery-tracking.php?order_id=${orderId}`);
        const result = await response.json();
        
        if (result.success) {
            const tracking = result.data;
            const timelineHtml = buildTimeline(tracking.timeline || []);
            document.getElementById('trackingContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <p><strong>Order #:</strong> ${tracking.order_number}</p>
                        <p><strong>Customer:</strong> ${tracking.customer_name || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${tracking.customer_phone || 'N/A'}</p>
                        <p><strong>Address:</strong> ${tracking.delivery_address || 'N/A'}</p>
                        <p><strong>Amount:</strong> ${formatCurrencyValue(tracking.total_amount, true)}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Delivery Status</h6>
                        <p><strong>Rider:</strong> ${tracking.rider_name || 'Not assigned'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${getStatusBadgeColor(tracking.status)}">${capitalize(tracking.status)}</span></p>
                        <p><strong>Estimated Time:</strong> ${formatDateTime(tracking.estimated_time) || 'N/A'}</p>
                        <p><strong>Order Time:</strong> ${formatDateTime(tracking.created_at) || 'N/A'}</p>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>Delivery Timeline</h6>
                    ${timelineHtml}
                </div>
                ${tracking.status !== 'delivered' && tracking.rider_name ? `
                <div class="mt-3 text-center">
                    <button class="btn btn-success me-2" onclick="updateDeliveryStatus(${orderId}, 'picked-up')">Mark as Picked Up</button>
                    <button class="btn btn-info me-2" onclick="updateDeliveryStatus(${orderId}, 'in-transit')">Mark as In Transit</button>
                    <button class="btn btn-primary" onclick="updateDeliveryStatus(${orderId}, 'delivered')">Mark as Delivered</button>
                </div>
                ` : ''}
            `;
        } else {
            document.getElementById('trackingContent').innerHTML = `
                <div class="alert alert-danger">
                    Error loading tracking information: ${escapeHtml(result.message)}
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('trackingContent').innerHTML = `
            <div class="alert alert-danger">
                Error: ${escapeHtml(error.message)}
            </div>
        `;
    }
}

function buildTimeline(entries) {
    if (!entries.length) {
        return '<div class="alert alert-secondary">No timeline activity recorded yet.</div>';
    }
    return `
        <ul class="list-group">
            ${entries.map(entry => `
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold">${capitalize(entry.status)}</div>
                        <small class="text-muted">${escapeHtml(entry.notes || 'No notes provided')}</small>
                    </div>
                    <span class="badge bg-secondary rounded-pill">${formatDateTime(entry.created_at)}</span>
                </li>
            `).join('')}
        </ul>
    `;
}

async function updateDeliveryStatus(orderId, status) {
    try {
        const response = await fetch('api/update-delivery-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('trackingModal')).hide();
            refreshDeliveryData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function refreshDeliveryData() {
    try {
        const response = await fetch('api/get-delivery-dashboard-data.php');
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch delivery data');
        }
        supportsDeliveryMetrics = Boolean(data.supportsDeliveryMetrics);
        latestRiders = Array.isArray(data.riders) ? data.riders : latestRiders;
        if (data.config) {
            if (typeof data.config.max_active_jobs !== 'undefined') {
                MAX_ACTIVE_JOBS = Number(data.config.max_active_jobs) || MAX_ACTIVE_JOBS;
            }
            if (data.config.sla) {
                deliveryConfig.sla = {
                    pending: Number(data.config.sla.pending_minutes_limit) || deliveryConfig.sla.pending,
                    assigned: Number(data.config.sla.assigned_minutes_limit) || deliveryConfig.sla.assigned,
                    delivery: Number(data.config.sla.delivery_minutes_limit) || deliveryConfig.sla.delivery,
                    slack: Number(data.config.sla.slack_minutes) || deliveryConfig.sla.slack,
                };
            }
        }
        updateStatsFromApi(data.stats || {});
        renderRiders(latestRiders);
        const refreshedOrders = (data.deliveries || data.orders || []).map(delivery => ({
            ...delivery,
            delivery_status: delivery.delivery_status ?? delivery.status ?? 'pending'
        }));
        renderOrders(refreshedOrders);

        handleSlaNotifications(refreshedOrders);

        if (currentOrderId) {
            const currentOrder = findOrderById(currentOrderId);
            updateAssignmentSummary(currentOrder);
            updateRecommendedHint(currentOrder, getRecommendedRider(currentOrder));
        }
    } catch (error) {
        console.error('Refresh error:', error);
    }
}

function updateStatsFromApi(stats) {
    const pendingOrders = stats.pending_deliveries ?? stats.pending_active ?? stats.active_deliveries_current ?? 0;
    document.getElementById('pendingOrdersCount').textContent = pendingOrders;
    const pendingBreakdown = document.getElementById('pendingBreakdown');
    if (pendingBreakdown) {
        const activeQueue = stats.active_deliveries_current ?? null;
        const pendingOnly = stats.pending_active ?? null;
        if (pendingOnly !== null && activeQueue !== null) {
            pendingBreakdown.textContent = `Queue: ${pendingOnly} pending / ${activeQueue} active`;
        } else if (activeQueue !== null) {
            pendingBreakdown.textContent = `Active deliveries: ${activeQueue}`;
        } else {
            pendingBreakdown.textContent = '';
        }
    }

    const slaRiskBadge = document.getElementById('slaRiskBadge');
    if (slaRiskBadge) {
        const riskCount = stats.orders_at_risk ?? stats.orders_overdue ?? 0;
        if (riskCount > 0) {
            slaRiskBadge.textContent = `${riskCount} SLA risk`;
            slaRiskBadge.style.display = 'inline-flex';
        } else {
            slaRiskBadge.style.display = 'none';
        }
    }

    const inTransit = stats.in_transit_active ?? stats.in_transit ?? 0;
    document.getElementById('inTransitCount').textContent = inTransit;
    const distanceSummary = document.getElementById('distanceSummary');
    if (distanceSummary) {
        if (supportsDeliveryMetrics && typeof stats.average_distance_km !== 'undefined') {
            distanceSummary.textContent = `Avg distance: ${Number(stats.average_distance_km).toFixed(2)} km`;
        } else {
            distanceSummary.textContent = '';
        }
    }

    document.getElementById('activeRidersCount').textContent = stats.active_riders ?? 0;
    document.getElementById('todayDeliveredCount').textContent = stats.completed_today ?? stats.today_delivered ?? 0;

    const availBadge = document.getElementById('ridersAvailableBadge');
    if (availBadge && typeof stats.riders_available !== 'undefined') {
        availBadge.textContent = `Avail: ${stats.riders_available}`;
    }

    const busyBadge = document.getElementById('ridersBusyBadge');
    if (busyBadge && typeof stats.riders_busy !== 'undefined') {
        busyBadge.textContent = `Busy: ${stats.riders_busy}`;
    }

    const offlineBadge = document.getElementById('ridersOfflineBadge');
    if (offlineBadge && typeof stats.riders_offline !== 'undefined') {
        offlineBadge.textContent = `Offline: ${stats.riders_offline}`;
    }

    const avgIdleBadge = document.getElementById('avgIdleBadge');
    if (avgIdleBadge) {
        if (typeof stats.avg_idle_minutes !== 'undefined') {
            const minutes = Number(stats.avg_idle_minutes);
            avgIdleBadge.textContent = `Avg idle: ${formatMinutesLabel(minutes)}`;
            avgIdleBadge.style.display = 'inline-flex';
        } else {
            avgIdleBadge.textContent = 'Avg idle: --';
        }
    }

    const maxActiveBadge = document.getElementById('maxActiveJobsBadge');
    if (maxActiveBadge) {
        maxActiveBadge.textContent = `Max active: ${MAX_ACTIVE_JOBS}`;
    }

    const slaLabel = document.getElementById('slaTargetsLabel');
    if (slaLabel && deliveryConfig.sla) {
        const slack = deliveryConfig.sla.slack;
        slaLabel.textContent = `SLA: pending ${deliveryConfig.sla.pending} min • assigned ${deliveryConfig.sla.assigned} min • delivery ${deliveryConfig.sla.delivery} min (+${slack} min slack)`;
    }
}

function normalizeRider(rider) {
    const activeJobs = Number(rider.active_jobs ?? rider.active_deliveries ?? rider.current_jobs ?? 0);
    const lastAssignedAt = rider.last_assigned_at || rider.assigned_at || rider.last_assignment || null;
    return {
        ...rider,
        activeJobs,
        lastAssignedAt,
        idleMinutes: typeof rider.idle_minutes !== 'undefined' ? rider.idle_minutes : null,
        rating: Number(rider.rating ?? 0)
    };
}

function handleSlaNotifications(orders) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        return;
    }

    const currentRiskIds = new Set();
    const newAlerts = [];

    (orders || []).forEach(order => {
        const sla = order.sla || {};
        if (sla.is_at_risk || sla.is_late) {
            const id = order.order_id || order.id;
            currentRiskIds.add(id);
            if (!lastSlaRiskIds.has(id)) {
                newAlerts.push({
                    id,
                    orderNumber: order.order_number || id,
                    customer: order.customer_name || 'Customer',
                    delay: sla.delay_minutes || 0
                });
            }
        }
    });

    newAlerts.slice(0, 3).forEach(alert => {
        const div = document.createElement('div');
        div.className = 'alert alert-danger alert-dismissible fade show';
        div.innerHTML = `
            <strong>SLA risk:</strong> Order #${escapeHtml(alert.orderNumber)} (${escapeHtml(alert.customer)})
            ${alert.delay ? ` · ${alert.delay} min overdue` : ''}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertContainer.append(div);
        setTimeout(() => div.classList.remove('show'), 5000);
        setTimeout(() => div.remove(), 5500);
    });

    lastSlaRiskIds = currentRiskIds;
}

function renderRiders(riders) {
    const list = document.getElementById('ridersList');
    if (!riders.length) {
        list.innerHTML = '<div class="list-group-item text-muted">No riders found.</div>';
        return;
    }
    const filtered = riders
        .map(normalizeRider)
        .filter(rider => {
            const matchesSearch = riderFilters.search === ''
                || (rider.name && rider.name.toLowerCase().includes(riderFilters.search))
                || (rider.phone && rider.phone.toLowerCase().includes(riderFilters.search))
                || (rider.vehicle_type && rider.vehicle_type.toLowerCase().includes(riderFilters.search))
                || (rider.vehicle_number && rider.vehicle_number.toLowerCase().includes(riderFilters.search));

            const matchesStatus = riderFilters.status === 'all' || (rider.status || '').toLowerCase() === riderFilters.status;

            let matchesWorkload = true;
            if (riderFilters.maxWorkload !== 'all') {
                const threshold = Number(riderFilters.maxWorkload);
                matchesWorkload = rider.activeJobs <= threshold;
            }

            return matchesSearch && matchesStatus && matchesWorkload;
        })
        .sort((a, b) => {
            switch (riderFilters.sort) {
                case 'rating':
                    return (Number(b.rating || 0) - Number(a.rating || 0)) || a.name.localeCompare(b.name);
                case 'name':
                    return a.name.localeCompare(b.name);
                case 'recent':
                    const aLast = a.lastAssignedAt ? new Date(a.lastAssignedAt).getTime() : 0;
                    const bLast = b.lastAssignedAt ? new Date(b.lastAssignedAt).getTime() : 0;
                    if (aLast !== bLast) {
                        return aLast - bLast;
                    }
                    return (b.activeJobs - a.activeJobs);
                case 'workload':
                default:
                    return (a.activeJobs - b.activeJobs)
                        || (Number(b.rating || 0) - Number(a.rating || 0))
                        || a.name.localeCompare(b.name);
            }
        });

    list.innerHTML = filtered.map(rider => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${escapeHtml(rider.name)}</h6>
                    <p class="mb-1 small text-muted"><i class="bi bi-phone me-1"></i>${escapeHtml(rider.phone || 'N/A')}</p>
                    <p class="mb-0 small text-muted"><i class="bi bi-scooter me-1"></i>${escapeHtml(rider.vehicle_type || 'N/A')} ${escapeHtml(rider.vehicle_number || '')}</p>
                    ${rider.lastAssignedAt ? `<p class="mb-0 small text-muted">Last assigned ${formatRelativeTimeFromNow(rider.lastAssignedAt)}</p>` : ''}
                </div>
                <div class="text-end">
                    <span class="badge bg-${(rider.status || '') === 'available' ? 'success' : ((rider.status || '') === 'busy' ? 'warning' : 'secondary')}">
                        ${capitalize(rider.status || 'unknown')}
                    </span>
                    <p class="mb-0 small text-muted mt-1">${Number(rider.total_deliveries || 0)} deliveries</p>
                    <p class="mb-0 small text-muted">Active jobs: ${rider.activeJobs}</p>
                    ${rider.idleMinutes !== null ? `<p class="mb-0 small text-muted">Idle ${formatMinutesLabel(rider.idleMinutes)}</p>` : ''}
                    <p class="mb-0 small"><i class="bi bi-star-fill text-warning"></i> ${(Number(rider.rating || 0)).toFixed(1)}</p>
                </div>
            </div>
        </div>
    `).join('');
}

function renderOrders(orders) {
    const tbody = document.getElementById('ordersTableBody');
    if (!orders.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox fs-1"></i><p class="mt-2 mb-0">No delivery orders</p></td></tr>';
        return;
    }
    latestOrders = orders.map(order => ({
        ...order,
        order_age_minutes: order.order_age_minutes ?? (order.created_at ? ((Date.now() - new Date(order.created_at).getTime()) / 60000) : 0)
    }));

    const filtered = latestOrders.filter(order => {
        const status = (order.delivery_status || order.status || '').toLowerCase();
        const matchesStatus = orderFilters.status === 'all' || status === orderFilters.status;
        const matchesUnassigned = !orderFilters.unassignedOnly || !order.rider_id;
        const sla = order.sla || {};
        const matchesSla = !orderFilters.slaRiskOnly || Boolean(sla.is_at_risk || sla.is_late);
        const searchValue = `${order.order_number || ''}|${order.customer_name || ''}|${order.customer_phone || ''}|${order.delivery_zone_name || ''}|${order.delivery_zone_code || ''}`.toLowerCase();
        const matchesSearch = orderFilters.search === '' || searchValue.includes(orderFilters.search);
        return matchesStatus && matchesUnassigned && matchesSla && matchesSearch;
    }).sort((a, b) => {
        switch (orderFilters.sort) {
            case 'age_asc':
                return (a.order_age_minutes - b.order_age_minutes);
            case 'amount_desc':
                return (Number(b.total_amount || 0) - Number(a.total_amount || 0));
            case 'amount_asc':
                return (Number(a.total_amount || 0) - Number(b.total_amount || 0));
            case 'age_desc':
            default:
                return (b.order_age_minutes - a.order_age_minutes);
        }
    });

    tbody.innerHTML = filtered.map(order => {
        const sla = order.sla || {};
        const statusBadge = getStatusBadgeColor(order.delivery_status);
        const riskBadge = sla.is_at_risk ? '<span class="badge bg-danger ms-1">SLA risk</span>' : (sla.is_late ? '<span class="badge bg-warning text-dark ms-1">Late</span>' : '');
        const slackBadge = `<span class="badge bg-${sla.is_at_risk ? 'danger' : (sla.is_late ? 'warning' : 'secondary')}">SLA ${sla.is_at_risk ? 'risk' : (sla.is_late ? 'late' : 'ok')}</span>`;

        return `
        <tr class="${sla.is_at_risk ? 'table-danger' : (sla.is_late ? 'table-warning' : '')}">
            <td>${escapeHtml(order.order_number)}</td>
            <td>
                ${escapeHtml(order.customer_name || 'N/A')}<br>
                <small class="text-muted">${escapeHtml(order.customer_phone || '')}</small>
            </td>
            <td>${formatDistanceDisplay(order)}</td>
            <td>${formatZoneDisplay(order)}</td>
            <td class="fw-bold">${formatCurrencyValue(order.total_amount, true)}</td>
            <td>${order.rider_name ? escapeHtml(order.rider_name) : '<span class="text-muted">Not assigned</span>'}</td>
            <td>
                <span class="badge bg-${statusBadge}">${capitalize((order.delivery_status || 'pending').replace('-', ' '))}</span>
                ${riskBadge}
            </td>
            <td>
                ${order.rider_id ? 
                    `<button class="btn btn-sm btn-outline-info" onclick="trackDelivery(${order.order_id || order.id})"><i class="bi bi-geo-alt"></i> Track</button>` :
                    `<button class="btn btn-sm btn-primary" onclick="assignRider(${order.order_id || order.id})"><i class="bi bi-person-plus"></i> Assign</button>`
                }
            </td>
        </tr>
    `;}).join('');
}

function formatDistanceDisplay(order) {
    if (!supportsDeliveryMetrics) {
        return '<span class="text-muted">N/A</span>';
    }
    const distance = order.estimated_distance_km;
    if (distance === null || typeof distance === 'undefined' || Number.isNaN(Number(distance))) {
        return '<span class="text-muted">N/A</span>';
    }
    return `${Number(distance).toFixed(2)} km`;
}

function formatZoneDisplay(order) {
    if (!supportsDeliveryMetrics) {
        return '<span class="text-muted">N/A</span>';
    }
    const zone = order.delivery_zone_name || order.delivery_zone_code || order.delivery_zone_id;
    if (!zone) {
        return '<span class="text-muted">N/A</span>';
    }
    return escapeHtml(zone.toString());
}

function getStatusBadgeColor(status) {
    switch (status) {
        case 'delivered': return 'success';
        case 'in-transit':
        case 'picked-up': return 'info';
        case 'assigned': return 'warning';
        default: return 'secondary';
    }
}

function escapeHtml(value) {
    return (value || '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDateTime(value) {
    if (!value) return null;
    return new Date(value).toLocaleString();
}

function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatMinutesLabel(minutes) {
    if (minutes === null || typeof minutes === 'undefined' || Number.isNaN(Number(minutes))) {
        return null;
    }
    const totalMinutes = Math.max(0, Math.round(Number(minutes)));
    const hours = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;

    if (hours && mins) {
        return `${hours} hr${hours > 1 ? 's' : ''} ${mins} min${mins !== 1 ? 's' : ''}`;
    }
    if (hours) {
        return `${hours} hr${hours > 1 ? 's' : ''}`;
    }
    return `${mins} min${mins !== 1 ? 's' : ''}`;
}

function formatRelativeTimeFromNow(value) {
    if (!value) {
        return null;
    }
    const timestamp = new Date(value).getTime();
    if (Number.isNaN(timestamp)) {
        return null;
    }
    const diffMs = Date.now() - timestamp;
    const diffMinutes = Math.round(diffMs / 60000);

    if (Math.abs(diffMinutes) < 1) {
        return 'just now';
    }

    if (Math.abs(diffMinutes) < 60) {
        return `${Math.abs(diffMinutes)} min${Math.abs(diffMinutes) === 1 ? '' : 's'} ago`;
    }

    const diffHours = Math.round(diffMinutes / 60);
    if (Math.abs(diffHours) < 24) {
        return `${Math.abs(diffHours)} hr${Math.abs(diffHours) === 1 ? '' : 's'} ago`;
    }

    const diffDays = Math.round(diffHours / 24);
    return `${Math.abs(diffDays)} day${Math.abs(diffDays) === 1 ? '' : 's'} ago`;
}

// Auto-refresh delivery status every 30 seconds without full reload
setInterval(refreshDeliveryData, 30000);

document.addEventListener('DOMContentLoaded', () => {
    registerFilterControls();
    refreshDeliveryData();
});
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding: 10px 0;
    border-left: 2px solid #dee2e6;
    padding-left: 20px;
    margin-bottom: 10px;
}

.timeline-item.completed {
    border-left-color: #28a745;
    color: #28a745;
}

.timeline-item.active {
    border-left-color: #007bff;
    color: #007bff;
    font-weight: bold;
}

.timeline-item i {
    position: absolute;
    left: -8px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
}

.timeline-item.completed i {
    border-color: #28a745;
    color: #28a745;
}

.timeline-item.active i {
    border-color: #007bff;
    color: #007bff;
}
</style>

<?php include 'includes/footer.php'; ?>
