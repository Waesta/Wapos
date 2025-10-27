<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'table_number' => sanitizeInput($_POST['table_number']),
            'table_name' => sanitizeInput($_POST['table_name']),
            'capacity' => $_POST['capacity'],
            'floor' => sanitizeInput($_POST['floor']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($action === 'add') {
            if ($db->insert('restaurant_tables', $data)) {
                $_SESSION['success_message'] = 'Table added successfully';
            }
        } else {
            $id = $_POST['id'];
            if ($db->update('restaurant_tables', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'Table updated successfully';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        if ($db->update('restaurant_tables', ['is_active' => 0], 'id = :id', ['id' => $id])) {
            $_SESSION['success_message'] = 'Table deactivated successfully';
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

$tables = $db->fetchAll("SELECT * FROM restaurant_tables ORDER BY table_number");

$pageTitle = 'Manage Tables';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-table me-2"></i>Manage Restaurant Tables</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tableModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-2"></i>Add Table
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Table Number</th>
                        <th>Table Name</th>
                        <th>Capacity</th>
                        <th>Floor</th>
                        <th>Status</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($table['table_number']) ?></strong></td>
                        <td><?= htmlspecialchars($table['table_name']) ?></td>
                        <td><i class="bi bi-people me-1"></i><?= $table['capacity'] ?> seats</td>
                        <td><?= htmlspecialchars($table['floor']) ?></td>
                        <td>
                            <span class="badge bg-<?= $table['status'] === 'available' ? 'success' : ($table['status'] === 'occupied' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($table['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $table['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $table['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editTable(<?= json_encode($table) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTable(<?= $table['id'] ?>, '<?= htmlspecialchars($table['table_number']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Table Modal -->
<div class="modal fade" id="tableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="tableForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="tableId">
                    
                    <div class="mb-3">
                        <label class="form-label">Table Number *</label>
                        <input type="text" class="form-control" name="table_number" id="table_number" required placeholder="e.g., T1, T2">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Table Name</label>
                        <input type="text" class="form-control" name="table_name" id="table_name" placeholder="e.g., Window Table, Corner Table">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Capacity *</label>
                        <input type="number" class="form-control" name="capacity" id="capacity" required min="1" value="4">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Floor/Location *</label>
                        <input type="text" class="form-control" name="floor" id="floor" required placeholder="e.g., Ground Floor, First Floor">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Table</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('tableForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add Table';
}

function editTable(table) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Table';
    document.getElementById('tableId').value = table.id;
    document.getElementById('table_number').value = table.table_number;
    document.getElementById('table_name').value = table.table_name || '';
    document.getElementById('capacity').value = table.capacity;
    document.getElementById('floor').value = table.floor || '';
    document.getElementById('is_active').checked = table.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('tableModal')).show();
}

function deleteTable(id, tableNumber) {
    if (confirm(`Deactivate table "${tableNumber}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
