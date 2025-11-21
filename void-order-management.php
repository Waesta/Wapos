<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check void permission - simplified for now
$user = $auth->getUser();
if (!$user || !in_array($user['role'], ['admin', 'manager', 'developer'])) {
    $_SESSION['error_message'] = 'You do not have permission to void orders. Manager or Admin role required.';
    redirect('index.php');
}

$db = Database::getInstance();

// Fetch void settings for policy thresholds
$voidSettingsRows = $db->fetchAll("SELECT setting_key, setting_value FROM void_settings");
$voidSettings = [];
foreach ($voidSettingsRows as $row) {
    $voidSettings[$row['setting_key']] = $row['setting_value'];
}

// Get recent orders that can be voided
$voidableOrders = $db->fetchAll("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           u.username as created_by_username,
           TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as age_minutes
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status IN ('pending', 'confirmed', 'preparing', 'ready')
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 50
");

$voidTimeLimit = (int)($voidSettings['void_time_limit_minutes'] ?? 60);
$managerAmountThreshold = (float)($voidSettings['require_manager_approval_amount'] ?? 0);
$dailyLimit = (int)($voidSettings['void_daily_limit'] ?? 0);

// Pre-fetch current user's void count for the day (used by stats and quick filters)
$userTodayVoids = (int)($db->fetchOne(
    "SELECT COUNT(*) as count FROM void_transactions WHERE voided_by_user_id = ? AND DATE(void_timestamp) = CURDATE()",
    [$auth->getUserId()]
)['count'] ?? 0);

$nearDailyLimit = $dailyLimit > 0 && $userTodayVoids >= max(0, $dailyLimit - 1);

foreach ($voidableOrders as &$order) {
    $order['total_amount'] = (float)($order['total_amount'] ?? 0);
    $order['age_minutes'] = (int)($order['age_minutes'] ?? 0);
    $order['over_time_limit'] = $voidTimeLimit > 0 && $order['age_minutes'] > $voidTimeLimit;
    $order['over_amount_threshold'] = $managerAmountThreshold > 0 && $order['total_amount'] > $managerAmountThreshold;
    $order['near_daily_limit'] = $nearDailyLimit;
}
unset($order);

// Get void reason codes
$voidReasons = $db->fetchAll("
    SELECT * FROM void_reason_codes 
    WHERE is_active = 1 
    ORDER BY display_order, display_name
");

// Get void statistics
$voidStats = [
    'today_voids' => $db->fetchOne("SELECT COUNT(*) as count FROM void_transactions WHERE DATE(void_timestamp) = CURDATE()")['count'] ?? 0,
    'week_voids' => $db->fetchOne("SELECT COUNT(*) as count FROM void_transactions WHERE void_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0,
    'user_today_voids' => $userTodayVoids,
    'total_void_amount_today' => $db->fetchOne("SELECT SUM(original_total) as total FROM void_transactions WHERE DATE(void_timestamp) = CURDATE()")['total'] ?? 0
];

$autoAdjustInventory = ($voidSettings['auto_adjust_inventory'] ?? '1') === '1';
$printVoidReceipt = ($voidSettings['print_void_receipt'] ?? '1') === '1';
$allowPartialVoid = ($voidSettings['allow_partial_void'] ?? '0') === '1';
$notificationEmail = $voidSettings['void_notification_email'] ?? '';

$voidCsrfToken = generateCSRFToken();

$policyHighlights = [
    'time_limit' => [
        'label' => 'Time Limit',
        'value' => $voidTimeLimit > 0 ? $voidTimeLimit . ' min' : 'Not enforced',
        'icon' => 'hourglass-split',
        'variant' => $voidTimeLimit > 0 ? 'warning' : 'secondary',
        'description' => $voidTimeLimit > 0 ? 'Orders older than this require approval.' : 'Manager approval is optional based on age.'
    ],
    'approval_amount' => [
        'label' => 'Approval Amount',
        'value' => $managerAmountThreshold > 0 ? formatMoney($managerAmountThreshold, false) : 'Not set',
        'icon' => 'cash-coin',
        'variant' => $managerAmountThreshold > 0 ? 'info' : 'secondary',
        'description' => 'Voids above this total must be escalated.'
    ],
    'daily_limit' => [
        'label' => 'Daily Limit',
        'value' => $dailyLimit > 0 ? $dailyLimit . ' voids' : 'No limit',
        'icon' => 'calendar-check',
        'variant' => $dailyLimit > 0 ? 'primary' : 'secondary',
        'description' => 'Per-user void allowance each day.'
    ],
    'auto_inventory' => [
        'label' => 'Inventory Sync',
        'value' => $autoAdjustInventory ? 'Enabled' : 'Disabled',
        'icon' => $autoAdjustInventory ? 'arrow-repeat' : 'slash-circle',
        'variant' => $autoAdjustInventory ? 'success' : 'secondary',
        'description' => 'Automatically return stock on void.'
    ],
];

$policyAlerts = [];
if ($nearDailyLimit && $dailyLimit > 0) {
    $policyAlerts[] = 'You are approaching the daily void limit. Manager authorization will be required for additional voids.';
}
if ($managerAmountThreshold > 0) {
    $policyAlerts[] = 'Orders above ' . formatMoney($managerAmountThreshold, false) . ' trigger manager review.';
}
if (!$autoAdjustInventory) {
    $policyAlerts[] = 'Inventory auto-adjustment is disabled. Remember to reconcile stock manually after voids.';
}
if ($notificationEmail) {
    $policyAlerts[] = 'Notification emails are being sent to ' . htmlspecialchars($notificationEmail); // escaping when rendered below
}

$userVoidCardClass = $nearDailyLimit ? 'text-danger' : 'text-info';
$userVoidHelperText = $dailyLimit > 0
    ? ($nearDailyLimit ? 'Daily limit: ' . $dailyLimit . ' · Action may require manager approval' : 'Daily limit: ' . $dailyLimit)
    : 'No daily limit set';

// Get recent void transactions
$recentVoids = $db->fetchAll("
    SELECT vt.*, vrc.display_name as reason_name, u.username
    FROM void_transactions vt
    JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    JOIN users u ON vt.voided_by_user_id = u.id
    ORDER BY vt.void_timestamp DESC
    LIMIT 20
");

$pageTitle = 'Void Order Management';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5><i class="bi bi-x-circle me-2"></i>Void Order Management</h5>
                    <small>Void orders with proper authorization and audit trail</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Void Statistics -->
    <div class="row g-3 mb-4 mt-2">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-x-circle text-danger fs-1 mb-2"></i>
                    <h3 class="text-danger" data-stat="today_voids"><?= $voidStats['today_voids'] ?></h3>
                    <p class="text-muted mb-0">Voids Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-week text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning" data-stat="week_voids"><?= $voidStats['week_voids'] ?></h3>
                    <p class="text-muted mb-0">Voids This Week</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i id="userVoidsIcon" class="bi bi-person-x <?= $userVoidCardClass ?> fs-1 mb-2"></i>
                    <h3 id="userVoidsCount" class="<?= $userVoidCardClass ?>" data-stat="user_today_voids"><?= $voidStats['user_today_voids'] ?></h3>
                    <p class="text-muted mb-1">Your Voids Today</p>
                    <small id="userVoidHelper" class="text-muted d-block"><?= htmlspecialchars($userVoidHelperText) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-secondary fs-1 mb-2"></i>
                    <h3 class="text-secondary" data-stat="total_void_amount_today"><?= formatMoney($voidStats['total_void_amount_today'], false) ?></h3>
                    <p class="text-muted mb-0">Void Amount Today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3" id="policyAlertsRow"<?= empty($policyAlerts) ? ' style="display:none;"' : '' ?>>
        <div class="col-12">
            <?php foreach ($policyAlerts as $alert): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= htmlspecialchars($alert) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mb-4" id="policyHighlightsRow">
        <?php foreach ($policyHighlights as $highlight): ?>
        <div class="col-xl-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-<?= $highlight['variant'] ?>-subtle text-<?= $highlight['variant'] ?> p-2">
                            <i class="bi bi-<?= htmlspecialchars($highlight['icon']) ?>"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 text-muted text-uppercase small"><?= htmlspecialchars($highlight['label']) ?></h6>
                            <strong class="d-block" data-highlight-value><?= htmlspecialchars($highlight['value']) ?></strong>
                        </div>
                    </div>
                    <p class="text-muted small mb-0" data-highlight-description><?= htmlspecialchars($highlight['description']) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Voidable Orders -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex flex-column flex-lg-row gap-2 justify-content-between align-items-lg-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Orders Available for Void</h6>
                        <span class="badge bg-light text-dark" id="voidableCountBadge"><?= count($voidableOrders) ?> orders</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="input-group input-group-sm" style="max-width:220px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="searchOrder" placeholder="Search order # or customer">
                        </div>
                        <select class="form-select form-select-sm" id="filterStatus" style="max-width:160px;">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="ready">Ready</option>
                        </select>
                        <select class="form-select form-select-sm" id="filterType" style="max-width:160px;">
                            <option value="">All Types</option>
                            <option value="dine-in">Dine-in</option>
                            <option value="takeout">Takeout</option>
                            <option value="delivery">Delivery</option>
                        </select>
                        <button class="btn btn-outline-secondary btn-sm" id="refreshVoidableBtn"><i class="bi bi-arrow-repeat"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="voidablePlaceholder" class="p-4 text-center text-muted" <?= empty($voidableOrders) ? '' : 'style="display:none;"' ?>>
                        <i class="bi bi-inbox fs-1 mb-3"></i>
                        <p class="mb-0">No orders available for void</p>
                    </div>
                    <div class="table-responsive" id="voidableTableWrapper" <?= empty($voidableOrders) ? 'style="display:none;"' : '' ?>>
                        <table class="table table-hover align-middle mb-0" id="voidableTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Age</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voidableOrders as $order): ?>
                                <tr data-order='<?= json_encode($order, JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>'>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                            <small class="text-muted">By <?= htmlspecialchars($order['created_by_username'] ?? 'N/A') ?></small>
                                            <?php if ($order['order_type'] === 'delivery'): ?>
                                            <span class="badge bg-info-subtle text-info mt-1"><i class="bi bi-truck me-1"></i>Delivery</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($order['customer_name'] ?: 'Walk-in') ?></strong>
                                            <?php if ($order['customer_phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= (int)$order['item_count'] ?> items</span></td>
                                    <td><strong><?= formatMoney($order['total_amount'], false) ?></strong></td>
                                    <td>
                                        <?php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'confirmed' => 'info',
                                                'preparing' => 'primary',
                                                'ready' => 'success'
                                            ];
                                            $statusColor = $statusColors[$order['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                    <td>
                                        <small>
                                            <?= (int)$order['age_minutes'] ?> min ago
                                            <?php if ($order['age_minutes'] > 60): ?>
                                            <br><span class="text-warning">⚠ Manager approval</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-secondary" onclick="showOrderDetails(<?= (int)$order['id'] ?>)"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-danger" onclick="openVoidModal(<?= (int)$order['id'] ?>)"><i class="bi bi-x-circle"></i> Void</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Voids / Activity -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class"card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Voids</h6>
                    <button class="btn btn-outline-secondary btn-sm" id="refreshRecentVoids"><i class="bi bi-arrow-repeat"></i></button>
                </div>
                <div class="card-body p-0" id="recentVoidsPanel">
                    <div class="p-4 text-center text-muted" id="recentVoidsPlaceholder" <?= empty($recentVoids) ? '' : 'style="display:none;"' ?>>
                        <i class="bi bi-clipboard-x fs-1 mb-3"></i>
                        <p class="mb-0">No recent voids</p>
                    </div>
                    <div class="list-group list-group-flush" <?= empty($recentVoids) ? 'style="display:none;"' : '' ?> id="recentVoidsList">
                        <?php foreach ($recentVoids as $void): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>#<?= htmlspecialchars($void['order_number']) ?></strong>
                                    <div class="text-muted small"><?= formatMoney($void['original_total'], false) ?></div>
                                </div>
                                <small class="text-muted"><?= timeAgo($void['void_timestamp']) ?></small>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-danger"><?= htmlspecialchars($void['reason_name']) ?></span>
                                <?php if (!empty($void['manager_username'])): ?>
                                <span class="badge bg-info-subtle text-info ms-1"><i class="bi bi-shield-check"></i> <?= htmlspecialchars($void['manager_username']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 text-muted small">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($void['voided_by_username'] ?? '') ?>
                            </div>
                            <?php if (!empty($void['void_reason_text'])): ?>
                            <div class="mt-2 text-muted small fst-italic">"<?= htmlspecialchars(substr($void['void_reason_text'], 0, 140)) ?>"</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Quick Filters</h6>
                </div>
                <div class="card-body">
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2" data-quick-filter="age" onclick="applyPolicyFilter('age', this)"><i class="bi bi-hourglass-split me-1"></i> Orders over policy limit</button>
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2" data-quick-filter="amount" onclick="applyPolicyFilter('amount', this)"><i class="bi bi-cash-coin me-1"></i> Orders above approval amount</button>
                    <button class="btn btn-outline-primary btn-sm w-100" data-quick-filter="daily-limit" onclick="applyPolicyFilter('daily-limit', this)"><i class="bi bi-calendar-check me-1"></i> Users near daily limit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Void Order Modal -->
<div class="modal fade" id="voidOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Void Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="void_order">
                    <input type="hidden" name="order_id" id="voidOrderId">
                    <input type="hidden" name="csrf_token" value="<?= $voidCsrfToken ?>">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. The order will be permanently voided.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Order Details:</strong></label>
                        <div id="orderDetails" class="p-2 bg-light rounded"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason_code" class="form-label">Void Reason <span class="text-danger">*</span></label>
                        <select class="form-select" name="reason_code" id="reason_code" required>
                            <option value="">Select reason...</option>
                            <?php foreach ($voidReasons as $reason): ?>
                            <option value="<?= $reason['code'] ?>" data-requires-approval="<?= $reason['requires_manager_approval'] ? '1' : '0' ?>">
                                <?= htmlspecialchars($reason['display_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason_text" class="form-label">Additional Details</label>
                        <textarea class="form-control" name="reason_text" id="reason_text" rows="3" placeholder="Optional additional details about the void..."></textarea>
                    </div>

                    <div id="policyFlags" class="mb-3" style="display:none;"></div>

                    <div id="managerApprovalSection" class="mb-3" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-shield-check me-2"></i>
                            Manager approval is required for this void request.
                        </div>
                        <div class="mb-2">
                            <label for="manager_user_id" class="form-label">Manager Authorization</label>
                            <select class="form-select" name="manager_user_id" id="manager_user_id">
                                <option value="">Select manager...</option>
                                <?php
                                $managers = $db->fetchAll(
                                    "SELECT id, username, full_name FROM users WHERE role IN ('manager','admin','developer') AND is_active = 1 ORDER BY full_name, username"
                                );
                                foreach ($managers as $manager):
                                ?>
                                <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['full_name'] ?: $manager['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="manager_pin" class="form-label">Manager PIN</label>
                            <input type="password" class="form-control" id="manager_pin" name="manager_pin" placeholder="Enter manager PIN">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Void Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const VOID_API = 'api/void-order-management.php';
const CSRF_TOKEN = <?= json_encode($voidCsrfToken) ?>;
let voidModalInstance = null;
let detailOffcanvas = null;
let activeQuickFilter = null;
let voidSettings = <?= json_encode($voidSettings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || {};
let voidStats = <?= json_encode($voidStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || {};

document.addEventListener('DOMContentLoaded', () => {
    voidModalInstance = new bootstrap.Modal(document.getElementById('voidOrderModal'));
    setupFilters();
    setupForm();
    autoPrintVoidReceipt();
    renderPolicySummary(voidSettings, voidStats);
});

function setupFilters() {
    document.getElementById('searchOrder').addEventListener('input', filterTable);
    document.getElementById('filterStatus').addEventListener('change', filterTable);
    document.getElementById('filterType').addEventListener('change', filterTable);
    document.getElementById('refreshVoidableBtn').addEventListener('click', refreshVoidData);
    document.getElementById('refreshRecentVoids').addEventListener('click', refreshVoidData);
}

function filterTable() {
    const query = document.getElementById('searchOrder').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const typeFilter = document.getElementById('filterType').value;

    const rows = document.querySelectorAll('#voidableTable tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        const data = JSON.parse(row.dataset.order);
        const matchesSearch = !query || Object.values(data).some(val => String(val ?? '').toLowerCase().includes(query));
        const matchesStatus = !statusFilter || data.status === statusFilter;
        const matchesType = !typeFilter || data.order_type === typeFilter;
        const matchesQuickFilter = (() => {
            switch (activeQuickFilter) {
                case 'age':
                    return !!data.over_time_limit;
                case 'amount':
                    return !!data.over_amount_threshold;
                case 'daily-limit':
                    return !!data.near_daily_limit;
                default:
                    return true;
            }
        })();

        const shouldShow = matchesSearch && matchesStatus && matchesType && matchesQuickFilter;
        row.style.display = shouldShow ? '' : 'none';
        if (shouldShow) {
            visibleCount++;
        }
    });

    return visibleCount;
}

async function refreshVoidData() {
    try {
        const response = await fetch(VOID_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_void_lists', csrf_token: CSRF_TOKEN })
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to refresh');
        }

        updateVoidLists(result);
        showToast('Void lists refreshed', 'success');
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

function openVoidModal(orderId) {
    fetchOrderDetails(orderId, true);
}

function showOrderDetails(orderId) {
    fetchOrderDetails(orderId, false);
}

async function fetchOrderDetails(orderId, openVoidFlow) {
    try {
        const response = await fetch(VOID_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'fetch_void_context', order_id: orderId, csrf_token: CSRF_TOKEN })
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to load order context');
        }

        const orderData = result.data;
        populateOrderSummary(orderData);

        if (openVoidFlow) {
            document.getElementById('voidOrderId').value = orderData.order.id;
            document.getElementById('reason_code').value = '';
            document.getElementById('reason_text').value = '';
            document.getElementById('manager_user_id').value = '';
            document.getElementById('manager_pin').value = '';
            document.getElementById('managerApprovalSection').style.display = 'none';
            document.getElementById('policyFlags').style.display = 'none';
            document.getElementById('policyFlags').innerHTML = '';
            voidModalInstance.show();
            checkManagerRequirement(orderData.policy);
        } else {
            renderDetailDrawer(orderData);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

function populateOrderSummary(context) {
    const order = context.order;
    const summaryEl = document.getElementById('orderDetails');
    summaryEl.innerHTML = `
        <div class="d-flex justify-content-between">
            <div>
                <strong>Order #${order.order_number}</strong><br>
                <small class="text-muted">${formatDate(order.created_at)}</small>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary">${order.order_type}</span><br>
                <strong>${formatMoney(order.total_amount)}</strong>
            </div>
        </div>
        <div class="mt-2">
            <small><strong>Customer:</strong> ${order.customer_name || 'Walk-in'}</small><br>
            ${order.customer_phone ? `<small class='text-muted'>${order.customer_phone}</small>` : ''}
        </div>
        <div class="mt-2"><small><strong>Created by:</strong> ${order.created_by_full_name || order.created_by_username || 'System'}</small></div>
        <div class="mt-2">
            <small><strong>Age:</strong> ${context.policy.age_minutes} minutes</small>
        </div>
    `;
}

function checkManagerRequirement(policy) {
    const managerSection = document.getElementById('managerApprovalSection');
    const managerSelect = document.getElementById('manager_user_id');
    const managerPin = document.getElementById('manager_pin');
    const policyFlags = document.getElementById('policyFlags');

    if (policy.flags.length) {
        policyFlags.style.display = 'block';
        policyFlags.innerHTML = policy.flags
            .map(flag => `<div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle me-2"></i>${flag.message}</div>`)
            .join('');
    }

    if (policy.requires_manager) {
        managerSection.style.display = 'block';
        managerSelect.required = true;
        managerPin.required = true;
    } else {
        managerSection.style.display = 'none';
        managerSelect.required = false;
        managerPin.required = false;
    }
}

function setupForm() {
    const form = document.querySelector('#voidOrderModal form');
    form.addEventListener('submit', handleVoidSubmit);
    document.getElementById('reason_code').addEventListener('change', event => {
        const requiresApproval = event.target.options[event.target.selectedIndex]?.getAttribute('data-requires-approval') === '1';
        if (requiresApproval) {
            document.getElementById('managerApprovalSection').style.display = 'block';
        }
    });
}

async function handleVoidSubmit(event) {
    event.preventDefault();
    const submitBtn = event.submitter;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Voiding...';

    try {
        const payload = {
            action: 'void_order',
            csrf_token: CSRF_TOKEN,
            order_id: document.getElementById('voidOrderId').value,
            reason_code: document.getElementById('reason_code').value,
            reason_text: document.getElementById('reason_text').value,
            manager_user_id: document.getElementById('manager_user_id').value || null,
            manager_pin: document.getElementById('manager_pin').value || null,
        };

        const response = await fetch(VOID_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Void request failed');
        }

        voidModalInstance.hide();
        updateVoidLists(result);
        showToast(result.message || 'Order voided successfully');
    } catch (error) {
        showToast(error.message, 'danger');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Void Order';
    }
}

function updateVoidLists(data) {
    if (data.void_settings) {
        voidSettings = data.void_settings;
    }
    if (data.void_stats) {
        voidStats = data.void_stats;
        renderVoidStats(data.void_stats);
    }
    if (data.voidable_orders) {
        renderVoidableTable(data.voidable_orders);
    }
    if (data.recent_voids) {
        renderRecentVoids(data.recent_voids);
    }

    renderPolicySummary(voidSettings, voidStats);

    filterTable();
}

function renderVoidableTable(orders) {
    const tableWrapper = document.getElementById('voidableTableWrapper');
    const placeholder = document.getElementById('voidablePlaceholder');
    const tbody = document.querySelector('#voidableTable tbody');
    const badge = document.getElementById('voidableCountBadge');

    if (!orders.length) {
        tableWrapper.style.display = 'none';
        placeholder.style.display = 'block';
        badge.textContent = '0 orders';
        return;
    }

    badge.textContent = `${orders.length} orders`;
    tableWrapper.style.display = 'block';
    placeholder.style.display = 'none';

    tbody.innerHTML = orders.map(order => `
        <tr data-order='${JSON.stringify(order)}'>
            <td>
                <div class="d-flex flex-column">
                    <strong>#${order.order_number}</strong>
                    <small class="text-muted">By ${order.created_by_username ?? 'N/A'}</small>
                    ${order.order_type === 'delivery' ? '<span class="badge bg-info-subtle text-info mt-1"><i class="bi bi-truck me-1"></i>Delivery</span>' : ''}
                </div>
            </td>
            <td>
                <div>
                    <strong>${order.customer_name || 'Walk-in'}</strong>
                    ${order.customer_phone ? `<br><small class="text-muted">${order.customer_phone}</small>` : ''}
                </div>
            </td>
            <td><span class="badge bg-light text-dark">${order.item_count} items</span></td>
            <td><strong>${formatMoney(order.total_amount)}</strong></td>
            <td>${renderStatusBadge(order.status)}</td>
            <td><small>${order.age_minutes} min ago${order.age_minutes > 60 ? '<br><span class="text-warning">⚠ Manager approval</span>' : ''}</small></td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary" onclick="showOrderDetails(${order.id})"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-danger" onclick="openVoidModal(${order.id})"><i class="bi bi-x-circle"></i> Void</button>
                </div>
            </td>
        </tr>
    `).join('');

    filterTable();
}

function renderStatusBadge(status) {
    const map = { pending: 'warning', confirmed: 'info', preparing: 'primary', ready: 'success' };
    const badge = map[status] || 'secondary';
    return `<span class="badge bg-${badge}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

function renderRecentVoids(voids) {
    const placeholder = document.getElementById('recentVoidsPlaceholder');
    const list = document.getElementById('recentVoidsList');

    if (!voids.length) {
        placeholder.style.display = 'block';
        list.style.display = 'none';
        list.innerHTML = '';
        return;
    }

    placeholder.style.display = 'none';
    list.style.display = 'block';
    list.innerHTML = voids.map(voidTxn => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>#${voidTxn.order_number}</strong>
                    <div class="text-muted small">${formatMoney(voidTxn.original_total)}</div>
                </div>
                <small class="text-muted">${timeAgo(voidTxn.void_timestamp)}</small>
            </div>
            <div class="mt-2">
                <span class="badge bg-danger">${voidTxn.reason_name}</span>
                ${voidTxn.manager_username ? `<span class="badge bg-info-subtle text-info ms-1"><i class="bi bi-shield-check"></i> ${voidTxn.manager_username}</span>` : ''}
            </div>
            <div class="mt-2 text-muted small">
                <i class="bi bi-person"></i> ${voidTxn.voided_by_username ?? ''}
            </div>
            ${voidTxn.void_reason_text ? `<div class="mt-2 text-muted small fst-italic">"${voidTxn.void_reason_text.substring(0, 140)}"</div>` : ''}
        </div>
    `).join('');
}

function renderPolicySummary(settings, stats) {
    const highlightsRow = document.getElementById('policyHighlightsRow');
    const alertsRow = document.getElementById('policyAlertsRow');
    if (!highlightsRow) {
        return;
    }

    const highlights = buildPolicyHighlights(settings, stats);
    highlightsRow.innerHTML = highlights.map((highlight) => `
        <div class="col-xl-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-${highlight.variant}-subtle text-${highlight.variant} p-2">
                            <i class="bi bi-${highlight.icon}"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 text-muted text-uppercase small">${escapeHtml(highlight.label)}</h6>
                            <strong class="d-block">${escapeHtml(highlight.value)}</strong>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">${escapeHtml(highlight.description)}</p>
                </div>
            </div>
        </div>
    `).join('');

    const alerts = buildPolicyAlerts(settings, stats);
    if (alertsRow) {
        if (!alerts.length) {
            alertsRow.style.display = 'none';
            alertsRow.innerHTML = '<div class="col-12"></div>';
        } else {
            alertsRow.style.display = '';
            alertsRow.innerHTML = `
                <div class="col-12">
                    ${alerts.map(alert => `
                        <div class="alert alert-warning d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>${escapeHtml(alert)}</div>
                        </div>
                    `).join('')}
                </div>`;
        }
    }
}

function buildPolicyHighlights(settings, stats) {
    const timeLimit = parseInt(settings?.void_time_limit_minutes ?? '0', 10) || 0;
    const approvalAmount = parseFloat(settings?.require_manager_approval_amount ?? '0') || 0;
    const dailyLimit = parseInt(settings?.void_daily_limit ?? '0', 10) || 0;
    const autoInventory = (settings?.auto_adjust_inventory ?? '1') === '1';

    return [
        {
            label: 'Time Limit',
            value: timeLimit > 0 ? `${timeLimit} min` : 'Not enforced',
            icon: 'hourglass-split',
            variant: timeLimit > 0 ? 'warning' : 'secondary',
            description: timeLimit > 0 ? 'Orders older than this require approval.' : 'Manager approval is optional based on age.',
        },
        {
            label: 'Approval Amount',
            value: approvalAmount > 0 ? formatMoney(approvalAmount) : 'Not set',
            icon: 'cash-coin',
            variant: approvalAmount > 0 ? 'info' : 'secondary',
            description: 'Voids above this total must be escalated.',
        },
        {
            label: 'Daily Limit',
            value: dailyLimit > 0 ? `${dailyLimit} voids` : 'No limit',
            icon: 'calendar-check',
            variant: dailyLimit > 0 ? 'primary' : 'secondary',
            description: 'Per-user void allowance each day.',
        },
        {
            label: 'Inventory Sync',
            value: autoInventory ? 'Enabled' : 'Disabled',
            icon: autoInventory ? 'arrow-repeat' : 'slash-circle',
            variant: autoInventory ? 'success' : 'secondary',
            description: 'Automatically return stock on void.',
        },
    ];
}

function buildPolicyAlerts(settings, stats) {
    const alerts = [];
    const dailyLimit = parseInt(settings?.void_daily_limit ?? '0', 10) || 0;
    const approvalAmount = parseFloat(settings?.require_manager_approval_amount ?? '0') || 0;
    const autoInventory = (settings?.auto_adjust_inventory ?? '1') === '1';
    const notificationEmail = settings?.void_notification_email || '';
    const userVoids = parseInt(stats?.user_today_voids ?? '0', 10) || 0;

    if (dailyLimit > 0 && userVoids >= Math.max(0, dailyLimit - 1)) {
        alerts.push('You are approaching the daily void limit. Manager authorization will be required for additional voids.');
    }
    if (approvalAmount > 0) {
        alerts.push(`Orders above ${formatMoney(approvalAmount)} trigger manager review.`);
    }
    if (!autoInventory) {
        alerts.push('Inventory auto-adjustment is disabled. Remember to reconcile stock manually after voids.');
    }
    if (notificationEmail) {
        alerts.push(`Notification emails are being sent to ${escapeHtml(notificationEmail)}`);
    }

    return alerts;
}

function computeUserVoidState(stats, settings) {
    const dailyLimit = parseInt(settings?.void_daily_limit ?? '0', 10) || 0;
    const userVoids = parseInt(stats?.user_today_voids ?? '0', 10) || 0;
    const nearingLimit = dailyLimit > 0 && userVoids >= Math.max(0, dailyLimit - 1);

    return {
        className: nearingLimit ? 'text-danger' : 'text-info',
        helper: dailyLimit > 0
            ? (nearingLimit ? `Daily limit: ${dailyLimit} · Action may require manager approval` : `Daily limit: ${dailyLimit}`)
            : 'No daily limit set',
    };
}

function escapeHtml(str) {
    if (str === null || str === undefined) {
        return '';
    }
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderVoidStats(stats) {
    const todayCard = document.querySelector('[data-stat="today_voids"]');
    const weekCard = document.querySelector('[data-stat="week_voids"]');
    const userCard = document.querySelector('[data-stat="user_today_voids"]');
    const amountCard = document.querySelector('[data-stat="total_void_amount_today"]');

    if (todayCard) todayCard.textContent = stats.today_voids;
    if (weekCard) weekCard.textContent = stats.week_voids;
    if (userCard) userCard.textContent = stats.user_today_voids;
    if (amountCard) amountCard.textContent = formatMoney(stats.total_void_amount_today);

    const helper = document.getElementById('userVoidHelper');
    const icon = document.getElementById('userVoidsIcon');
    const count = document.getElementById('userVoidsCount');
    const state = computeUserVoidState(stats, voidSettings);

    if (helper) helper.textContent = state.helper;
    if (icon) {
        icon.className = `bi bi-person-x ${state.className} fs-1 mb-2`;
    }
    if (count) {
        count.className = state.className;
    }
}

function applyPolicyFilter(type, button) {
    const isTogglingOff = activeQuickFilter === type;
    activeQuickFilter = isTogglingOff ? null : type;

    document.querySelectorAll('[data-quick-filter]').forEach(btn => {
        btn.classList.remove('btn-primary', 'text-white');
        btn.classList.add('btn-outline-primary');
    });

    if (!isTogglingOff && button) {
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-primary', 'text-white');
    }

    if (!activeQuickFilter) {
        filterTable();
        showToast('Quick filters cleared', 'secondary');
        return;
    }

    const visibleCount = filterTable();

    if (visibleCount === 0) {
        showToast('No orders match this quick filter', 'info');
    } else {
        const dailyLimit = parseInt(voidSettings.void_daily_limit ?? '0', 10) || 0;
        const messages = {
            age: 'Showing orders over the policy time limit',
            amount: 'Showing orders exceeding manager approval amount',
            'daily-limit': dailyLimit > 0 && voidStats.user_today_voids >= Math.max(0, dailyLimit - 1)
                ? 'You are near the daily void limit. Manager approval will be required.'
                : 'Showing orders while you approach the daily limit',
        };
        showToast(messages[type] || 'Quick filter applied', 'info');
    }
}

function renderDetailDrawer(context) {
    if (!detailOffcanvas) {
        const template = document.getElementById('orderDetailTemplate');
        document.body.appendChild(template.content.cloneNode(true));
        detailOffcanvas = new bootstrap.Offcanvas(document.getElementById('orderDetailDrawer'));
    }

    const order = context.order;
    document.getElementById('detailHeader').innerHTML = `Order #${order.order_number}`;
    document.getElementById('detailMeta').innerHTML = `
        <div><strong>Status:</strong> ${order.status}</div>
        <div><strong>Total:</strong> ${formatMoney(order.total_amount)}</div>
        <div><strong>Created:</strong> ${formatDate(order.created_at)} (${context.policy.age_minutes} min ago)</div>
        <div><strong>Customer:</strong> ${order.customer_name || 'Walk-in'} ${order.customer_phone ? '(' + order.customer_phone + ')' : ''}</div>
    `;

    document.getElementById('detailItems').innerHTML = context.items.map(item => `
        <li class="list-group-item">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${item.product_name}</strong>
                    ${item.modifiers_data ? `<div class="text-muted small">${renderModifiers(item.modifiers_data)}</div>` : ''}
                </div>
                <div>
                    <span class="badge bg-light text-dark">${item.quantity} x ${formatMoney(item.unit_price)}</span>
                </div>
            </div>
        </li>
    `).join('');

    document.getElementById('detailPayments').innerHTML = context.payments.length ? context.payments.map(payment => `
        <tr>
            <td>${formatDate(payment.created_at)}</td>
            <td>${payment.payment_method}</td>
            <td>${formatMoney(payment.amount)}</td>
            <td>${payment.recorded_by ?? ''}</td>
        </tr>
    `).join('') : '<tr><td colspan="4" class="text-center text-muted">No payments recorded</td></tr>';

    detailOffcanvas.show();
}

function renderModifiers(json) {
    try {
        const modifiers = JSON.parse(json) || [];
        return modifiers.map(mod => `+ ${mod.name}`).join(', ');
    } catch (e) {
        return '';
    }
}

function formatMoney(amount) {
    const num = parseFloat(amount) || 0;
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleString();
}

function timeAgo(dateString) {
    const delta = Math.floor((Date.now() - new Date(dateString)) / 1000);
    if (delta < 60) return `${delta}s ago`;
    if (delta < 3600) return `${Math.floor(delta / 60)}m ago`;
    if (delta < 86400) return `${Math.floor(delta / 3600)}h ago`;
    return `${Math.floor(delta / 86400)}d ago`;
}

function showToast(message, variant = 'success') {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${variant} border-0`;
    toastEl.role = 'alert';
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;

    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function autoPrintVoidReceipt() {
    <?php if (!empty($_SESSION['print_void_receipt'])): ?>
    const orderId = <?= (int)$_SESSION['print_void_receipt'] ?>;
    <?php unset($_SESSION['print_void_receipt']); ?>
    window.open('print-void-receipt.php?order_id=' + orderId, '_blank');
    <?php endif; ?>
}
</script>

<template id="orderDetailTemplate">
    <div class="offcanvas offcanvas-end" tabindex="-1" id="orderDetailDrawer" style="width: 420px;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="detailHeader"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div id="detailMeta" class="mb-3 small text-muted"></div>
            <h6>Items</h6>
            <ul class="list-group list-group-flush mb-3" id="detailItems"></ul>
            <h6>Payments</h6>
            <div class="table-responsive">
                <table class="table table-sm" id="detailPayments">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>By</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</template>

<?php include 'includes/footer.php'; ?>
