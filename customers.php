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
            'notes' => sanitizeInput($_POST['notes']),
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

$customers = $db->fetchAll("SELECT * FROM customers ORDER BY name");

$pageTitle = 'Customers';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Customer Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-2"></i>Add Customer
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Total Orders</th>
                        <th>Total Spent</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                        <td><?= htmlspecialchars($customer['phone']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><?= $customer['total_orders'] ?></td>
                        <td class="fw-bold">KES <?= formatMoney($customer['total_spent']) ?></td>
                        <td>
                            <span class="badge bg-<?= $customer['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $customer['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
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
function resetForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add Customer';
}

function editCustomer(customer) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Customer';
    document.getElementById('customerId').value = customer.id;
    document.getElementById('name').value = customer.name;
    document.getElementById('phone').value = customer.phone;
    document.getElementById('email').value = customer.email || '';
    document.getElementById('address').value = customer.address || '';
    document.getElementById('notes').value = customer.notes || '';
    document.getElementById('is_active').checked = customer.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
