<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$canAccessReservations = $auth->hasRole('admin') || $auth->hasRole('manager') || $auth->hasRole('waiter');
if (!$canAccessReservations) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();

$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'restaurant_tables'");
    if ($stmt && $stmt->fetchColumn()) {
        $tables = $db->fetchAll(
            "SELECT id, table_number, table_name, capacity
             FROM restaurant_tables
             WHERE is_active = 1
             ORDER BY table_number ASC, table_name ASC"
        );
    }
} catch (Throwable $e) {
    $tables = [];
}

$currencyManager = CurrencyManager::getInstance();
$currencyJsConfig = $currencyManager->getJavaScriptConfig();
$currencyCode = $currencyManager->getCurrencyCode();
$gatewayProvider = strtolower((string)(settings('payments_gateway_provider') ?? ''));
$isGatewayEnabled = in_array($gatewayProvider, ['relworx', 'pesapal'], true);

$pageTitle = 'Restaurant Reservations';
include 'includes/header.php';
?>

<script>
    window.RESERVATION_GATEWAY_CONFIG = {
        enabled: <?= $isGatewayEnabled ? 'true' : 'false' ?>,
        provider: '<?= addslashes($gatewayProvider) ?>',
        currency: '<?= addslashes($currencyCode) ?>'
    };
    window.CURRENCY_CONFIG = <?= json_encode($currencyJsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="container-fluid py-4 stack-lg" role="main">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Restaurant Reservations</h1>
            <p class="text-muted mb-0">Manage table bookings, track statuses, and coordinate seatings.</p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <div class="d-flex align-items-center gap-2 small text-muted">
                <div class="live-sync-dot" id="reservationSyncDot"></div>
                <span id="reservationSyncLabel">Live updates on</span>
            </div>
            <button class="btn btn-primary" id="newReservationBtn">
                <i class="bi bi-plus-circle me-2"></i>Create Reservation
            </button>
        </div>
    </div>

    <div id="alertContainer" class="stack-sm" aria-live="polite" aria-atomic="true"></div>

    <div class="row g-3" id="summaryCards">
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="app-card h-100">
                <div class="text-uppercase text-muted fw-semibold small">Total</div>
                <div class="fs-3 fw-semibold mb-0" data-summary="total">0</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="app-card h-100">
                <div class="text-uppercase text-muted fw-semibold small">Pending</div>
                <div class="fs-4 fw-semibold text-warning mb-0" data-summary="pending">0</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="app-card h-100">
                <div class="text-uppercase text-muted fw-semibold small">Confirmed</div>
                <div class="fs-4 fw-semibold text-primary mb-0" data-summary="confirmed">0</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="app-card h-100">
                <div class="text-uppercase text-muted fw-semibold small">Seated</div>
                <div class="fs-4 fw-semibold text-success mb-0" data-summary="seated">0</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="app-card h-100">
                <div class="text-uppercase text-muted fw-semibold small">Completed</div>
                <div class="fs-4 fw-semibold text-secondary mb-0" data-summary="completed">0</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="app-card h-100">
                <div class="text-uppercase text-muted fw-semibold small">Cancelled / No-show</div>
                <div class="d-flex align-items-center gap-2 fw-semibold">
                    <span class="text-danger" data-summary="cancelled">0</span>
                    <span class="text-muted">/</span>
                    <span class="text-danger" data-summary="no_show">0</span>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card">
        <form class="row gy-3 gx-3 align-items-end" id="filterForm">
            <div class="col-sm-6 col-md-3 col-lg-2">
                <label for="filterDate" class="form-label">Reservation Date</label>
                <input type="date" id="filterDate" class="form-control" required>
            </div>
            <div class="col-sm-6 col-md-3 col-lg-2">
                <label for="filterStatus" class="form-label">Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="seated">Seated</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_show">No-show</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-3 col-lg-2">
                <label for="filterTable" class="form-label">Table</label>
                <select id="filterTable" class="form-select">
                    <option value="">All Tables</option>
                    <?php foreach ($tables as $table): ?>
                    <?php
                        $labelParts = [];
                        if (!empty($table['table_number'])) {
                            $labelParts[] = 'Table ' . htmlspecialchars($table['table_number']);
                        }
                        if (!empty($table['table_name'])) {
                            $labelParts[] = htmlspecialchars($table['table_name']);
                        }
                        if (empty($labelParts)) {
                            $labelParts[] = 'Table #' . (int)$table['id'];
                        }
                        $label = implode(' · ', $labelParts);
                    ?>
                    <option value="<?= (int)$table['id']; ?>">
                        <?= $label; ?> (<?= (int)($table['capacity'] ?? 0); ?> pax)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label class="form-label">Actions</label>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-icon" id="refreshBtn">
                        <i class="bi bi-arrow-repeat"></i><span>Refresh</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-icon" id="clearFiltersBtn">
                        <i class="bi bi-sliders"></i><span>Reset</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="app-card">
        <div class="section-heading">
            <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Reservations</h5>
            <span class="app-status" data-color="info" id="reservationCount">0 reservations</span>
        </div>
        <div class="app-table">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Table</th>
                        <th>Guest</th>
                        <th>Party</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Deposit</th>
                        <th>Notes</th>
                        <th>Created By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="reservationsTableBody">
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">Loading reservations...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reservation Modal -->
<div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationModalLabel">Create Reservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reservationForm">
                    <input type="hidden" id="reservationId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="reservationTable" class="form-label">Table</label>
                            <select id="reservationTable" class="form-select" required>
                                <option value="">Select table...</option>
                                <?php foreach ($tables as $table): ?>
                                <?php
                                    $labelParts = [];
                                    if (!empty($table['table_number'])) {
                                        $labelParts[] = 'Table ' . htmlspecialchars($table['table_number']);
                                    }
                                    if (!empty($table['table_name'])) {
                                        $labelParts[] = htmlspecialchars($table['table_name']);
                                    }
                                    if (empty($labelParts)) {
                                        $labelParts[] = 'Table #' . (int)$table['id'];
                                    }
                                    $label = implode(' · ', $labelParts);
                                ?>
                                <option value="<?= (int)$table['id']; ?>" data-capacity="<?= (int)($table['capacity'] ?? 0); ?>">
                                    <?= $label; ?> (<?= (int)($table['capacity'] ?? 0); ?> pax)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="reservationDate" class="form-label">Date</label>
                            <input type="date" id="reservationDate" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="reservationTime" class="form-label">Time</label>
                            <input type="time" id="reservationTime" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="reservationDuration" class="form-label">Duration (mins)</label>
                            <input type="number" id="reservationDuration" class="form-control" value="120" min="30" step="15">
                        </div>
                        <div class="col-md-3">
                            <label for="reservationPartySize" class="form-label">Party Size</label>
                            <input type="number" id="reservationPartySize" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reservationGuestName" class="form-label">Guest Name</label>
                            <input type="text" id="reservationGuestName" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reservationGuestPhone" class="form-label">Guest Phone</label>
                            <input type="tel" id="reservationGuestPhone" class="form-control" maxlength="30" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reservationStatus" class="form-label">Status</label>
                            <select id="reservationStatus" class="form-select" required>
                                <option value="confirmed">Confirmed</option>
                                <option value="pending">Pending</option>
                                <option value="seated">Seated</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No-show</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="reservationSpecialRequests" class="form-label">Special Requests</label>
                            <textarea id="reservationSpecialRequests" class="form-control" rows="2" maxlength="500" placeholder="Allergies, preferences, occasions..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="card border">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Deposit Settings</h6>
                                        <small class="text-muted">Capture upfront payments when required.</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="reservationDepositRequired">
                                        <label class="form-check-label" for="reservationDepositRequired">Deposit required</label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label" for="reservationDepositAmount">Deposit Amount</label>
                                            <input type="number" class="form-control" id="reservationDepositAmount" min="0" step="0.01" placeholder="e.g. 50.00" disabled>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="reservationDepositDueAt">Deposit Due By</label>
                                            <input type="datetime-local" class="form-control" id="reservationDepositDueAt" disabled>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="reservationCancellationPolicy">Cancellation Policy</label>
                                            <textarea class="form-control" id="reservationCancellationPolicy" rows="1" maxlength="500" placeholder="Optional" disabled></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="reservationDepositNotes">Internal Deposit Notes</label>
                                            <textarea class="form-control" id="reservationDepositNotes" rows="2" maxlength="500" placeholder="Internal notes for the team" disabled></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card bg-light" id="reservationDepositSummaryCard" hidden>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="text-muted text-uppercase small">Deposit Status</div>
                                            <div id="reservationDepositStatusBadge">-</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-muted text-uppercase small">Balance</div>
                                            <div class="fs-5 fw-semibold" id="reservationDepositBalance">-</div>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="fw-semibold">Payments</h6>
                                                    <div class="small text-muted mb-2">Chronological log of recorded deposits.</div>
                                                    <div id="reservationDepositPayments" class="deposit-payments-list small">No payments yet.</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="fw-semibold">Record Payment</h6>
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <label class="form-label small" for="depositPaymentAmount">Amount</label>
                                                            <input type="number" class="form-control" id="depositPaymentAmount" min="0" step="0.01" placeholder="e.g. 25.00">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small" for="depositPaymentMethod">Method</label>
                                                            <select class="form-select" id="depositPaymentMethod">
                                                                <option value="">Select method</option>
                                                                <option value="mobile_money">Mobile Money</option>
                                                                <option value="cash">Cash</option>
                                                                <option value="card">Card</option>
                                                                <option value="bank_transfer">Bank Transfer</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6" id="depositPaymentPhoneWrap" hidden>
                                                            <label class="form-label small" for="depositPaymentPhone">Customer Phone</label>
                                                            <input type="tel" class="form-control" id="depositPaymentPhone" placeholder="e.g. +2567...">
                                                            <div class="form-text">Required for mobile money prompts.</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small" for="depositPaymentReference">Reference</label>
                                                            <input type="text" class="form-control" id="depositPaymentReference" placeholder="Txn ID / receipt">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small" for="depositPaymentNotes">Notes</label>
                                                            <textarea class="form-control" id="depositPaymentNotes" rows="2" maxlength="250" placeholder="Optional"></textarea>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-success mt-3" id="recordDepositPaymentBtn">
                                                        <i class="bi bi-cash-coin me-2"></i>Record Payment
                                                    </button>
                                                    <div class="alert alert-info d-none mt-3" id="depositPaymentGatewayStatus"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveReservationBtn">
                    <i class="bi bi-save me-2"></i>Save Reservation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Update Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="statusReservationId">
                <div class="mb-3">
                    <label for="statusSelect" class="form-label">Status</label>
                    <select id="statusSelect" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="seated">Seated</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No-show</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="statusNotes" class="form-label">Notes (optional)</label>
                    <textarea id="statusNotes" class="form-control" rows="2" maxlength="250"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateStatusBtn">
                    <i class="bi bi-arrow-repeat me-2"></i>Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<style>
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
</style>

<script>
const tables = <?= json_encode($tables, JSON_THROW_ON_ERROR); ?>;
const STATUS_LABELS = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    seated: 'Seated',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: 'No-show'
};
const STATUS_BADGES = {
    pending: 'warning',
    confirmed: 'info',
    seated: 'success',
    completed: 'secondary',
    cancelled: 'danger',
    no_show: 'danger'
};

