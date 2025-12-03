<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'code' => strtoupper(sanitizeInput($_POST['code'])),
            'address' => sanitizeInput($_POST['address']),
            'phone' => sanitizeInput($_POST['phone']),
            'email' => sanitizeInput($_POST['email']),
            'manager_id' => $_POST['manager_id'] ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($action === 'add') {
            if ($db->insert('locations', $data)) {
                $_SESSION['success_message'] = 'Location added successfully';
            }
        } else {
            $id = $_POST['id'];
            if ($db->update('locations', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'Location updated successfully';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

$locations = $db->fetchAll("
    SELECT l.*, u.full_name as manager_name,
           (SELECT COUNT(*) FROM sales WHERE location_id = l.id) as total_sales,
           (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE location_id = l.id) as total_revenue
    FROM locations l
    LEFT JOIN users u ON l.manager_id = u.id
    ORDER BY l.name
");

$managers = $db->fetchAll("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') AND is_active = 1");

$pageTitle = 'Locations';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Multi-Location Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#locationModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-2"></i>Add Location
    </button>
</div>

<!-- Location Cards -->
<div class="row g-3 mb-3">
    <?php foreach ($locations as $location): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($location['name'] ?? '') ?></h5>
                        <?php if (!empty($location['code'])): ?>
                        <span class="badge bg-primary"><?= htmlspecialchars($location['code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-<?= $location['is_active'] ? 'success' : 'secondary' ?>">
                        <?= $location['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                
                <p class="text-muted small mb-2">
                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($location['address'] ?? 'No address') ?>
                </p>
                <p class="text-muted small mb-2">
                    <i class="bi bi-phone me-1"></i><?= htmlspecialchars($location['phone'] ?? 'No phone') ?>
                </p>
                <p class="text-muted small mb-3">
                    <i class="bi bi-person me-1"></i>Manager: <?= htmlspecialchars($location['manager_name'] ?? 'Not assigned') ?>
                </p>
                
                <div class="border-top pt-3">
                    <div class="row text-center">
                        <div class="col-6">
                            <h6 class="mb-0"><?= $location['total_sales'] ?></h6>
                            <small class="text-muted">Sales</small>
                        </div>
                        <div class="col-6">
                            <h6 class="mb-0"><?= formatMoney($location['total_revenue']) ?></h6>
                            <small class="text-muted">Revenue</small>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-outline-primary btn-sm" onclick='editLocation(<?= json_encode($location) ?>)'>
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="locationForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="locationId">
                    
                    <div class="mb-3">
                        <label class="form-label">Location Name *</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location Code *</label>
                        <input type="text" class="form-control" name="code" id="code" required 
                               placeholder="e.g., NYC, LA, LON" maxlength="10" style="text-transform: uppercase;">
                        <small class="text-muted">Short code for this location</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" id="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <select class="form-select" name="manager_id" id="manager_id">
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('locationForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add Location';
}

function editLocation(location) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Location';
    document.getElementById('locationId').value = location.id;
    document.getElementById('name').value = location.name || '';
    document.getElementById('code').value = location.code || '';
    document.getElementById('address').value = location.address || '';
    document.getElementById('phone').value = location.phone || '';
    document.getElementById('email').value = location.email || '';
    document.getElementById('manager_id').value = location.manager_id || '';
    document.getElementById('is_active').checked = location.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('locationModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
