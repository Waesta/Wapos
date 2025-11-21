<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'email' => sanitizeInput($_POST['email']),
            'phone' => sanitizeInput($_POST['phone']),
            'address' => sanitizeInput($_POST['address']),
            'delivery_notes' => sanitizeInput($_POST['notes']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($action === 'add') {
            if ($db->insert('customers', $data)) {
                $_SESSION['success_message'] = 'Customer added successfully';
            }
        } else {
            $id = $_POST['id'];
            if ($db->update('customers', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'Customer updated successfully';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

$customers = $db->fetchAll("
    SELECT c.*,
        COALESCE(SUM(CASE WHEN TRIM(LOWER(s.customer_name)) = TRIM(LOWER(c.name)) THEN 1 ELSE 0 END), 0) AS orders_count,
        COALESCE(SUM(CASE WHEN TRIM(LOWER(s.customer_name)) = TRIM(LOWER(c.name)) THEN s.total_amount ELSE 0 END), 0) AS total_spent,
        MAX(CASE WHEN TRIM(LOWER(s.customer_name)) = TRIM(LOWER(c.name)) THEN s.created_at ELSE NULL END) AS last_purchase
    FROM customers c
    LEFT JOIN sales s ON s.customer_name IS NOT NULL AND s.customer_name != ''
    GROUP BY c.id
    ORDER BY c.name
");

$totalCustomers = count($customers);
$activeCustomers = array_reduce($customers, fn($carry, $customer) => $carry + ((int)$customer['is_active'] === 1 ? 1 : 0), 0);
$inactiveCustomers = $totalCustomers - $activeCustomers;
$totalLifetimeValue = array_reduce($customers, fn($carry, $customer) => $carry + ($customer['total_spent'] ?? 0), 0);
$averageSpend = $totalCustomers > 0 ? $totalLifetimeValue / $totalCustomers : 0;
$loyalCustomers = array_filter($customers, fn($customer) => ($customer['orders_count'] ?? 0) >= 10);
$vipCustomers = array_filter($customers, fn($customer) => ($customer['total_spent'] ?? 0) >= 50000);
$recentCustomers = array_slice(array_filter($customers, fn($customer) => isset($customer['created_at'])), 0, 5);

usort($recentCustomers, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
usort($customers, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$topCustomers = $customers;
usort($topCustomers, fn($a, $b) => ($b['total_spent'] ?? 0) <=> ($a['total_spent'] ?? 0));
$topCustomers = array_slice($topCustomers, 0, 5);

$churnRiskCustomers = array_filter($customers, function ($customer) {
    if (!$customer['is_active'] || empty($customer['last_purchase'])) {
        return false;
    }
    $daysSince = (time() - strtotime($customer['last_purchase'])) / 86400;
    return $daysSince >= 60;
});
$churnRiskCustomers = array_slice($churnRiskCustomers, 0, 6);

$pageTitle = 'Customers';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-people me-2"></i>Customer Management</h4>
        <small class="text-muted">Track engagement, loyalty, and customer health</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" onclick="location.href='sales.php'">
            <i class="bi bi-receipt-cutoff me-2"></i>Sales History
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
            <i class="bi bi-plus-circle me-2"></i>Add Customer
        </button>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Total Customers</small>
                <h3 class="mb-0"><?= $totalCustomers ?></h3>
                <span class="badge bg-success-subtle text-success mt-2">Active: <?= $activeCustomers ?></span>
                <span class="badge bg-secondary-subtle text-secondary ms-2">Inactive: <?= $inactiveCustomers ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Lifetime Value</small>
                <h3 class="mb-0"><?= formatMoney($totalLifetimeValue) ?></h3>
                <span class="badge bg-info-subtle text-info mt-2">Avg Spend: <?= formatMoney($averageSpend) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">High Value Customers</small>
                <h3 class="mb-0"><?= count($vipCustomers) ?></h3>
                <span class="badge bg-warning-subtle text-warning mt-2">10+ Orders: <?= count($loyalCustomers) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Churn Watch</small>
                <h3 class="mb-0 text-danger"><?= count($churnRiskCustomers) ?></h3>
                <span class="badge bg-danger-subtle text-danger mt-2">No purchase 60+ days</span>
            </div>
        </div>
    </div>
</div>

<!-- Insights Row -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Top Customers</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topCustomers)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topCustomers as $customer): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($customer['name']) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars($customer['email'] ?: $customer['phone']) ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary-subtle text-primary"><?= formatMoney($customer['total_spent'] ?? 0) ?></span>
                            <span class="badge bg-secondary-subtle text-secondary ms-2"><?= (int)($customer['orders_count'] ?? 0) ?> orders</span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">No spend data available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Churn Risk</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($churnRiskCustomers)): ?>
                <div class="table-responsive" style="max-height:220px;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Last Purchase</th>
                                <th class="text-end">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($churnRiskCustomers as $customer): ?>
                            <?php
                                $daysSince = $customer['last_purchase'] ? floor((time() - strtotime($customer['last_purchase'])) / 86400) : '—';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($customer['name']) ?></td>
                                <td><?= $customer['last_purchase'] ? formatDate($customer['last_purchase'], 'd M Y') : '—' ?></td>
                                <td class="text-end text-danger"><?= is_numeric($daysSince) ? $daysSince : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No active customers beyond the 60 day threshold.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <h5 class="mb-0">Customer Directory</h5>
        <div class="d-flex flex-wrap gap-2">
            <div class="input-group input-group-sm" style="max-width:220px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="customerSearch" placeholder="Search name, phone, email">
            </div>
            <select class="form-select form-select-sm" id="customerStatusFilter" style="max-width:160px;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select class="form-select form-select-sm" id="customerSegmentFilter" style="max-width:200px;">
                <option value="">All Segments</option>
                <option value="vip">High Value (≥ 50k)</option>
                <option value="loyal">Loyal (≥ 10 orders)</option>
                <option value="new">New (30 days)</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="customerTable">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Last Purchase</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <?php
                        $ordersCount = (int)($customer['orders_count'] ?? 0);
                        $totalSpent = $customer['total_spent'] ?? 0;
                        $segment = [];
                        if ($totalSpent >= 50000) {
                            $segment[] = 'vip';
                        }
                        if ($ordersCount >= 10) {
                            $segment[] = 'loyal';
                        }
                        if (!empty($customer['created_at']) && (time() - strtotime($customer['created_at'])) <= 30 * 86400) {
                            $segment[] = 'new';
                        }
                        $segmentAttr = implode(' ', $segment);
                        $lastPurchase = $customer['last_purchase'] ? formatDate($customer['last_purchase'], 'd M Y') : '—';
                        $searchIndex = strtolower(trim(($customer['name'] ?? '') . ' ' . ($customer['phone'] ?? '') . ' ' . ($customer['email'] ?? '')));
                    ?>
                    <tr data-status="<?= $customer['is_active'] ? 'active' : 'inactive' ?>" data-segment="<?= htmlspecialchars($segmentAttr) ?>" data-search="<?= htmlspecialchars($searchIndex) ?>">
                        <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                        <td><?= htmlspecialchars($customer['phone']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><span class="badge bg-secondary-subtle text-secondary"><?= $ordersCount ?></span></td>
                        <td class="fw-bold"><?= formatMoney($totalSpent) ?></td>
                        <td><?= $lastPurchase ?></td>
                        <td>
                            <span class="badge bg-<?= $customer['is_active'] ? 'success' : 'secondary' ?>"><?= $customer['is_active'] ? 'Active' : 'Inactive' ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editCustomer(<?= json_encode($customer) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="customerEmptyState" class="alert alert-info text-center" style="display:none;">
            No customers match the current filters.
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="customerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="customerId">
                    
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" class="form-control" name="phone" id="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const customerSearchInput = document.getElementById('customerSearch');
const customerStatusFilter = document.getElementById('customerStatusFilter');
const customerSegmentFilter = document.getElementById('customerSegmentFilter');
const customerRows = Array.from(document.querySelectorAll('#customerTable tbody tr'));
const customerEmptyState = document.getElementById('customerEmptyState');

function resetForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add Customer';
    document.getElementById('customerId').value = '';
}

function editCustomer(customer) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Customer';
    document.getElementById('customerId').value = customer.id;
    document.getElementById('name').value = customer.name;
    document.getElementById('email').value = customer.email;
    document.getElementById('phone').value = customer.phone;
    document.getElementById('address').value = customer.address;
    document.getElementById('notes').value = customer.delivery_notes;
    document.getElementById('is_active').checked = customer.is_active == 1;

    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function filterCustomers() {
    const searchTerm = (customerSearchInput?.value || '').trim().toLowerCase();
    const status = customerStatusFilter?.value || '';
    const segment = customerSegmentFilter?.value || '';

    let visibleCount = 0;

    customerRows.forEach(row => {
        const matchesSearch = !searchTerm || (row.dataset.search || '').includes(searchTerm);
        const matchesStatus = !status || (row.dataset.status || '') === status;
        const matchesSegment = !segment || (row.dataset.segment || '').includes(segment);

        if (matchesSearch && matchesStatus && matchesSegment) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (customerEmptyState) {
        customerEmptyState.style.display = visibleCount === 0 ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    customerSearchInput?.addEventListener('input', debounce(filterCustomers, 150));
    customerStatusFilter?.addEventListener('change', filterCustomers);
    customerSegmentFilter?.addEventListener('change', filterCustomers);
    filterCustomers();
});

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}
</script>

<?php include 'includes/footer.php'; ?>