const filterDate = document.getElementById('filterDate');
const filterStatus = document.getElementById('filterStatus');
const filterTable = document.getElementById('filterTable');
const reservationsTableBody = document.getElementById('reservationsTableBody');
const reservationCount = document.getElementById('reservationCount');
const summaryEls = document.querySelectorAll('[data-summary]');
const reservationModalEl = document.getElementById('reservationModal');
const statusModalEl = document.getElementById('statusModal');
let reservationModal;
let statusModal;

function trapFocus(modalEl) {
    const focusableSelectors = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';
    const focusableEls = Array.from(modalEl.querySelectorAll(focusableSelectors));
    if (!focusableEls.length) return;

    const first = focusableEls[0];
    const last = focusableEls[focusableEls.length - 1];
    modalEl.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
}

const reservationForm = document.getElementById('reservationForm');
const reservationIdField = document.getElementById('reservationId');
const reservationTableField = document.getElementById('reservationTable');
const reservationDateField = document.getElementById('reservationDate');
const reservationTimeField = document.getElementById('reservationTime');
const reservationDurationField = document.getElementById('reservationDuration');
const reservationPartySizeField = document.getElementById('reservationPartySize');
const reservationGuestNameField = document.getElementById('reservationGuestName');
const reservationGuestPhoneField = document.getElementById('reservationGuestPhone');
const reservationStatusField = document.getElementById('reservationStatus');
const reservationSpecialRequestsField = document.getElementById('reservationSpecialRequests');
const reservationDepositRequiredField = document.getElementById('reservationDepositRequired');
const reservationDepositAmountField = document.getElementById('reservationDepositAmount');
const reservationDepositDueField = document.getElementById('reservationDepositDueAt');
const reservationCancellationPolicyField = document.getElementById('reservationCancellationPolicy');
const reservationDepositNotesField = document.getElementById('reservationDepositNotes');
const depositSummaryCard = document.getElementById('reservationDepositSummaryCard');
const reservationDepositStatusBadge = document.getElementById('reservationDepositStatusBadge');
const reservationDepositBalance = document.getElementById('reservationDepositBalance');
const reservationDepositPayments = document.getElementById('reservationDepositPayments');
const depositPaymentAmountField = document.getElementById('depositPaymentAmount');
const depositPaymentMethodField = document.getElementById('depositPaymentMethod');
const depositPaymentPhoneWrap = document.getElementById('depositPaymentPhoneWrap');
const depositPaymentPhoneField = document.getElementById('depositPaymentPhone');
const depositPaymentReferenceField = document.getElementById('depositPaymentReference');
const depositPaymentNotesField = document.getElementById('depositPaymentNotes');
const depositPaymentGatewayStatus = document.getElementById('depositPaymentGatewayStatus');
const recordDepositPaymentBtn = document.getElementById('recordDepositPaymentBtn');

