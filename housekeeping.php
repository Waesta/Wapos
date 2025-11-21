<?php
require_once 'includes/bootstrap.php';

use App\Services\HousekeepingService;

$auth->requireLogin();

$allowedRoles = ['admin', 'manager', 'housekeeping_manager', 'housekeeping_staff', 'housekeeper', 'frontdesk'];
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
$service = new HousekeepingService($pdo);

try {
    $service->ensureSchema();
} catch (Throwable $e) {
    $_SESSION['error_message'] = 'Unable to initialize housekeeping module: ' . $e->getMessage();
}

$summary = [];
$initialTasks = [];
try {
    $summary = $service->getDashboardSummary();
    $initialTasks = $service->getTasks(['limit' => 50]);
} catch (Throwable $e) {
    $summary = [];
    $initialTasks = [];
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
        "SELECT id, username, full_name, role FROM users WHERE is_active = 1 AND role IN ('admin','manager','cashier','waiter','inventory_manager','housekeeping_manager','housekeeping_staff','housekeeper','frontdesk') ORDER BY full_name, username"
    );
    $staffStmt->execute();
    $staff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $staff = [];
}

$statuses = [
    ['value' => 'pending', 'label' => 'Pending'],
    ['value' => 'in_progress', 'label' => 'In Progress'],
    ['value' => 'completed', 'label' => 'Completed'],
    ['value' => 'cancelled', 'label' => 'Cancelled'],
];

$priorities = [
    ['value' => 'high', 'label' => 'High'],
    ['value' => 'normal', 'label' => 'Normal'],
    ['value' => 'low', 'label' => 'Low'],
];

$pageTitle = 'Housekeeping';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-broom me-2"></i>Housekeeping Board</h4>
    <button class="btn btn-primary" id="newTaskBtn">
        <i class="bi bi-plus-circle me-2"></i>New Task
    </button>
</div>

