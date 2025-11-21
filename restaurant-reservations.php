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

$pageTitle = 'Restaurant Reservations';
include 'includes/header.php';
?>

<div class="container-fluid py-4 stack-lg" role="main">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Restaurant Reservations</h1>
            <p class="text-muted mb-0">Manage table bookings, track statuses, and coordinate seatings.</p>
        </div>
        <div>
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
                        <th>Notes</th>
                        <th>Created By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="reservationsTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">Loading reservations...</td>
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
    }
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

    return `
        <tr>
            <td>${tableLabel}</td>
            <td>${guestInfo}</td>
            <td><span class="badge bg-light text-dark">${reservation.party_size || '-'}</span></td>
            <td>${formatDate(reservation.reservation_date)}</td>
            <td>${formatTime(reservation.reservation_time)}</td>
            <td><span class="badge bg-${badgeClass}">${statusLabel}</span></td>
            <td class="text-truncate" style="max-width: 180px;" title="${reservation.special_requests || ''}">${reservation.special_requests || '<span class="text-muted">None</span>'}</td>
            <td>${escapeHtml(reservation.created_by_name || '-')}</td>
            <td class="text-end">${actions}</td>
        </tr>`;
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
        special_requests: reservationSpecialRequestsField.value
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
    loadSummary();
    loadReservations();
};

if (typeof bootstrap === 'undefined') {
    window.addEventListener('load', bootstrapInit, { once: true });
} else {
    bootstrapInit();
}
</script>

<?php include 'includes/footer.php'; ?>