const statusReservationIdField = document.getElementById('statusReservationId');
const statusSelectField = document.getElementById('statusSelect');
const statusNotesField = document.getElementById('statusNotes');

const alertContainer = document.getElementById('alertContainer');
const newReservationBtn = document.getElementById('newReservationBtn');
const saveReservationBtn = document.getElementById('saveReservationBtn');
const refreshBtn = document.getElementById('refreshBtn');
const clearFiltersBtn = document.getElementById('clearFiltersBtn');
const updateStatusBtn = document.getElementById('updateStatusBtn');

const totalFields = document.querySelectorAll('[data-summary]');

function todayISO() {
    const today = new Date();
    const tzOffset = today.getTimezoneOffset();
    const local = new Date(today.getTime() - tzOffset * 60000);
    return local.toISOString().split('T')[0];
}

function showAlert(type, message) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.role = 'alert';
    alert.innerHTML = `
        <div>${message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    alertContainer.append(alert);
    setTimeout(() => {
        const alertInstance = bootstrap.Alert.getOrCreateInstance(alert);
        alertInstance.close();
    }, 6000);
}

const RESERVATION_GATEWAY = window.RESERVATION_GATEWAY_CONFIG || { enabled: false, provider: null, currency: (window.CURRENCY_CONFIG?.code || 'KES') };
const AUTO_REFRESH_INTERVAL_MS = 15000;
let reservationsAutoRefreshTimer = null;
let reservationsLastSyncedAt = null;

let depositGatewayReference = null;
let depositGatewayPollTimeout = null;
let depositGatewayPollAttempts = 0;
const DEPOSIT_GATEWAY_POLL_INTERVAL = 4000;
const DEPOSIT_GATEWAY_MAX_ATTEMPTS = 30;

async function loadSummary() {
    try {
        const params = new URLSearchParams({
            action: 'summary',
            date: filterDate.value
        });
        const response = await fetch(`api/restaurant-reservations.php?${params.toString()}`);
        const result = await response.json();
        if (result.success) {
            totalFields.forEach(el => {
                const key = el.getAttribute('data-summary');
                el.textContent = result.summary[key] ?? 0;
            });
        }
    } catch (error) {
        console.error('Summary load failed', error);
        throw error;
    }
}

async function loadReservations() {
    reservationsTableBody.innerHTML = `
        <tr>
            <td colspan="9" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted">Fetching reservations...</div>
            </td>
        </tr>`;

    try {
        const params = new URLSearchParams({ action: 'list' });
        if (filterDate.value) params.set('date', filterDate.value);
        if (filterStatus.value) params.set('status', filterStatus.value);
        if (filterTable.value) params.set('table_id', filterTable.value);

        const response = await fetch(`api/restaurant-reservations.php?${params.toString()}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load reservations');
        }

        const reservations = result.reservations || [];
        reservationCount.textContent = `${reservations.length} reservation${reservations.length === 1 ? '' : 's'}`;

        if (!reservations.length) {
            reservationsTableBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="bi bi-info-circle me-2"></i>No reservations found for the selected filters.
                    </td>
                </tr>`;
            return;
        }

        reservationsTableBody.innerHTML = reservations.map(renderReservationRow).join('');
    } catch (error) {
        console.error('Reservation load failed', error);
        reservationsTableBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>${error.message}
                </td>
            </tr>`;
        throw error;
    }
}

