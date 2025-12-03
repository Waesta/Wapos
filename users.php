<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all POST actions
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'username' => sanitizeInput($_POST['username']),
            'full_name' => sanitizeInput($_POST['full_name']),
            'email' => sanitizeInput($_POST['email']),
            'phone' => sanitizeInput($_POST['phone']),
            'role' => $_POST['role'],
            'location_id' => $_POST['location_id'] ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Add created_at for new users
        if ($action === 'add') {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        // Add password if provided
        if (!empty($_POST['password'])) {
            $data['password'] = Auth::hashPassword($_POST['password']);
        }
        
        if ($action === 'add') {
            if (empty($_POST['password'])) {
                $_SESSION['error_message'] = 'Password is required for new users';
            } else {
                try {
                    if ($db->insert('users', $data)) {
                        $_SESSION['success_message'] = 'User added successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to add user';
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
                    error_log("User creation error: " . $e->getMessage());
                }
            }
        } else {
            $id = $_POST['id'];
            if ($db->update('users', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'User updated successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to update user';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'reset_password') {
        if (!$auth->hasRole(['admin', 'super_admin'])) {
            $_SESSION['error_message'] = 'You do not have permission to reset passwords.';
            redirect($_SERVER['PHP_SELF']);
        }

        $id = (int)($_POST['id'] ?? 0);
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if ($id <= 0) {
            $_SESSION['error_message'] = 'Invalid user selected.';
        } elseif ($newPassword === '' || strlen($newPassword) < 8) {
            $_SESSION['error_message'] = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['error_message'] = 'Passwords do not match.';
        } else {
            try {
                $hashed = Auth::hashPassword($newPassword);
                $updated = $db->update('users', ['password' => $hashed], 'id = :id', ['id' => $id]);
                if ($updated) {
                    $_SESSION['success_message'] = 'Password reset successfully.';
                } else {
                    $_SESSION['error_message'] = 'Failed to reset password.';
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
            }
        }

        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'delete') {
        $id = $_POST['id'];
        // Soft delete by deactivating
        if ($db->update('users', ['is_active' => 0], 'id = :id', ['id' => $id])) {
            $_SESSION['success_message'] = 'User deactivated successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to deactivate user';
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

$users = $db->fetchAll("
    SELECT u.*, l.name as location_name 
    FROM users u 
    LEFT JOIN locations l ON u.location_id = l.id 
    ORDER BY u.username
");

$locations = $db->fetchAll("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name");

$pageTitle = 'User Management';
$csrfToken = generateCSRFToken();
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-person-badge me-2"></i>User Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-2"></i>Add User
    </button>
</div>

<?php
$roleLabels = [
    'admin' => 'Administrator',
    'manager' => 'General Manager',
    'accountant' => 'Accountant',
    'cashier' => 'Cashier',
    'waiter' => 'Waitstaff',
    'inventory_manager' => 'Inventory Manager',
    'frontdesk' => 'Front Desk',
    'housekeeping_manager' => 'Housekeeping Manager',
    'housekeeping_staff' => 'Housekeeping Staff',
    'maintenance_manager' => 'Maintenance Manager',
    'maintenance_staff' => 'Maintenance Staff',
    'technician' => 'Technician',
    'engineer' => 'Engineer',
    'rider' => 'Delivery Rider',
    'developer' => 'Developer'
];

$roleBadges = [
    'admin' => 'danger',
    'manager' => 'primary',
    'accountant' => 'info',
    'cashier' => 'success',
    'waiter' => 'success',
    'inventory_manager' => 'warning',
    'frontdesk' => 'secondary',
    'housekeeping_manager' => 'primary',
    'housekeeping_staff' => 'secondary',
    'maintenance_manager' => 'primary',
    'maintenance_staff' => 'secondary',
    'technician' => 'info',
    'engineer' => 'dark',
    'rider' => 'warning',
    'developer' => 'dark'
];

$activeCount = array_reduce($users, fn($carry, $user) => $carry + ($user['is_active'] ? 1 : 0), 0);
$inactiveCount = count($users) - $activeCount;
?>

<div class="stack-lg">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Total Users</p>
                <h4 class="fw-bold mb-0"><?= number_format(count($users)) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Active Users</p>
                <h4 class="fw-bold text-success mb-0"><?= number_format($activeCount) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Inactive Users</p>
                <h4 class="fw-bold text-muted mb-0"><?= number_format($inactiveCount) ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Distinct Roles</p>
                <h4 class="fw-bold mb-0"><?= number_format(count(array_unique(array_column($users, 'role')))) ?></h4>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form class="row g-3 align-items-end" id="userFilters">
                <div class="col-md-4">
                    <label class="form-label" for="filterSearch">Search</label>
                    <input type="search" class="form-control" id="filterSearch" placeholder="Search by username, name, email">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="filterRole">Role</label>
                    <select class="form-select" id="filterRole">
                        <option value="">All roles</option>
                        <?php foreach ($roleLabels as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="filterStatus">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary w-100" id="filterResetBtn">
                        <i class="bi bi-eraser me-1"></i>Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="text-muted small" id="userResultCount"><?= number_format(count($users)) ?> user(s)</div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="userTemplateBtn">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Template
                    </button>
                    <a href="api/data-port.php?action=export&amp;entity=users" target="_blank" rel="noopener" class="btn btn-outline-primary" id="userExportBtn">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                </div>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label">Import Users (CSV)</label>
                    <form id="userImportForm" class="row g-2" enctype="multipart/form-data" novalidate>
                        <div class="col-md-7 col-sm-12">
                            <input type="file" class="form-control" id="userImportFile" accept=".csv,text/csv" required>
                            <div class="form-text">Use the template headers. UTF-8 encoded.</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <select class="form-select" id="userImportMode">
                                <option value="validate">Validate Only</option>
                                <option value="import">Validate &amp; Import</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 d-flex align-items-end justify-content-md-end">
                            <button type="submit" class="btn btn-success btn-sm w-100" id="userImportSubmit">
                                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                <i class="bi bi-upload me-1"></i>Process
                            </button>
                        </div>
                    </form>
                    <div id="userImportStatus" class="alert d-none mt-2" role="alert"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead class="table-light">
                        <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Location</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php
                        $role = $user['role'] ?? 'unknown';
                        $badge = $roleBadges[$role] ?? 'secondary';
                        $label = $roleLabels[$role] ?? ucfirst($role);
                        $username = (string) ($user['username'] ?? '');
                        $fullName = (string) ($user['full_name'] ?? '');
                        $email = (string) ($user['email'] ?? '');
                        $searchIndex = strtolower(trim($username . ' ' . $fullName . ' ' . $email . ' ' . ($label ?? '')));
                        $statusSlug = $user['is_active'] ? 'active' : 'inactive';
                    ?>
                    <tr data-role="<?= htmlspecialchars($role) ?>" data-status="<?= $statusSlug ?>" data-search="<?= htmlspecialchars($searchIndex) ?>">
                        <td><strong><?= htmlspecialchars($username) ?></strong></td>
                        <td><?= htmlspecialchars($fullName) ?></td>
                        <td><?= htmlspecialchars($email) ?></td>
                        <td>
                            <span class="badge bg-<?= $badge ?>">
                                <?= htmlspecialchars($label) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($user['location_name'] ?? 'All Locations') ?></td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <small class="text-muted"><?= formatDate($user['last_login'], 'd/m/Y H:i') ?></small>
                            <?php else: ?>
                                <small class="text-muted">Never</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($user) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($auth->hasRole(['admin', 'super_admin'])): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="openResetModal(<?= (int)$user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                                <i class="bi bi-key"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($user['id'] != $auth->getUserId()): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" id="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" id="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password <span id="passwordNote">(Required)</span></label>
                        <input type="password" class="form-control" name="password" id="password">
                        <small class="text-muted">Leave blank to keep current password when editing</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" id="role" required>
                            <?php foreach ($roleLabels as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location_id" id="location_id">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['name']) ?></option>
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
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="passwordResetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="passwordResetForm">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="id" id="resetUserId">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="resetUsername" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8">
                        <small class="text-muted">Minimum 8 characters.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="8">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-key me-1"></i>Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken); ?>';
function resetForm() {
    document.getElementById('userForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('password').required = true;
    document.getElementById('passwordNote').textContent = '(Required)';
}

function editUser(user) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('email').value = user.email || '';
    document.getElementById('phone').value = user.phone || '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordNote').textContent = '(Optional)';
    document.getElementById('role').value = user.role;
    document.getElementById('location_id').value = user.location_id || '';
    document.getElementById('is_active').checked = user.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function deleteUser(id, username) {
    if (confirm(`Deactivate user "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function openResetModal(id, username) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUsername').value = username;
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    new bootstrap.Modal(document.getElementById('passwordResetModal')).show();
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('filterSearch');
    const roleSelect = document.getElementById('filterRole');
    const statusSelect = document.getElementById('filterStatus');
    const resetBtn = document.getElementById('filterResetBtn');
    const tableBody = document.querySelector('#usersTable tbody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    const resultCount = document.getElementById('userResultCount');

    function applyFilters() {
        const searchTerm = searchInput.value.trim().toLowerCase();
        const selectedRole = roleSelect.value;
        const selectedStatus = statusSelect.value;

        let visibleCount = 0;

        rows.forEach(row => {
            const matchesRole = !selectedRole || row.dataset.role === selectedRole;
            const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
            const searchable = row.dataset.search || '';
            const matchesSearch = !searchTerm || searchable.includes(searchTerm);

            const isVisible = matchesRole && matchesStatus && matchesSearch;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) {
                visibleCount += 1;
            }
        });

        if (resultCount) {
            resultCount.textContent = `${visibleCount.toLocaleString()} user(s)`;
        }
    }

    [searchInput, roleSelect, statusSelect].forEach(control => {
        if (control) {
            control.addEventListener('input', applyFilters);
            control.addEventListener('change', applyFilters);
        }
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            searchInput.value = '';
            roleSelect.value = '';
            statusSelect.value = '';
            applyFilters();
        });
    }

    const templateBtn = document.getElementById('userTemplateBtn');
    const importForm = document.getElementById('userImportForm');
    const importFile = document.getElementById('userImportFile');
    const importMode = document.getElementById('userImportMode');
    const importSubmit = document.getElementById('userImportSubmit');
    const importStatus = document.getElementById('userImportStatus');

    templateBtn?.addEventListener('click', () => {
        window.open('api/data-port.php?action=template&entity=users', '_blank');
    });

    importForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!importFile?.files?.length) {
            setImportStatus('Choose a CSV file to upload.', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('entity', 'users');
        formData.append('mode', importMode?.value || 'validate');
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('file', importFile.files[0]);

        const spinner = importSubmit?.querySelector('.spinner-border');
        spinner?.classList.remove('d-none');
        if (importSubmit) {
            importSubmit.disabled = true;
        }
        importStatus?.classList.add('d-none');

        try {
            const response = await fetch('api/data-port.php', {
                method: 'POST',
                body: formData,
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Import failed');
            }
            const summary = payload.message || `Completed. Inserted ${payload.inserted || 0}, updated ${payload.updated || 0}.`;
            setImportStatus(summary, 'success');
        } catch (error) {
            setImportStatus(error.message || 'Unable to process file', 'danger');
        } finally {
            spinner?.classList.add('d-none');
            if (importSubmit) {
                importSubmit.disabled = false;
            }
        }
    });

    function setImportStatus(message, variant = 'success') {
        if (!importStatus) {
            return;
        }
        importStatus.className = `alert alert-${variant}`;
        importStatus.textContent = message;
        importStatus.classList.remove('d-none');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
