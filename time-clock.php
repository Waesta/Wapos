<?php
/**
 * Employee Time Clock
 * 
 * Features:
 * - Clock in/out
 * - Break tracking
 * - Shift history
 * - Manager approval
 * - Overtime calculation
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'cashier', 'waiter', 'bartender', 'housekeeping_staff', 'maintenance_staff', 'frontdesk']);

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$userRole = strtolower($auth->getRole() ?? '');
$isManager = in_array($userRole, ['admin', 'manager']);

// Get current clock status
$currentShift = $db->fetchOne("
    SELECT * FROM employee_time_clock 
    WHERE user_id = ? AND status = 'active'
    ORDER BY clock_in_at DESC LIMIT 1
", [$userId]);

// Get recent shifts
$recentShifts = $db->fetchAll("
    SELECT tc.*, u.full_name, u.username
    FROM employee_time_clock tc
    JOIN users u ON tc.user_id = u.id
    WHERE tc.user_id = ? 
    ORDER BY tc.clock_in_at DESC 
    LIMIT 14
", [$userId]);

// For managers - get all active shifts
$activeShifts = [];
if ($isManager) {
    $activeShifts = $db->fetchAll("
        SELECT tc.*, u.full_name, u.username, u.role
        FROM employee_time_clock tc
        JOIN users u ON tc.user_id = u.id
        WHERE tc.status = 'active'
        ORDER BY tc.clock_in_at ASC
    ");
}

// Calculate weekly hours
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weeklyHours = $db->fetchOne("
    SELECT COALESCE(SUM(actual_hours), 0) as total_hours,
           COALESCE(SUM(overtime_hours), 0) as overtime_hours
    FROM employee_time_clock
    WHERE user_id = ? AND DATE(clock_in_at) >= ? AND status = 'completed'
", [$userId, $weekStart]);

$csrfToken = generateCSRFToken();
$pageTitle = 'Time Clock';
include 'includes/header.php';
?>

<style>
    .clock-display {
        font-size: 4rem;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        text-align: center;
        padding: 2rem;
        background: linear-gradient(135deg, #1a1a2e, #16213e);
        color: #00ff88;
        border-radius: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .clock-date {
        font-size: 1.2rem;
        color: #8b8b8b;
        margin-top: 0.5rem;
    }
    
    .status-badge {
        font-size: 1rem;
        padding: 0.5rem 1.5rem;
        border-radius: 2rem;
    }
    
    .clock-btn {
        padding: 1.5rem 3rem;
        font-size: 1.5rem;
        font-weight: 700;
        border-radius: 1rem;
        min-width: 200px;
    }
    
    .shift-card {
        border-left: 4px solid var(--bs-primary);
        background: var(--bs-body-bg);
        padding: 1rem;
        margin-bottom: 0.75rem;
        border-radius: 0 0.5rem 0.5rem 0;
    }
    
    .shift-card.on-break {
        border-left-color: var(--bs-warning);
    }
    
    .shift-card.completed {
        border-left-color: var(--bs-success);
    }
    
    .hours-display {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .active-staff-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }
    
    .active-staff-card .staff-name {
        font-weight: 600;
    }
    
    .active-staff-card .staff-role {
        font-size: 0.8rem;
        color: var(--bs-secondary);
    }
    
    .elapsed-time {
        font-family: monospace;
        font-weight: 600;
    }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Main Clock Panel -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <!-- Live Clock -->
                    <div class="clock-display">
                        <span id="liveClock">--:--:--</span>
                        <div class="clock-date" id="liveDate"></div>
                    </div>
                    
                    <!-- Current Status -->
                    <div class="text-center mb-4">
                        <?php if ($currentShift): ?>
                            <?php if ($currentShift['break_start_at'] && !$currentShift['break_end_at']): ?>
                                <span class="status-badge bg-warning text-dark">
                                    <i class="bi bi-cup-hot me-2"></i>On Break
                                </span>
                            <?php else: ?>
                                <span class="status-badge bg-success">
                                    <i class="bi bi-check-circle me-2"></i>Clocked In
                                </span>
                            <?php endif; ?>
                            <div class="mt-2 text-muted">
                                Since <?= date('g:i A', strtotime($currentShift['clock_in_at'])) ?>
                                <span class="elapsed-time ms-2" id="elapsedTime" data-start="<?= $currentShift['clock_in_at'] ?>">--:--:--</span>
                            </div>
                        <?php else: ?>
                            <span class="status-badge bg-secondary">
                                <i class="bi bi-x-circle me-2"></i>Not Clocked In
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <?php if (!$currentShift): ?>
                            <button class="clock-btn btn btn-success" onclick="clockIn()">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Clock In
                            </button>
                        <?php else: ?>
                            <?php if ($currentShift['break_start_at'] && !$currentShift['break_end_at']): ?>
                                <button class="clock-btn btn btn-warning" onclick="endBreak()">
                                    <i class="bi bi-play-circle me-2"></i>End Break
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-warning btn-lg" onclick="startBreak()">
                                    <i class="bi bi-cup-hot me-2"></i>Start Break
                                </button>
                            <?php endif; ?>
                            <button class="clock-btn btn btn-danger" onclick="clockOut()">
                                <i class="bi bi-box-arrow-right me-2"></i>Clock Out
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Note Input -->
                    <div class="mt-4">
                        <label class="form-label">Note (Optional)</label>
                        <input type="text" class="form-control" id="clockNote" placeholder="Add a note for this action...">
                    </div>
                </div>
            </div>
            
            <!-- Weekly Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-week me-2"></i>This Week</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="hours-display text-primary"><?= number_format($weeklyHours['total_hours'] ?? 0, 1) ?></div>
                            <div class="text-muted">Regular Hours</div>
                        </div>
                        <div class="col-6">
                            <div class="hours-display text-warning"><?= number_format($weeklyHours['overtime_hours'] ?? 0, 1) ?></div>
                            <div class="text-muted">Overtime</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="col-lg-6">
            <!-- Recent Shifts -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Shifts</h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($recentShifts)): ?>
                        <p class="text-muted text-center">No shift history yet</p>
                    <?php else: ?>
                        <?php foreach ($recentShifts as $shift): ?>
                            <div class="shift-card <?= $shift['status'] ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?= date('D, M j', strtotime($shift['clock_in_at'])) ?></div>
                                        <div class="text-muted small">
                                            <?= date('g:i A', strtotime($shift['clock_in_at'])) ?>
                                            <?php if ($shift['clock_out_at']): ?>
                                                - <?= date('g:i A', strtotime($shift['clock_out_at'])) ?>
                                            <?php else: ?>
                                                - <span class="text-success">Active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($shift['actual_hours']): ?>
                                            <div class="fw-bold"><?= number_format($shift['actual_hours'], 1) ?>h</div>
                                            <?php if ($shift['overtime_hours'] > 0): ?>
                                                <div class="small text-warning">+<?= number_format($shift['overtime_hours'], 1) ?>h OT</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($shift['clock_in_note'] || $shift['clock_out_note']): ?>
                                    <div class="small text-muted mt-1">
                                        <?php if ($shift['clock_in_note']): ?>
                                            <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($shift['clock_in_note']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($isManager && !empty($activeShifts)): ?>
            <!-- Active Staff (Manager View) -->
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Currently On Shift (<?= count($activeShifts) ?>)</h6>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($activeShifts as $staff): ?>
                        <div class="active-staff-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="staff-name"><?= htmlspecialchars($staff['full_name']) ?></div>
                                    <div class="staff-role"><?= ucfirst(str_replace('_', ' ', $staff['role'])) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="elapsed-time" data-start="<?= $staff['clock_in_at'] ?>">--:--</div>
                                    <div class="small text-muted">
                                        <?php if ($staff['break_start_at'] && !$staff['break_end_at']): ?>
                                            <span class="text-warning"><i class="bi bi-cup-hot"></i> On Break</span>
                                        <?php else: ?>
                                            Since <?= date('g:i A', strtotime($staff['clock_in_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';

// Live clock
function updateClock() {
    const now = new Date();
    const time = now.toLocaleTimeString('en-US', {hour12: false});
    const date = now.toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
    
    document.getElementById('liveClock').textContent = time;
    document.getElementById('liveDate').textContent = date;
}

// Elapsed time calculator
function updateElapsedTimes() {
    document.querySelectorAll('.elapsed-time').forEach(el => {
        const start = new Date(el.dataset.start);
        const now = new Date();
        const diff = Math.floor((now - start) / 1000);
        
        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;
        
        el.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });
}

async function clockIn() {
    const note = document.getElementById('clockNote').value;
    
    try {
        const response = await fetch('api/time-clock.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'clock_in',
                note: note,
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to clock in');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to clock in');
    }
}

async function clockOut() {
    if (!confirm('Are you sure you want to clock out?')) return;
    
    const note = document.getElementById('clockNote').value;
    
    try {
        const response = await fetch('api/time-clock.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'clock_out',
                note: note,
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            alert(`Clocked out! Total hours: ${result.hours.toFixed(1)}`);
            location.reload();
        } else {
            alert(result.message || 'Failed to clock out');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to clock out');
    }
}

async function startBreak() {
    try {
        const response = await fetch('api/time-clock.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'start_break',
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to start break');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function endBreak() {
    try {
        const response = await fetch('api/time-clock.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'end_break',
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to end break');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Initialize
updateClock();
setInterval(updateClock, 1000);
setInterval(updateElapsedTimes, 1000);
updateElapsedTimes();
</script>

<?php include 'includes/footer.php'; ?>