function updateLiveSyncIndicator(state, label) {
    const dot = document.getElementById('reservationSyncDot');
    const text = document.getElementById('reservationSyncLabel');
    if (dot) {
        dot.setAttribute('data-state', state);
    }
    if (text) {
        text.textContent = label;
    }
}

async function refreshReservations(triggeredByUser = false) {
    if (reservationsAutoRefreshTimer) {
        clearTimeout(reservationsAutoRefreshTimer);
        reservationsAutoRefreshTimer = null;
    }

    updateLiveSyncIndicator('syncing', triggeredByUser ? 'Syncing…' : 'Auto-syncing…');

    try {
        await Promise.all([loadSummary(), loadReservations()]);
        reservationsLastSyncedAt = new Date();
        const label = reservationsLastSyncedAt
            ? `Updated ${reservationsLastSyncedAt.toLocaleTimeString()}`
            : 'Live updates on';
        updateLiveSyncIndicator('live', label);
    } catch (error) {
        updateLiveSyncIndicator('error', 'Sync failed – retrying');
    } finally {
        scheduleReservationsAutoRefresh();
    }
}

function scheduleReservationsAutoRefresh() {
    if (document.hidden) {
        return;
    }
    reservationsAutoRefreshTimer = setTimeout(() => refreshReservations(false), AUTO_REFRESH_INTERVAL_MS);
}