<div class="row g-3 mb-3" id="summaryCards">
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Pending</p>
                <h4 class="fw-bold" id="summaryPending">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">In Progress</p>
                <h4 class="fw-bold text-primary" id="summaryInProgress">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Completed</p>
                <h4 class="fw-bold text-success" id="summaryCompleted">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Cancelled</p>
                <h4 class="fw-bold text-secondary" id="summaryCancelled">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Due Today</p>
                <h4 class="fw-bold text-warning" id="summaryToday">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">Overdue</p>
                <h4 class="fw-bold text-danger" id="summaryOverdue">0</h4>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-3" id="filterForm">
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
                <label class="form-label">Scheduled Date</label>
                <input type="date" class="form-control" name="scheduled_date" id="filterDate">
            </div>
            <div class="col-md-3">
                <label class="form-label">Assigned To</label>
                <select class="form-select" name="assigned_to" id="filterAssigned">
                    <option value="">Any staff</option>
                    <?php foreach ($staff as $member): ?>
                        <?php
                            $name = trim(($member['full_name'] ?? '') . ' ' . ($member['username'] ?? ''));
                            $name = $name !== '' ? $name : $member['username'];
                        ?>
                        <option value="<?= (int)$member['id'] ?>">
                            <?= htmlspecialchars($name . ' (' . strtoupper($member['role']) . ')') ?>
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
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tasksTable">
                <thead class="table-light">
                    <tr>
                        <th>Task</th>
                        <th>Room / Booking</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Scheduled</th>
                        <th>Assigned</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="text-center text-muted py-4 d-none" id="emptyState">
            <i class="bi bi-emoji-smile fs-1"></i>
            <p class="mt-2 mb-0">No housekeeping tasks match your filters.</p>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="taskForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskModalTitle">New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="task_id" id="taskId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" id="taskTitle" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority" id="taskPriority">
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= htmlspecialchars($priority['value']) ?>">
                                        <?= htmlspecialchars($priority['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="taskStatus">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status['value']) ?>">
                                        <?= htmlspecialchars($status['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <select class="form-select" name="room_id" id="taskRoom">
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
                            <select class="form-select" name="booking_id" id="taskBooking">
                                <option value="">No booking linked</option>
                                <?php foreach ($bookings as $booking): ?>
                                    <option value="<?= (int)$booking['id'] ?>">
                                        <?= htmlspecialchars(($booking['booking_number'] ?? ('#' . $booking['id'])) . ' - ' . ($booking['guest_name'] ?? 'Guest')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" name="scheduled_date" id="taskScheduledDate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Scheduled Time</label>
                            <input type="time" class="form-control" name="scheduled_time" id="taskScheduledTime">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due At</label>
                            <input type="datetime-local" class="form-control" name="due_at" id="taskDueAt">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" name="assigned_to" id="taskAssignedTo">
                                <option value="">Unassigned</option>
                                <?php foreach ($staff as $member): ?>
                                    <?php
                                        $name = trim(($member['full_name'] ?? '') . ' ' . ($member['username'] ?? ''));
                                        $name = $name !== '' ? $name : $member['username'];
                                    ?>
                                    <option value="<?= (int)$member['id'] ?>">
                                        <?= htmlspecialchars($name . ' (' . strtoupper($member['role']) . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="taskNotes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
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
                <h5 class="modal-title">Task Activity Log</h5>
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

    const INITIAL_TASKS = <?= json_encode($initialTasks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const SUMMARY_COUNTS = <?= json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const STATUS_LABELS = {
        pending: 'Pending',
        in_progress: 'In Progress',
        completed: 'Completed',
        cancelled: 'Cancelled'
    };
    const PRIORITY_BADGES = {
        high: 'danger',
        normal: 'primary',
        low: 'secondary'
    };

    const taskModalElement = document.getElementById('taskModal');
    const taskModal = new bootstrap.Modal(taskModalElement);
    const logsModalElement = document.getElementById('logsModal');
    const logsModal = new bootstrap.Modal(logsModalElement);
    const toastElement = document.getElementById('feedbackToast');
    const toastInstance = new bootstrap.Toast(toastElement, { delay: 3500 });

    const tasksTableBody = document.querySelector('#tasksTable tbody');
    const emptyState = document.getElementById('emptyState');
    let currentTasks = Array.isArray(INITIAL_TASKS) ? INITIAL_TASKS : [];
    let editingTaskId = null;

    function showToast(message, title = 'Notice') {
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastBody').textContent = message;
        document.getElementById('toastTime').textContent = new Date().toLocaleTimeString();
        toastInstance.show();
    }

    function renderSummary(data) {
        const counts = Object.assign({ pending: 0, in_progress: 0, completed: 0, cancelled: 0, today: 0, overdue: 0 }, data || {});
        document.getElementById('summaryPending').textContent = counts.pending;
        document.getElementById('summaryInProgress').textContent = counts.in_progress;
        document.getElementById('summaryCompleted').textContent = counts.completed;
        document.getElementById('summaryCancelled').textContent = counts.cancelled;
        document.getElementById('summaryToday').textContent = counts.today;
        document.getElementById('summaryOverdue').textContent = counts.overdue;
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        const entities = { '"': '&quot;', '&': '&amp;', "'": '&#39;', '<': '&lt;', '>': '&gt;' };
        return text.replace(/["&'<>]/g, char => entities[char] || char);
    }

    function renderTasks(tasks) {
        tasksTableBody.innerHTML = '';
        if (!Array.isArray(tasks) || tasks.length === 0) {
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');
        tasks.forEach(task => {
            const tr = document.createElement('tr');

            const scheduledParts = [];
            if (task.scheduled_date) {
                scheduledParts.push(new Date(task.scheduled_date + 'T00:00:00').toLocaleDateString());
            }
            if (task.scheduled_time) {
                scheduledParts.push(task.scheduled_time.substring(0, 5));
            }

            const dueParts = [];
            if (task.due_at) {
                const due = new Date(task.due_at.replace(' ', 'T'));
                dueParts.push(due.toLocaleString());
            }

            const assignedName = task.assigned_name || '';
            const badgeClass = PRIORITY_BADGES[task.priority] || 'secondary';
            const statusLabel = STATUS_LABELS[task.status] || task.status;

            tr.innerHTML = `
                <td>
                    <strong>${escapeHtml(task.title || 'Untitled')}</strong>
                    ${task.notes ? `<div class="small text-muted">${escapeHtml(task.notes)}</div>` : ''}
                </td>
                <td>
                    ${task.room_number ? `<div><i class="bi bi-door-open me-1"></i>Room ${escapeHtml(task.room_number)}</div>` : ''}
                    ${task.booking_number ? `<div class="small text-muted">Booking ${escapeHtml(task.booking_number)}</div>` : ''}
                </td>
                <td><span class="badge bg-${badgeClass}">${escapeHtml(task.priority || '')}</span></td>
                <td><span class="badge bg-${task.status === 'completed' ? 'success' : task.status === 'in_progress' ? 'primary' : task.status === 'cancelled' ? 'secondary' : 'warning'}">${escapeHtml(statusLabel)}</span></td>
                <td>
                    ${scheduledParts.length ? `<div>${escapeHtml(scheduledParts.join(' · '))}</div>` : ''}
                    ${dueParts.length ? `<div class="small text-danger">Due ${escapeHtml(dueParts.join(''))}</div>` : ''}
                </td>
                <td>
                    ${assignedName ? escapeHtml(assignedName) : '<span class="text-muted">Unassigned</span>'}
                </td>
                <td>
                    ${task.updated_at ? new Date(task.updated_at.replace(' ', 'T')).toLocaleString() : ''}
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-secondary" data-action="logs" data-id="${task.id}" title="View log"><i class="bi bi-clock-history"></i></button>
                        <button class="btn btn-outline-primary" data-action="edit" data-id="${task.id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-success" data-action="status" data-id="${task.id}" data-status="in_progress" title="Mark in progress"><i class="bi bi-play"></i></button>
                        <button class="btn btn-outline-success" data-action="status" data-id="${task.id}" data-status="completed" title="Complete"><i class="bi bi-check2-circle"></i></button>
                        <button class="btn btn-outline-secondary" data-action="status" data-id="${task.id}" data-status="cancelled" title="Cancel"><i class="bi bi-x-circle"></i></button>
                    </div>
                </td>
            `;

            tasksTableBody.appendChild(tr);
        });
    }

    function fetchTasks(params = {}) {
        const query = new URLSearchParams(params);
        return fetch('api/housekeeping.php?action=list&' + query.toString(), { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    currentTasks = data.tasks || [];
                    renderTasks(currentTasks);
                } else {
                    throw new Error(data.message || 'Unable to fetch tasks.');
                }
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function fetchSummary() {
        return fetch('api/housekeeping.php?action=summary', { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    renderSummary(data.summary);
                }
            })
            .catch(() => {});
    }

    function openTaskModal(task = null) {
        editingTaskId = task ? task.id : null;
        document.getElementById('taskForm').reset();

        document.getElementById('taskId').value = task ? task.id : '';
        document.getElementById('taskTitle').value = task ? task.title || '' : '';
        document.getElementById('taskPriority').value = task ? (task.priority || 'normal') : 'normal';
        document.getElementById('taskStatus').value = task ? (task.status || 'pending') : 'pending';
        document.getElementById('taskRoom').value = task && task.room_id ? task.room_id : '';
        document.getElementById('taskBooking').value = task && task.booking_id ? task.booking_id : '';
        document.getElementById('taskScheduledDate').value = task && task.scheduled_date ? task.scheduled_date : '';
        document.getElementById('taskScheduledTime').value = task && task.scheduled_time ? task.scheduled_time.substring(0, 5) : '';
        document.getElementById('taskDueAt').value = task && task.due_at ? task.due_at.replace(' ', 'T') : '';
        document.getElementById('taskAssignedTo').value = task && task.assigned_to ? task.assigned_to : '';
        document.getElementById('taskNotes').value = task && task.notes ? task.notes : '';

        document.getElementById('taskModalTitle').textContent = task ? 'Edit Task' : 'New Task';
        taskModal.show();
    }

    function submitTaskForm(event) {
        event.preventDefault();
        const formData = Object.fromEntries(new FormData(event.target).entries());
        formData.action = editingTaskId ? 'update' : 'create';

        fetch('api/housekeeping.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to save task.');
                }
                showToast(data.message || 'Task saved', 'Success');
                taskModal.hide();
                fetchTasks(getCurrentFilters()).then(fetchSummary);
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function getCurrentFilters() {
        return {
            status: document.getElementById('filterStatus').value,
            scheduled_date: document.getElementById('filterDate').value,
            assigned_to: document.getElementById('filterAssigned').value
        };
    }

    function handleStatusChange(taskId, status) {
        fetch('api/housekeeping.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', task_id: taskId, status })
        })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to update status.');
                }
                showToast('Status updated.', 'Success');
                fetchTasks(getCurrentFilters()).then(fetchSummary);
            })
            .catch(err => showToast(err.message, 'Error'));
    }

    function handleViewLogs(taskId) {
        fetch('api/housekeeping.php?action=logs&task_id=' + encodeURIComponent(taskId), { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to load logs.');
                }
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
        if (!button) {
            return;
        }

        const action = button.dataset.action;
        const taskId = parseInt(button.dataset.id, 10);
        if (!taskId) {
            return;
        }

        const task = currentTasks.find(t => Number(t.id) === taskId);

        switch (action) {
            case 'edit':
                openTaskModal(task || null);
                break;
            case 'status':
                handleStatusChange(taskId, button.dataset.status);
                break;
            case 'logs':
                handleViewLogs(taskId);
                break;
            default:
                break;
        }
    }

    document.getElementById('newTaskBtn').addEventListener('click', () => openTaskModal(null));
    document.getElementById('taskForm').addEventListener('submit', submitTaskForm);
    tasksTableBody.addEventListener('click', handleTableClick);
    document.getElementById('filterForm').addEventListener('submit', event => {
        event.preventDefault();
        fetchTasks(getCurrentFilters());
    });
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDate').value = '';
        document.getElementById('filterAssigned').value = '';
        fetchTasks({});
    });

    renderTasks(currentTasks);
    renderSummary(SUMMARY_COUNTS);
    fetchSummary();
});
</script>

<?php include 'includes/footer.php'; ?>
