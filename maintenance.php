<?php
require_once 'includes/bootstrap.php';

use App\Services\MaintenanceService;

$auth->requireLogin();

$allowedRoles = ['admin', 'manager', 'maintenance_manager', 'maintenance_staff', 'maintenance', 'technician', 'engineer', 'frontdesk'];
$hasAccess = false;
foreach ($allowedRoles as $role) {
    if ($auth->hasRole($role)) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    redirect('index.php?error=access_denied');
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$service = new MaintenanceService($pdo);

try {
    $service->ensureSchema();
} catch (Throwable $e) {
    $_SESSION['error_message'] = 'Unable to initialize maintenance module: ' . $e->getMessage();
}

$summary = [];
$initialRequests = [];
$currentUserId = (int)$auth->getUserId();
try {
    $summary = $service->getSummary();
    $initialRequests = $service->getRequests(['limit' => 50]);
} catch (Throwable $e) {
    $summary = [];
    $initialRequests = [];
}

$rooms = [];
try {
    $roomsStmt = $pdo->query("SELECT id, room_number FROM rooms WHERE is_active = 1 ORDER BY room_number");
    $rooms = $roomsStmt ? $roomsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $rooms = [];
}

$bookings = [];
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'room_bookings'");
    if ($tableCheck && $tableCheck->fetchColumn()) {
        $bookingStmt = $pdo->prepare(
            "SELECT id, booking_number, guest_name, status FROM room_bookings WHERE status IN ('confirmed','checked_in') ORDER BY booking_number DESC LIMIT 100"
        );
        $bookingStmt->execute();
        $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $bookings = [];
}

$staff = [];
try {
    $staffStmt = $pdo->prepare(
        "SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name, username"
    );
    $staffStmt->execute();
    $staff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $staff = [];
}

$statuses = [
    ['value' => 'open', 'label' => 'Open'],
    ['value' => 'assigned', 'label' => 'Assigned'],
    ['value' => 'in_progress', 'label' => 'In Progress'],
    ['value' => 'on_hold', 'label' => 'On Hold'],
    ['value' => 'resolved', 'label' => 'Resolved'],
    ['value' => 'closed', 'label' => 'Closed'],
];

$priorities = [
    ['value' => 'high', 'label' => 'High'],
    ['value' => 'normal', 'label' => 'Normal'],
    ['value' => 'low', 'label' => 'Low'],
];

$pageTitle = 'Maintenance';
include 'includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-tools me-2"></i>Maintenance Dashboard</h4>
        <small class="text-muted">Track issues reported by guests and staff, assign technicians, and monitor progress.</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" id="guestPortalBtn">
            <i class="bi bi-link-45deg me-2"></i>Guest Portal Link
        </button>
        <button class="btn btn-outline-primary" id="simulateGuestReportBtn">
            <i class="bi bi-megaphone me-2"></i>Simulate Guest Report
        </button>
        <button class="btn btn-primary" id="newRequestBtn">
            <i class="bi bi-plus-circle me-2"></i>New Request
        </button>
    </div>
</div>

<div class="row g-3 mb-3" id="technicianLoadRow">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <strong>Technician Workload</strong>
                    <div class="text-muted small">Live view of pending assignments per technician</div>
                </div>
                <button class="btn btn-sm btn-outline-secondary" id="refreshTechLoadBtn"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="technicianLoadTable">
                        <thead class="table-light">
                            <tr>
                                <th>Technician</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">In Progress</th>
                                <th class="text-center">On Hold</th>
                                <th class="text-center">Total Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No active assignments.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-3" id="summaryCards">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Open</p>
                <h4 class="fw-bold" id="summaryOpen">0</h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Assigned</p>
                <h4 class="fw-bold text-info" id="summaryAssigned">0</h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">In Progress</p>
                <h4 class="fw-bold text-primary" id="summaryInProgress">0</h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">On Hold</p>
                <h4 class="fw-bold text-warning" id="summaryOnHold">0</h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Resolved</p>
                <h4 class="fw-bold text-success" id="summaryResolved">0</h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Closed</p>
                <h4 class="fw-bold text-secondary" id="summaryClosed">0</h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">High Priority Open</p>
                <h4 class="fw-bold text-danger" id="summaryHighPriority">0</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Overdue Tickets</p>
                <h4 class="fw-bold text-warning" id="summaryOverdue">0</h4>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <form class="row g-3 flex-grow-1" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="filterStatus">
                        <option value="">All statuses</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status['value']) ?>"><?= htmlspecialchars($status['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Priority</label>
                <select class="form-select" name="priority" id="filterPriority">
                    <option value="">All priorities</option>
                    <?php foreach ($priorities as $priority): ?>
                        <option value="<?= htmlspecialchars($priority['value']) ?>"><?= htmlspecialchars($priority['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Assigned To</label>
                <select class="form-select" name="assigned_to" id="filterAssigned">
                    <option value="">Any technician</option>
                    <?php foreach ($staff as $member): ?>
                        <?php
                            $name = trim(($member['full_name'] ?? '') . ' ' . ($member['username'] ?? ''));
                            $name = $name !== '' ? $name : $member['username'];
                        ?>
                        <option value="<?= (int)$member['id'] ?>">
                            <?= htmlspecialchars($name . ' (' . strtoupper($member['role'] ?? '') . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter me-2"></i>Apply Filters
                </button>
                <button type="button" class="btn btn-outline-secondary" id="clearFiltersBtn">
                    Clear
                </button>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reporter Type</label>
                <select class="form-select" name="reporter_type" id="filterReporterType">
                    <option value="">Any reporter</option>
                    <option value="guest">Guest</option>
                    <option value="staff">Staff</option>
                    <option value="frontdesk">Front Desk</option>
                    <option value="system">System</option>
                    <option value="other">Other</option>
                </select>
            </div>
            </form>
            <div class="d-flex flex-column gap-2">
                <button class="btn btn-outline-dark" id="myAssignmentsBtn"><i class="bi bi-person-badge me-1"></i>My Assignments</button>
                <button class="btn btn-outline-secondary" id="todayDueBtn"><i class="bi bi-calendar-check me-1"></i>Due Today</button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="requestsTable">
                <thead class="table-light">
                    <tr>
                        <th>Issue</th>
                        <th>Room / Booking</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reporter</th>
                        <th>Tracking</th>
                        <th>SLA / Timing</th>
                        <th>Assigned</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="text-center text-muted py-4 d-none" id="emptyState">
            <i class="bi bi-emoji-smile fs-1"></i>
            <p class="mt-2 mb-0">No maintenance requests match your filters.</p>
        </div>
    </div>
</div>

<!-- Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="requestForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestModalTitle">New Maintenance Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="requestId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Issue Title *</label>
                            <input type="text" class="form-control" name="title" id="requestTitle" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority" id="requestPriority">
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= htmlspecialchars($priority['value']) ?>">
                                        <?= htmlspecialchars($priority['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="requestStatus">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status['value']) ?>">
                                        <?= htmlspecialchars($status['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <select class="form-select" name="room_id" id="requestRoom">
                                <option value="">No room selected</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= (int)$room['id'] ?>">
                                        <?= htmlspecialchars('Room ' . $room['room_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Booking</label>
                            <select class="form-select" name="booking_id" id="requestBooking">
                                <option value="">No booking linked</option>
                                <?php foreach ($bookings as $booking): ?>
                                    <option value="<?= (int)$booking['id'] ?>">
                                        <?= htmlspecialchars(($booking['booking_number'] ?? ('#' . $booking['id'])) . ' - ' . ($booking['guest_name'] ?? 'Guest')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date" id="requestDueDate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Started At</label>
                            <input type="datetime-local" class="form-control" name="started_at" id="requestStartedAt">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Completed At</label>
                            <input type="datetime-local" class="form-control" name="completed_at" id="requestCompletedAt">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign Technician</label>
                            <select class="form-select" name="assigned_to" id="requestAssignedTo">
                                <option value="">Unassigned</option>
                                <?php foreach ($staff as $member): ?>
                                    <?php
                                        $name = trim(($member['full_name'] ?? '') . ' ' . ($member['username'] ?? ''));
                                        $name = $name !== '' ? $name : $member['username'];
                                    ?>
                                    <option value="<?= (int)$member['id'] ?>">
                                        <?= htmlspecialchars($name . ' (' . strtoupper($member['role'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Reporter Type</label>
                            <select class="form-select" name="reporter_type" id="requestReporterType">
                                <option value="staff">Staff</option>
                                <option value="guest">Guest</option>
                                <option value="system">System</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Reference Code</label>
                            <input type="text" class="form-control" name="reference_code" id="requestReference">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporter Name</label>
                            <input type="text" class="form-control" name="reporter_name" id="requestReporterName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporter Contact</label>
                            <input type="text" class="form-control" name="reporter_contact" id="requestReporterContact">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="requestDescription" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Internal Notes</label>
                            <textarea class="form-control" name="notes" id="requestNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Logs Modal -->
<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Maintenance Activity Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="logsContainer" class="list-group list-group-flush"></div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1200;">
    <div id="feedbackToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <small id="toastTime"></small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JavaScript bundle is missing.');
        return;
    }

    const INITIAL_REQUESTS = <?= json_encode($initialRequests, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const SUMMARY_COUNTS = <?= json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const CURRENT_USER_ID = <?= json_encode($currentUserId) ?>;
    const STATUS_LABELS = {
        open: 'Open',
        assigned: 'Assigned',
        in_progress: 'In Progress',
        on_hold: 'On Hold',
        resolved: 'Resolved',
        closed: 'Closed'
    };
    const STATUS_BADGES = {
        open: 'secondary',
        assigned: 'info',
        in_progress: 'primary',
        on_hold: 'warning',
        resolved: 'success',
        closed: 'dark'
    };
    const PRIORITY_BADGES = {
        high: 'danger',
        normal: 'primary',
        low: 'secondary'
    };

    const requestModalElement = document.getElementById('requestModal');
    const logsModalElement = document.getElementById('logsModal');
    const toastElement = document.getElementById('feedbackToast');
    const requestsTableBody = document.querySelector('#requestsTable tbody');

    if (!requestModalElement || !logsModalElement || !toastElement || !requestsTableBody) {
        console.error('Maintenance dashboard elements missing; aborting setup.');
        return;
    }

    const requestModal = new bootstrap.Modal(requestModalElement);
    const logsModal = new bootstrap.Modal(logsModalElement);
    const toastInstance = new bootstrap.Toast(toastElement, { delay: 3500 });

    const emptyState = document.getElementById('emptyState');
    let currentRequests = Array.isArray(INITIAL_REQUESTS) ? INITIAL_REQUESTS : [];
    let editingRequestId = null;
    let lastFocusedTrigger = null;

    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        return text.replace(/["&'<>]/g, char => ({ '"': '&quot;', '&': '&amp;', "'": '&#39;', '<': '&lt;', '>': '&gt;' }[char] || char));
    }

    function showToast(message, title = 'Notice') {
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastBody').textContent = message;
        document.getElementById('toastTime').textContent = new Date().toLocaleTimeString();
        toastInstance.show();
    }

    function renderSummary(data) {
        const counts = Object.assign({
            open: 0,
            assigned: 0,
            in_progress: 0,
            on_hold: 0,
            resolved: 0,
            closed: 0,
            high_priority: 0,
            overdue: 0
        }, data || {});
        document.getElementById('summaryOpen').textContent = counts.open;
        const assignedNode = document.getElementById('summaryAssigned');
        if (assignedNode) assignedNode.textContent = counts.assigned;
        document.getElementById('summaryInProgress').textContent = counts.in_progress;
        const onHoldNode = document.getElementById('summaryOnHold');
        if (onHoldNode) onHoldNode.textContent = counts.on_hold;
        document.getElementById('summaryResolved').textContent = counts.resolved;
        document.getElementById('summaryClosed').textContent = counts.closed;
        document.getElementById('summaryHighPriority').textContent = counts.high_priority;
        document.getElementById('summaryOverdue').textContent = counts.overdue;
    }

    function renderTechnicianLoad(load) {
        const tableBody = document.querySelector('#technicianLoadTable tbody');
        tableBody.innerHTML = '';
        if (!Array.isArray(load) || load.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No active assignments.</td></tr>';
            return;
        }

        load.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(item.technician_name || 'Unknown')}</td>
                <td class="text-center"><span class="badge bg-secondary">${Number(item.pending || 0)}</span></td>
                <td class="text-center"><span class="badge bg-primary">${Number(item.in_progress || 0)}</span></td>
                <td class="text-center"><span class="badge bg-warning text-dark">${Number(item.on_hold || 0)}</span></td>
                <td class="text-center fw-bold">${Number(item.total || 0)}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function parseDate(value, fallbackTime = '00:00:00') {
        if (!value) return null;
        if (value.includes('T') || value.includes(' ')) {
            return new Date(value.replace(' ', 'T'));
        }
        return new Date(value + 'T' + fallbackTime);
    }

    function formatDuration(minutes) {
        if (minutes === null || isNaN(minutes)) return '';
        const abs = Math.abs(minutes);
        const hours = Math.floor(abs / 60);
        const mins = Math.max(0, abs % 60);
        const parts = [];
        if (hours) parts.push(hours + 'h');
        if (mins || parts.length === 0) parts.push(mins + 'm');
        return parts.join(' ');
    }

    function buildTimingHtml(request) {
        const now = new Date();
        const createdAt = parseDate(request.created_at);
        const startedAt = parseDate(request.started_at);
        const completedAt = parseDate(request.completed_at);
        const dueDate = parseDate(request.due_date);

        const pieces = [];

        if (dueDate) {
            const msDiff = dueDate.getTime() - now.getTime();
            const minutesDiff = Math.round(msDiff / 60000);
            const isPast = minutesDiff < 0;
            const badge = isPast ? 'danger' : 'success';
            const label = isPast ? 'Overdue ' + formatDuration(minutesDiff) : 'Due in ' + formatDuration(minutesDiff);
            pieces.push(`<span class="badge bg-${badge} bg-opacity-10 text-${badge}">${escapeHtml(label)}</span>`);
        }

        if (completedAt) {
            pieces.push(`<div class="small text-muted">Closed ${escapeHtml(completedAt.toLocaleString())}</div>`);
        } else if (startedAt) {
            const mins = Math.round((now.getTime() - startedAt.getTime()) / 60000);
            pieces.push(`<div class="text-muted small">In progress ${escapeHtml(formatDuration(mins))}</div>`);
        } else if (createdAt) {
            const mins = Math.round((now.getTime() - createdAt.getTime()) / 60000);
            pieces.push(`<div class="text-muted small">Open for ${escapeHtml(formatDuration(mins))}</div>`);
        }

        return pieces.join('<br>') || '<span class="text-muted">—</span>';
    }

    function renderRequests(requests) {
        requestsTableBody.innerHTML = '';
        if (!Array.isArray(requests) || requests.length === 0) {
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');
        requests.forEach(request => {
            const tr = document.createElement('tr');

            const dueText = request.due_date ? new Date(request.due_date + 'T00:00:00').toLocaleDateString() : '';
            const startedText = request.started_at ? new Date(request.started_at.replace(' ', 'T')).toLocaleString() : '';
            const completedText = request.completed_at ? new Date(request.completed_at.replace(' ', 'T')).toLocaleString() : '';

            const reporter = [];
            if (request.reporter_type) reporter.push(request.reporter_type.charAt(0).toUpperCase() + request.reporter_type.slice(1));
            if (request.reporter_name) reporter.push(request.reporter_name);
            if (request.reporter_contact) reporter.push(request.reporter_contact);

            const badgeClass = PRIORITY_BADGES[request.priority] || 'secondary';
            const statusLabel = STATUS_LABELS[request.status] || request.status;
            const statusChip = STATUS_BADGES[request.status] || 'secondary';

            tr.innerHTML = `
                <td>
                    <strong>${escapeHtml(request.title || 'Untitled')}</strong>
                    ${request.reference_code ? `<div class="small text-muted">Ref: ${escapeHtml(request.reference_code)}</div>` : ''}
                    ${request.description ? `<div class="small text-muted">${escapeHtml(request.description)}</div>` : ''}
                </td>
                <td>
                    ${request.room_number ? `<div><i class="bi bi-door-open me-1"></i>Room ${escapeHtml(request.room_number)}</div>` : ''}
                    ${request.booking_number ? `<div class="small text-muted">Booking ${escapeHtml(request.booking_number)}</div>` : ''}
                </td>
                <td><span class="badge bg-${badgeClass}">${escapeHtml(request.priority || '')}</span></td>
                <td><span class="badge bg-${statusChip}">${escapeHtml(statusLabel)}</span></td>
                <td>${reporter.length ? escapeHtml(reporter.join(' • ')) : '<span class="text-muted">Unknown</span>'}</td>
                <td>${request.tracking_code ? `<span class="badge bg-dark">${escapeHtml(request.tracking_code)}</span>` : '<span class="text-muted">—</span>'}</td>
                <td>${buildTimingHtml(request)}</td>
                <td>${request.assigned_name ? escapeHtml(request.assigned_name) : '<span class="text-muted">Unassigned</span>'}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-secondary" data-action="logs" data-id="${request.id}" title="View log"><i class="bi bi-clock-history"></i></button>
                        <button class="btn btn-outline-primary" data-action="edit" data-id="${request.id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-info" data-action="status" data-id="${request.id}" data-status="assigned" title="Assign / acknowledge"><i class="bi bi-person-check"></i></button>
                        <button class="btn btn-outline-success" data-action="status" data-id="${request.id}" data-status="in_progress" title="Mark in progress"><i class="bi bi-play"></i></button>
                        <button class="btn btn-outline-warning" data-action="status" data-id="${request.id}" data-status="on_hold" title="Pause"><i class="bi bi-pause"></i></button>
                        <button class="btn btn-outline-success" data-action="status" data-id="${request.id}" data-status="resolved" title="Resolve"><i class="bi bi-check2"></i></button>
                        <button class="btn btn-outline-secondary" data-action="status" data-id="${request.id}" data-status="closed" title="Close"><i class="bi bi-check2-circle"></i></button>
                    </div>
                </td>
            `;

            requestsTableBody.appendChild(tr);
        });
    }

    function fetchRequests(params = {}) {
        const query = new URLSearchParams(params);
        return fetch('api/maintenance.php?action=list&' + query.toString(), { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    currentRequests = data.requests || [];
                    renderRequests(currentRequests);
                } else {
                    throw new Error(data.message || 'Unable to fetch maintenance requests.');
                }
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function fetchSummary() {
        return fetch('api/maintenance.php?action=summary', { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) renderSummary(data.summary);
            })
            .catch(() => {});
    }

    function fetchTechnicianLoad() {
        return fetch('api/maintenance.php?action=tech_load', { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) renderTechnicianLoad(data.load || []);
            })
            .catch(() => {});
    }

    function openRequestModal(request = null) {
        lastFocusedTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const data = request ? Object.assign({}, request) : {};
        const hasExisting = Boolean(data.id);

        editingRequestId = hasExisting ? data.id : null;
        document.getElementById('requestForm').reset();

        document.getElementById('requestId').value = hasExisting ? data.id : '';
        document.getElementById('requestTitle').value = data.title || '';
        document.getElementById('requestPriority').value = data.priority || 'normal';
        document.getElementById('requestStatus').value = data.status || 'open';
        document.getElementById('requestRoom').value = data.room_id || '';
        document.getElementById('requestBooking').value = data.booking_id || '';
        document.getElementById('requestDueDate').value = data.due_date || '';
        document.getElementById('requestStartedAt').value = data.started_at ? data.started_at.replace(' ', 'T') : '';
        document.getElementById('requestCompletedAt').value = data.completed_at ? data.completed_at.replace(' ', 'T') : '';
        document.getElementById('requestAssignedTo').value = data.assigned_to || '';
        document.getElementById('requestReporterType').value = data.reporter_type || 'staff';
        document.getElementById('requestReference').value = data.reference_code || '';
        document.getElementById('requestReporterName').value = data.reporter_name || '';
        document.getElementById('requestReporterContact').value = data.reporter_contact || '';
        document.getElementById('requestDescription').value = data.description || '';
        document.getElementById('requestNotes').value = data.notes || '';

        document.getElementById('requestModalTitle').textContent = hasExisting ? 'Edit Maintenance Request' : 'New Maintenance Request';
        requestModal.show();
    }

    function submitRequestForm(event) {
        event.preventDefault();
        const formData = Object.fromEntries(new FormData(event.target).entries());
        formData.action = editingRequestId ? 'update' : 'create';

        fetch('api/maintenance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Unable to save maintenance request.');
                showToast(data.message || 'Maintenance request saved.', 'Success');
                requestModal.hide();
                fetchRequests(getCurrentFilters()).then(fetchSummary);
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function getCurrentFilters() {
        return {
            status: document.getElementById('filterStatus').value,
            priority: document.getElementById('filterPriority').value,
            assigned_to: document.getElementById('filterAssigned').value,
            reporter_type: document.getElementById('filterReporterType').value
        };
    }

    function handleStatusChange(requestId, status) {
        fetch('api/maintenance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', request_id: requestId, status })
        })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Unable to update status.');
                showToast('Status updated.', 'Success');
                fetchRequests(getCurrentFilters()).then(fetchSummary);
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function handleViewLogs(requestId) {
        fetch('api/maintenance.php?action=logs&request_id=' + encodeURIComponent(requestId), { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Unable to load logs.');
                const logs = data.logs || [];
                const container = document.getElementById('logsContainer');
                container.innerHTML = '';
                if (logs.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-3">No log entries.</div>';
                } else {
                    logs.forEach(log => {
                        const item = document.createElement('div');
                        item.className = 'list-group-item';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <strong>${escapeHtml(STATUS_LABELS[log.status] || log.status)}</strong>
                                <span class="text-muted small">${log.created_at ? new Date(log.created_at.replace(' ', 'T')).toLocaleString() : ''}</span>
                            </div>
                            ${log.notes ? `<div class="small">${escapeHtml(log.notes)}</div>` : ''}
                            ${log.created_by_name ? `<div class="small text-muted">By ${escapeHtml(log.created_by_name)}</div>` : ''}
                        `;
                        container.appendChild(item);
                    });
                }
                logsModal.show();
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function handleTableClick(event) {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const requestId = parseInt(button.dataset.id, 10);
        if (!requestId) return;

        const request = currentRequests.find(r => Number(r.id) === requestId);

        switch (action) {
            case 'edit':
                openRequestModal(request || null);
                break;
            case 'status':
                handleStatusChange(requestId, button.dataset.status);
                break;
            case 'logs':
                handleViewLogs(requestId);
                break;
            default:
                break;
        }
    }

    requestModalElement.addEventListener('show.bs.modal', () => {
        setTimeout(() => {
            const firstInput = requestModalElement.querySelector('input, textarea, select, button:not([data-bs-dismiss])');
            if (firstInput instanceof HTMLElement) {
                firstInput.focus({ preventScroll: true });
            }
        }, 0);
    });

    requestModalElement.addEventListener('hide.bs.modal', () => {
        const focused = document.activeElement;
        if (focused instanceof HTMLElement && requestModalElement.contains(focused)) {
            focused.blur();
        }
    });

    requestModalElement.addEventListener('hidden.bs.modal', () => {
        if (lastFocusedTrigger instanceof HTMLElement) {
            lastFocusedTrigger.focus({ preventScroll: true });
            lastFocusedTrigger = null;
        }
    });

    document.getElementById('newRequestBtn').addEventListener('click', () => openRequestModal(null));
    const simulateGuestReportBtn = document.getElementById('simulateGuestReportBtn');
    if (simulateGuestReportBtn) {
        simulateGuestReportBtn.addEventListener('click', () => {
            openRequestModal({
                title: 'Air conditioning not cooling',
                priority: 'high',
                status: 'open',
                reporter_type: 'guest',
                reporter_name: 'Simulated Guest',
                description: 'Guest reports the room air conditioner is blowing warm air.',
                notes: 'Simulated guest report for testing.'
            });
            document.getElementById('requestModalTitle').textContent = 'Simulated Guest Report';
        });
    }
    document.getElementById('requestForm').addEventListener('submit', submitRequestForm);
    requestsTableBody.addEventListener('click', handleTableClick);
    document.getElementById('filterForm').addEventListener('submit', event => {
        event.preventDefault();
        fetchRequests(getCurrentFilters());
    });
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterPriority').value = '';
        document.getElementById('filterAssigned').value = '';
        document.getElementById('filterReporterType').value = '';
        fetchRequests({});
    });

    document.getElementById('guestPortalBtn').addEventListener('click', () => {
        const url = new URL(window.location.origin + '/public-maintenance-report.php');
        navigator.clipboard.writeText(url.href).then(() => {
            showToast('Guest portal link copied to clipboard. Share it with guests to report issues.', 'Link copied');
        }).catch(() => {
            showToast('Guest portal available at ' + url.href, 'Guest Portal');
        });
    });

    document.getElementById('myAssignmentsBtn').addEventListener('click', () => {
        const assignedField = document.getElementById('filterAssigned');
        assignedField.value = CURRENT_USER_ID || '';
        fetchRequests(getCurrentFilters());
    });

    document.getElementById('todayDueBtn').addEventListener('click', () => {
        const statusField = document.getElementById('filterStatus');
        statusField.value = '';
        fetchRequests({ status: '', priority: '', assigned_to: '', reporter_type: '', due_today: 1 });
    });

    document.getElementById('refreshTechLoadBtn').addEventListener('click', () => {
        fetchTechnicianLoad();
    });

    renderRequests(currentRequests);
    renderSummary(SUMMARY_COUNTS);
    fetchSummary();
    fetchTechnicianLoad();
});
</script>

<?php include 'includes/footer.php'; ?>