function renderReservationRow(reservation) {
    const badgeClass = STATUS_BADGES[reservation.status] || 'secondary';
    const statusLabel = STATUS_LABELS[reservation.status] || reservation.status;
    const guestInfo = `
        <div class="fw-semibold">${escapeHtml(reservation.guest_name || '-')}</div>
        <div class="small text-muted">${escapeHtml(reservation.guest_phone || '')}</div>
    `;
    const tableLabel = buildTableLabel(reservation);
    const actions = buildRowActions(reservation);
    const depositSummary = buildDepositSummaryChip(reservation);

    return `
        <tr>
            <td>${tableLabel}</td>
            <td>${guestInfo}</td>
            <td><span class="badge bg-light text-dark">${reservation.party_size || '-'}</span></td>
            <td>${formatDate(reservation.reservation_date)}</td>
            <td>${formatTime(reservation.reservation_time)}</td>
            <td><span class="badge bg-${badgeClass}">${statusLabel}</span></td>
            <td>${depositSummary}</td>
            <td class="text-truncate" style="max-width: 180px;" title="${reservation.special_requests || ''}">${reservation.special_requests || '<span class="text-muted">None</span>'}</td>
            <td>${escapeHtml(reservation.created_by_name || '-')}</td>
            <td class="text-end">${actions}</td>
        </tr>`;
}

function buildDepositSummaryChip(reservation) {
    if (!reservation.deposit_required) {
        return '<span class="badge bg-secondary-subtle text-secondary">Not required</span>';
    }

    const status = (reservation.deposit_status || 'pending').toLowerCase();
    const badgeMap = {
        paid: 'success',
        pending: 'warning',
        due: 'danger',
        waived: 'secondary',
        forfeited: 'dark',
        refunded: 'info'
    };
    const amount = reservation.deposit_amount ? Number(reservation.deposit_amount).toFixed(2) : '-';
    const paid = Number(reservation.deposit_total_paid || 0).toFixed(2);
    const balance = reservation.deposit_balance !== null && reservation.deposit_balance !== undefined
        ? Number(reservation.deposit_balance).toFixed(2)
        : '-';
    const badge = badgeMap[status] || 'secondary';
    const label = status.replace('_', ' ').replace(/\w/g, c => c.toUpperCase());
    return `
        <div class="stack-xxs">
            <div><span class="badge bg-${badge}">${label}</span></div>
            <div class="small text-muted">${paid} / ${amount}<br><strong>Bal:</strong> ${balance}</div>
        </div>
    `;
}

function buildTableLabel(reservation) {
    const parts = [];
    if (reservation.table_number) {
        parts.push(`Table ${escapeHtml(reservation.table_number)}`);
    }
    if (reservation.table_name) {
        parts.push(escapeHtml(reservation.table_name));
    }
    const label = parts.length ? parts.join(' · ') : `Table #${reservation.table_id}`;
    const capacity = reservation.capacity ? `${reservation.capacity} pax` : '';
    return `<div class="fw-semibold">${label}</div><div class="small text-muted">${capacity}</div>`;
}

function buildRowActions(reservation) {
    const id = reservation.id;
    const editBtn = `<button class="btn btn-sm btn-outline-primary me-2" data-action="edit" data-id="${id}"><i class="bi bi-pencil-square"></i></button>`;
    const statusBtn = `<button class="btn btn-sm btn-outline-secondary" data-action="status" data-id="${id}" data-status="${reservation.status}"><i class="bi bi-arrow-repeat"></i></button>`;
    return `<div class="btn-group" role="group">${editBtn}${statusBtn}</div>`;
}

