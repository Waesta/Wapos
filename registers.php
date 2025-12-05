<?php
/**
 * Register/Till Management
 * Manage multiple POS terminals within locations
 * Linked to accounting and reports
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check permissions - admin, manager, or users with manage_registers permission
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$currentLocationId = $_SESSION['location_id'] ?? 1;
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Get all locations for dropdown
$locations = $db->fetchAll("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name");

// Get registers for current location
$locationFilter = isset($_GET['location']) ? (int)$_GET['location'] : 0;
$sql = "
    SELECT r.*, l.name as location_name,
           u1.full_name as opened_by_name,
           u2.full_name as closed_by_name,
           (SELECT COUNT(*) FROM register_sessions WHERE register_id = r.id AND status = 'open') as has_open_session,
           (SELECT SUM(total_sales) FROM register_sessions WHERE register_id = r.id AND DATE(opened_at) = CURDATE()) as today_sales,
           (SELECT COUNT(*) FROM register_sessions WHERE register_id = r.id AND DATE(opened_at) = CURDATE()) as today_sessions
    FROM registers r
    JOIN locations l ON r.location_id = l.id
    LEFT JOIN users u1 ON r.last_opened_by = u1.id
    LEFT JOIN users u2 ON r.last_closed_by = u2.id
    WHERE (r.location_id = ? OR ? = 0)
    ORDER BY l.name, r.register_number
";
$registerList = $db->fetchAll($sql, [$locationFilter ?: $currentLocationId, $locationFilter]);

// Get today's totals
$todayStats = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT rs.id) as total_sessions,
        COALESCE(SUM(rs.total_sales), 0) as total_sales,
        COALESCE(SUM(rs.cash_sales), 0) as cash_sales,
        COALESCE(SUM(rs.card_sales), 0) as card_sales,
        COALESCE(SUM(rs.mobile_sales), 0) as mobile_sales,
        COALESCE(SUM(rs.variance), 0) as total_variance,
        COUNT(CASE WHEN rs.status = 'open' THEN 1 END) as open_sessions
    FROM register_sessions rs
    WHERE DATE(rs.opened_at) = CURDATE()
");

// Helper functions
function getRegisterIcon($type) {
    return match($type) {
        'bar' => 'cup-straw',
        'restaurant' => 'egg-fried',
        'retail' => 'shop',
        'service' => 'headset',
        default => 'cash-stack'
    };
}

function getRegisterTypeBadge($type) {
    return match($type) {
        'bar' => 'warning',
        'restaurant' => 'info',
        'retail' => 'primary',
        'service' => 'secondary',
        default => 'dark'
    };
}

require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cash-stack me-2"></i>Register Management</h1>
            <p class="text-muted mb-0">Manage POS terminals, tills, and cashier stations</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select" id="locationFilter" style="width: 200px;">
                <option value="0">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $locationFilter == $loc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="reports.php?type=register_sessions" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRegisterModal">
                <i class="bi bi-plus-lg me-1"></i>Add Register
            </button>
        </div>
    </div>

    <!-- Today's Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Today's Sales</h6>
                            <h3 class="mb-0"><?= $currencySymbol ?> <?= number_format($todayStats['total_sales'] ?? 0, 2) ?></h3>
                        </div>
                        <i class="bi bi-graph-up-arrow fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Active Registers</h6>
                            <h3 class="mb-0"><?= $todayStats['open_sessions'] ?? 0 ?> / <?= count($registerList) ?></h3>
                        </div>
                        <i class="bi bi-pc-display fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Sessions Today</h6>
                            <h3 class="mb-0"><?= $todayStats['total_sessions'] ?? 0 ?></h3>
                        </div>
                        <i class="bi bi-clock-history fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?= ($todayStats['total_variance'] ?? 0) < 0 ? 'bg-danger' : 'bg-secondary' ?> text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Cash Variance</h6>
                            <h3 class="mb-0"><?= $currencySymbol ?> <?= number_format($todayStats['total_variance'] ?? 0, 2) ?></h3>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Cards -->
    <div class="row g-4">
        <?php if (empty($registerList)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    No registers found. Click "Add Register" to create your first POS terminal.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($registerList as $reg): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 <?= $reg['has_open_session'] ? 'border-success border-2' : '' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold">
                                <i class="bi bi-<?= getRegisterIcon($reg['register_type']) ?> me-1"></i>
                                <?= htmlspecialchars($reg['name']) ?>
                            </span>
                            <span class="badge bg-<?= $reg['is_active'] ? ($reg['has_open_session'] ? 'success' : 'secondary') : 'danger' ?>">
                                <?= $reg['has_open_session'] ? 'In Use' : ($reg['is_active'] ? 'Available' : 'Inactive') ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Register #</td>
                                    <td class="text-end"><code><?= htmlspecialchars($reg['register_number']) ?></code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Location</td>
                                    <td class="text-end"><?= htmlspecialchars($reg['location_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Type</td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= getRegisterTypeBadge($reg['register_type']) ?>">
                                            <?= ucfirst($reg['register_type']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Current Balance</td>
                                    <td class="text-end fw-bold"><?= $currencySymbol ?> <?= number_format($reg['current_balance'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Today's Sales</td>
                                    <td class="text-end text-success"><?= $currencySymbol ?> <?= number_format($reg['today_sales'] ?? 0, 2) ?></td>
                                </tr>
                                <?php if ($reg['last_opened_at']): ?>
                                <tr>
                                    <td class="text-muted">Last Opened</td>
                                    <td class="text-end small"><?= date('M j, g:i A', strtotime($reg['last_opened_at'])) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="btn-group w-100">
                                <?php if ($reg['has_open_session']): ?>
                                    <a href="pos.php?register=<?= $reg['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="bi bi-cart me-1"></i>Use
                                    </a>
                                    <button class="btn btn-warning btn-sm" onclick="closeSession(<?= $reg['id'] ?>)">
                                        <i class="bi bi-box-arrow-right me-1"></i>Close
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm" onclick="openSession(<?= $reg['id'] ?>, '<?= htmlspecialchars($reg['name']) ?>', <?= $reg['opening_balance'] ?>)" <?= !$reg['is_active'] ? 'disabled' : '' ?>>
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Open
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-sm" onclick="editRegister(<?= $reg['id'] ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="viewHistory(<?= $reg['id'] ?>)" title="History">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Payment Method Breakdown -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Today's Payment Methods</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border-end">
                                <i class="bi bi-cash-coin text-success fs-3"></i>
                                <h5 class="mt-2 mb-0"><?= $currencySymbol ?> <?= number_format($todayStats['cash_sales'] ?? 0, 2) ?></h5>
                                <small class="text-muted">Cash</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <i class="bi bi-credit-card text-primary fs-3"></i>
                                <h5 class="mt-2 mb-0"><?= $currencySymbol ?> <?= number_format($todayStats['card_sales'] ?? 0, 2) ?></h5>
                                <small class="text-muted">Card</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-phone text-info fs-3"></i>
                            <h5 class="mt-2 mb-0"><?= $currencySymbol ?> <?= number_format($todayStats['mobile_sales'] ?? 0, 2) ?></h5>
                            <small class="text-muted">Mobile</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Accounting Integration</h5>
                    <span class="badge bg-success">Active</span>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Register sessions automatically create accounting entries:</p>
                    <ul class="mb-0 small">
                        <li><strong>Opening Float:</strong> Debit Cash in Register, Credit Petty Cash</li>
                        <li><strong>Sales:</strong> Debit Cash/Card/Mobile, Credit Sales Revenue</li>
                        <li><strong>Cash Pickup:</strong> Debit Bank/Safe, Credit Cash in Register</li>
                        <li><strong>Variance:</strong> Debit/Credit Cash Over/Short account</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Register Modal -->
<div class="modal fade" id="addRegisterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Register</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRegisterForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <select class="form-select" name="location_id" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>" <?= $loc['id'] == $currentLocationId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Register Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="register_number" placeholder="e.g., REG-01, BAR-01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Register Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="register_type" required>
                                <option value="pos">General POS</option>
                                <option value="retail">Retail Checkout</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="bar">Bar Counter</option>
                                <option value="service">Service Desk</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., Checkout 1, Main Bar" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Optional description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Opening Float (<?= $currencySymbol ?>)</label>
                        <input type="number" class="form-control" name="opening_balance" value="0" min="0" step="0.01">
                        <small class="text-muted">Default cash to start each session with</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Create Register
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Open Session Modal -->
<div class="modal fade" id="openSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-right me-2"></i>Open Register Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="openSessionForm">
                <input type="hidden" name="register_id" id="openRegisterId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Opening: <strong id="openRegisterName"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opening Balance (<?= $currencySymbol ?>) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-lg text-center" name="opening_balance" 
                               id="openingBalance" value="0" min="0" step="0.01" required>
                        <small class="text-muted">Count the cash in the drawer and enter the amount</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Any notes about the opening"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-unlock me-1"></i>Open Session & Start Selling
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Close Session Modal -->
<div class="modal fade" id="closeSessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-box-arrow-right me-2"></i>Close Register Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="closeSessionForm">
                <input type="hidden" name="register_id" id="closeRegisterId">
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Opening Balance</h6>
                                    <h4 class="text-primary" id="sessionOpening"><?= $currencySymbol ?> 0.00</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Cash Sales</h6>
                                    <h4 class="text-success" id="sessionCashSales"><?= $currencySymbol ?> 0.00</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Expected Balance</h6>
                                    <h4 class="text-info" id="expectedBalance"><?= $currencySymbol ?> 0.00</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Counted Cash (<?= $currencySymbol ?>) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-lg text-center" name="closing_balance" 
                               id="closingBalance" value="0" min="0" step="0.01" required>
                    </div>
                    
                    <div class="alert" id="varianceAlert" style="display:none;">
                        <strong>Variance:</strong> <span id="varianceAmount"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Closing Notes</label>
                        <textarea class="form-control" name="closing_notes" rows="2" placeholder="Any notes about the closing (required if variance exists)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" onclick="printZReport()">
                        <i class="bi bi-printer me-1"></i>Print Z-Report
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-lock me-1"></i>Close Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Session History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Register Session History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="historyContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">Loading history...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const currencySymbol = '<?= $currencySymbol ?>';

document.getElementById('locationFilter').addEventListener('change', function() {
    window.location.href = 'registers.php?location=' + this.value;
});

function openSession(registerId, registerName, defaultFloat) {
    document.getElementById('openRegisterId').value = registerId;
    document.getElementById('openRegisterName').textContent = registerName;
    document.getElementById('openingBalance').value = defaultFloat || 0;
    new bootstrap.Modal(document.getElementById('openSessionModal')).show();
}

async function closeSession(registerId) {
    document.getElementById('closeRegisterId').value = registerId;
    
    // Fetch session data
    try {
        const response = await fetch(`api/register-sessions.php?action=current&register_id=${registerId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const session = result.data;
            document.getElementById('sessionOpening').textContent = currencySymbol + ' ' + parseFloat(session.opening_balance || 0).toFixed(2);
            document.getElementById('sessionCashSales').textContent = currencySymbol + ' ' + parseFloat(session.cash_sales || 0).toFixed(2);
            
            const expected = parseFloat(session.opening_balance || 0) + parseFloat(session.cash_sales || 0);
            document.getElementById('expectedBalance').textContent = currencySymbol + ' ' + expected.toFixed(2);
            document.getElementById('closingBalance').value = expected.toFixed(2);
        }
    } catch (error) {
        console.error('Error fetching session:', error);
    }
    
    new bootstrap.Modal(document.getElementById('closeSessionModal')).show();
}

function editRegister(registerId) {
    window.location.href = 'register-edit.php?id=' + registerId;
}

function viewHistory(registerId) {
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    modal.show();
    
    fetch(`api/register-sessions.php?action=history&register_id=${registerId}`)
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                let html = '<table class="table table-striped"><thead><tr>';
                html += '<th>Session</th><th>User</th><th>Opened</th><th>Closed</th>';
                html += '<th>Sales</th><th>Variance</th><th>Status</th></tr></thead><tbody>';
                
                result.data.forEach(s => {
                    html += `<tr>
                        <td>${s.session_number}</td>
                        <td>${s.user_name || '-'}</td>
                        <td>${s.opened_at ? new Date(s.opened_at).toLocaleString() : '-'}</td>
                        <td>${s.closed_at ? new Date(s.closed_at).toLocaleString() : '-'}</td>
                        <td>${currencySymbol} ${parseFloat(s.total_sales || 0).toFixed(2)}</td>
                        <td class="${parseFloat(s.variance || 0) < 0 ? 'text-danger' : ''}">${currencySymbol} ${parseFloat(s.variance || 0).toFixed(2)}</td>
                        <td><span class="badge bg-${s.status === 'open' ? 'success' : 'secondary'}">${s.status}</span></td>
                    </tr>`;
                });
                
                html += '</tbody></table>';
                document.getElementById('historyContent').innerHTML = html;
            }
        });
}

function printZReport() {
    const registerId = document.getElementById('closeRegisterId').value;
    window.open(`register-report.php?id=${registerId}&type=z-report`, '_blank');
}

// Calculate variance on close
document.getElementById('closingBalance')?.addEventListener('input', function() {
    const expectedText = document.getElementById('expectedBalance').textContent;
    const expected = parseFloat(expectedText.replace(/[^0-9.-]/g, '')) || 0;
    const counted = parseFloat(this.value) || 0;
    const variance = counted - expected;
    
    const alert = document.getElementById('varianceAlert');
    const amount = document.getElementById('varianceAmount');
    
    if (Math.abs(variance) > 0.01) {
        alert.style.display = 'block';
        alert.className = 'alert ' + (variance > 0 ? 'alert-success' : 'alert-danger');
        amount.textContent = currencySymbol + ' ' + variance.toFixed(2) + (variance > 0 ? ' (Over)' : ' (Short)');
    } else {
        alert.style.display = 'none';
    }
});

// Form submissions
document.getElementById('addRegisterForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('api/registers.php', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(formData)),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Register created successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'Failed to create register', 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
});

document.getElementById('openSessionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('api/register-sessions.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'open',
                ...Object.fromEntries(formData)
            }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        
        if (result.success) {
            window.location.href = 'pos.php?register=' + formData.get('register_id');
        } else {
            showToast(result.message || 'Failed to open session', 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
});

document.getElementById('closeSessionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('api/register-sessions.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'close',
                ...Object.fromEntries(formData)
            }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Session closed successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'Failed to close session', 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
});

function showToast(message, type = 'info') {
    // Use existing toast system or alert
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type === 'error' ? 'error' : 'success',
            title: message,
            showConfirmButton: false,
            timer: 3000
        });
    } else {
        alert(message);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