function escapeHtml(value) {
    return (value || '').replace(/[&<>'"]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[c]);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr + 'T00:00:00');
    if (Number.isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return '-';
    const [hours, minutes] = timeStr.split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) return timeStr;
    const date = new Date();
    date.setHours(hours, minutes, 0, 0);
    return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function resetReservationForm() {
    reservationForm.reset();
    reservationIdField.value = '';
    reservationDurationField.value = 120;
    reservationStatusField.value = 'confirmed';
    toggleDepositFields(false);
    depositSummaryCard.hidden = true;
    reservationDepositPayments.innerHTML = 'No payments yet.';
}

function openCreateModal() {
    resetReservationForm();
    reservationModalLabel.textContent = 'Create Reservation';
    reservationDateField.value = filterDate.value || todayISO();
    reservationModal.show();
}

async function openEditModal(reservationId) {
    resetReservationForm();
    reservationModalLabel.textContent = 'Edit Reservation';

    try {
        const params = new URLSearchParams({
            action: 'get',
            reservation_id: reservationId
        });
        const response = await fetch(`api/restaurant-reservations.php?${params.toString()}`);
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Unable to load reservation');
        }

        const reservation = result.reservation;
        reservationIdField.value = reservation.id;
        reservationTableField.value = reservation.table_id;
        reservationDateField.value = reservation.reservation_date;
        reservationTimeField.value = reservation.reservation_time?.substring(0,5);
        reservationDurationField.value = reservation.duration_minutes || 120;
        reservationPartySizeField.value = reservation.party_size || 1;
        reservationGuestNameField.value = reservation.guest_name || '';
        reservationGuestPhoneField.value = reservation.guest_phone || '';
        reservationStatusField.value = reservation.status || 'confirmed';
        reservationSpecialRequestsField.value = reservation.special_requests || '';
        reservationDepositRequiredField.checked = !!reservation.deposit_required;
        toggleDepositFields(reservationDepositRequiredField.checked);
        reservationDepositAmountField.value = reservation.deposit_amount || '';
        reservationDepositDueField.value = normalizeDateTimeLocalValue(reservation.deposit_due_at);
        reservationCancellationPolicyField.value = reservation.cancellation_policy || '';
        reservationDepositNotesField.value = reservation.deposit_notes || '';
        updateDepositSummary(reservation);

        reservationModal.show();
    } catch (error) {
        showAlert('danger', error.message);
    }
}

async function saveReservation() {
    if (!reservationForm.checkValidity()) {
        reservationForm.reportValidity();
        return;
    }

    const payload = {
        action: reservationIdField.value ? 'update' : 'create',
        reservation_id: reservationIdField.value || undefined,
        table_id: reservationTableField.value,
        reservation_date: reservationDateField.value,
        reservation_time: reservationTimeField.value,
        duration_minutes: reservationDurationField.value,
        party_size: reservationPartySizeField.value,
        guest_name: reservationGuestNameField.value,
        guest_phone: reservationGuestPhoneField.value,
        status: reservationStatusField.value,
        special_requests: reservationSpecialRequestsField.value,
        deposit_required: reservationDepositRequiredField.checked ? 1 : 0,
        deposit_amount: reservationDepositRequiredField.checked ? reservationDepositAmountField.value : null,
        deposit_due_at: reservationDepositRequiredField.checked ? reservationDepositDueField.value : null,
        cancellation_policy: reservationDepositRequiredField.checked ? reservationCancellationPolicyField.value : null,
        deposit_notes: reservationDepositRequiredField.checked ? reservationDepositNotesField.value : null
    };

    const capacity = parseInt(reservationTableField.selectedOptions[0]?.dataset.capacity || '0', 10);
    if (capacity && parseInt(payload.party_size, 10) > capacity) {
        if (!confirm('Party size exceeds table capacity. Continue?')) {
            return;
        }
    }

    saveReservationBtn.disabled = true;
    saveReservationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

    try {
        const response = await fetch('api/restaurant-reservations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to save reservation');
        }

        reservationModal.hide();
        showAlert('success', result.message || 'Reservation saved successfully.');
        await loadSummary();
        await loadReservations();
    } catch (error) {
        showAlert('danger', error.message);
    } finally {
        saveReservationBtn.disabled = false;
        saveReservationBtn.innerHTML = '<i class="bi bi-save me-2"></i>Save Reservation';
    }
}

function openStatusModal(reservationId, currentStatus) {
    statusReservationIdField.value = reservationId;
    statusSelectField.value = currentStatus || 'confirmed';
    statusNotesField.value = '';
    statusModal.show();
}

async function updateStatus() {
    const reservationId = statusReservationIdField.value;
    const status = statusSelectField.value;

    if (!reservationId) {
        return;
    }

    updateStatusBtn.disabled = true;
    updateStatusBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating...';

    try {
        const response = await fetch('api/restaurant-reservations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                reservation_id: reservationId,
                status,
                notes: statusNotesField.value
            })
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to update status');
        }

        statusModal.hide();
        showAlert('success', result.message || 'Reservation status updated.');
        await loadSummary();
        await loadReservations();
    } catch (error) {
        showAlert('danger', error.message);
    } finally {
        updateStatusBtn.disabled = false;
        updateStatusBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Update Status';
    }
}

reservationsTableBody.addEventListener('click', event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const reservationId = button.getAttribute('data-id');
    const action = button.getAttribute('data-action');

    if (action === 'edit') {
        openEditModal(reservationId);
    } else if (action === 'status') {
        const currentStatus = button.getAttribute('data-status');
        openStatusModal(reservationId, currentStatus);
    }
});

newReservationBtn.addEventListener('click', openCreateModal);
saveReservationBtn.addEventListener('click', saveReservation);
refreshBtn.addEventListener('click', () => {
    loadSummary();
    loadReservations();
});
clearFiltersBtn.addEventListener('click', () => {
    filterDate.value = todayISO();
    filterStatus.value = '';
    filterTable.value = '';
    loadSummary();
    loadReservations();
});
updateStatusBtn.addEventListener('click', updateStatus);
filterDate.addEventListener('change', () => {
    loadSummary();
    loadReservations();
});
filterStatus.addEventListener('change', loadReservations);
filterTable.addEventListener('change', loadReservations);

const bootstrapInit = () => {
    reservationModal = new bootstrap.Modal(reservationModalEl);
    statusModal = new bootstrap.Modal(statusModalEl);
    trapFocus(reservationModalEl);
    trapFocus(statusModalEl);
    filterDate.value = todayISO();
    reservationDepositRequiredField.addEventListener('change', () => {
        toggleDepositFields(reservationDepositRequiredField.checked);
    });
    depositPaymentMethodField.addEventListener('change', handleDepositPaymentMethodChange);
    recordDepositPaymentBtn.addEventListener('click', recordDepositPayment);
    refreshReservations(true);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshReservations(false);
        }
    });
};

if (typeof bootstrap === 'undefined') {
    window.addEventListener('load', bootstrapInit, { once: true });
} else {
    bootstrapInit();
}

function toggleDepositFields(enabled) {
    [
        reservationDepositAmountField,
        reservationDepositDueField,
        reservationCancellationPolicyField,
        reservationDepositNotesField
    ].forEach(field => {
        field.disabled = !enabled;
        if (!enabled) {
            field.value = '';
        }
    });
    depositSummaryCard.hidden = !enabled;
}

function normalizeDateTimeLocalValue(value) {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }
    const iso = parsed.toISOString();
    return iso.slice(0, 16);
}

function updateDepositSummary(reservation) {
    const enabled = !!reservation.deposit_required;
    depositSummaryCard.hidden = !enabled;
    if (!enabled) {
        return;
    }

    const status = (reservation.deposit_status || 'pending').replace('_', ' ');
    reservationDepositStatusBadge.innerHTML = `<span class="badge bg-primary">${status}</span>`;
    const balance = reservation.deposit_balance !== null && reservation.deposit_balance !== undefined
        ? Number(reservation.deposit_balance).toFixed(2)
        : '-';
    reservationDepositBalance.textContent = balance;

    if (reservation.deposit_payments && reservation.deposit_payments.length > 0) {
        reservationDepositPayments.innerHTML = reservation.deposit_payments.map(payment => `
            <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                <div>
                    <div class="fw-semibold">${Number(payment.amount).toFixed(2)} ${escapeHtml(payment.method || '')}</div>
                    <div class="text-muted small">${formatDateTime(payment.recorded_at)}${payment.reference ? ' · Ref: ' + escapeHtml(payment.reference) : ''}${payment.customer_phone ? ' · ' + escapeHtml(payment.customer_phone) : ''}</div>
                    ${payment.notes ? `<div class="small">${escapeHtml(payment.notes)}</div>` : ''}
                </div>
                <div class="text-muted small text-end">${payment.recorded_by ? `User #${payment.recorded_by}` : ''}</div>
            </div>
        `).join('');
    } else {
        reservationDepositPayments.innerHTML = 'No payments yet.';
    }
    clearDepositGatewayStatus();
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
}

function handleDepositPaymentMethodChange() {
    const method = depositPaymentMethodField.value;
    const isMobile = method === 'mobile_money';
    depositPaymentPhoneWrap.hidden = !isMobile;
    if (!isMobile) {
        depositPaymentPhoneField.value = '';
    }
}

async function recordDepositPayment() {
    const reservationId = reservationIdField.value;
    if (!reservationId) {
        showAlert('danger', 'Save the reservation before logging payments.');
        return;
    }

    const amount = parseFloat(depositPaymentAmountField.value);
    if (Number.isNaN(amount) || amount <= 0) {
        depositPaymentAmountField.focus();
        return;
    }

    const isMobileMoney = depositPaymentMethodField.value === 'mobile_money';
    if (isMobileMoney) {
        const phone = depositPaymentPhoneField.value.trim();
        if (phone.length < 7) {
            depositPaymentPhoneField.focus();
            return;
        }
    }

    const paymentPayload = {
        amount,
        method: depositPaymentMethodField.value,
        customer_phone: isMobileMoney ? depositPaymentPhoneField.value : null,
        reference: depositPaymentReferenceField.value,
        notes: depositPaymentNotesField.value
    };

    recordDepositPaymentBtn.disabled = true;
    recordDepositPaymentBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Recording...';

    try {
        if (isMobileMoney && RESERVATION_GATEWAY.enabled) {
            await initiateReservationGatewayPayment(reservationId, paymentPayload);
        } else {
            await submitDepositPayment(reservationId, paymentPayload);
        }
    } catch (error) {
        showAlert('danger', error.message);
    } finally {
        recordDepositPaymentBtn.disabled = false;
        recordDepositPaymentBtn.innerHTML = '<i class="bi bi-cash-coin me-2"></i>Record Payment';
    }
}

async function submitDepositPayment(reservationId, paymentPayload) {
    const response = await fetch('api/restaurant-reservations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'record_deposit_payment',
            reservation_id: reservationId,
            ...paymentPayload
        })
    });
    const result = await response.json();
    if (!result.success) {
        throw new Error(result.message || 'Failed to record payment');
    }

    showAlert('success', result.message || 'Deposit payment recorded.');
    updateDepositSummary(result.reservation);
    depositPaymentAmountField.value = '';
    depositPaymentMethodField.value = '';
    handleDepositPaymentMethodChange();
    depositPaymentReferenceField.value = '';
    depositPaymentNotesField.value = '';
    depositPaymentPhoneField.value = '';
    clearDepositGatewayStatus();
    await loadReservations();
}

function setDepositGatewayStatus(message, type = 'info') {
    if (!depositPaymentGatewayStatus) return;
    depositPaymentGatewayStatus.className = `alert alert-${type}`;
    depositPaymentGatewayStatus.textContent = message;
    depositPaymentGatewayStatus.classList.remove('d-none');
}

function clearDepositGatewayStatus() {
    if (!depositPaymentGatewayStatus) return;
    depositPaymentGatewayStatus.classList.add('d-none');
    depositPaymentGatewayStatus.textContent = '';
    depositGatewayReference = null;
    depositGatewayPollAttempts = 0;
    if (depositGatewayPollTimeout) {
        clearTimeout(depositGatewayPollTimeout);
        depositGatewayPollTimeout = null;
    }
}

async function initiateReservationGatewayPayment(reservationId, paymentPayload) {
    try {
        setDepositGatewayStatus('Sending mobile money prompt to customer...', 'info');
        const requestBody = {
            amount: paymentPayload.amount,
            currency: RESERVATION_GATEWAY.currency,
            customer_phone: paymentPayload.customer_phone,
            customer_name: reservationGuestNameField.value || null,
            customer_email: null,
            metadata: {
                source: 'restaurant_reservation_deposit',
                reservation_id: reservationId,
                guest_phone: reservationGuestPhoneField.value,
                deposit_form: true
            },
            context_type: 'restaurant_reservation',
            context_id: Number(reservationId) || Date.now()
        };

        const response = await fetch('api/payments/initiate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Unable to initiate payment');
        }

        const data = result.data || {};
        depositGatewayReference = data.reference;
        depositGatewayPollAttempts = 0;
        setDepositGatewayStatus(data.instructions || 'Prompt sent. Waiting for customer confirmation...', 'info');

        if (['success', 'completed', 'paid', 'approved'].includes((data.status || '').toLowerCase())) {
            await submitDepositPayment(reservationId, {
                ...paymentPayload,
                reference: paymentPayload.reference || data.provider_reference || data.reference
            });
            return;
        }

        await pollReservationGatewayStatus(reservationId, paymentPayload);
    } catch (error) {
        clearDepositGatewayStatus();
        throw error;
    }
}

async function pollReservationGatewayStatus(reservationId, paymentPayload) {
    if (!depositGatewayReference) {
        clearDepositGatewayStatus();
        throw new Error('Missing payment reference; please try again.');
    }

    depositGatewayPollAttempts += 1;
    if (depositGatewayPollAttempts > DEPOSIT_GATEWAY_MAX_ATTEMPTS) {
        setDepositGatewayStatus('Payment is still pending. Please confirm with the customer or try again.', 'warning');
        throw new Error('Payment pending. No charge recorded.');
    }

    try {
        const response = await fetch(`api/payments/status.php?reference=${encodeURIComponent(depositGatewayReference)}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            throw new Error(result.message || 'Unable to fetch payment status');
        }

        const record = result.data;
        const status = (record.status || '').toLowerCase();

        if (['success', 'completed', 'paid', 'approved'].includes(status)) {
            setDepositGatewayStatus('Deposit confirmed! Recording payment...', 'success');
            await submitDepositPayment(reservationId, {
                ...paymentPayload,
                reference: paymentPayload.reference || record.reference || depositGatewayReference
            });
            return;
        }

        if (['failed', 'declined', 'cancelled', 'expired', 'error'].includes(status)) {
            clearDepositGatewayStatus();
            throw new Error(`Payment failed: ${record.status}`);
        }

        setDepositGatewayStatus(record.instructions || 'Awaiting customer confirmation...', 'info');
        depositGatewayPollTimeout = setTimeout(() => {
            pollReservationGatewayStatus(reservationId, paymentPayload).catch(error => {
                showAlert('danger', error.message);
            });
        }, DEPOSIT_GATEWAY_POLL_INTERVAL);
    } catch (error) {
        clearDepositGatewayStatus();
        throw error;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
