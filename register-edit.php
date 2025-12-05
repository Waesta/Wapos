<?php
/**
 * Edit Register/Till
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check permissions
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

$registerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$registerId) {
    header('Location: registers.php');
    exit;
}

// Get register details
$register = $db->fetchOne("
    SELECT r.*, l.name as location_name 
    FROM registers r 
    JOIN locations l ON r.location_id = l.id 
    WHERE r.id = ?
", [$registerId]);

if (!$register) {
    $_SESSION['error'] = 'Register not found';
    header('Location: registers.php');
    exit;
}

// Get all locations
$locations = $db->fetchAll("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    if ($action === 'delete') {
        // Check if register has any sessions
        $sessionCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM register_sessions WHERE register_id = ?", [$registerId]);
        
        if ($sessionCount['cnt'] > 0) {
            $_SESSION['error'] = 'Cannot delete register with existing sessions. Deactivate it instead.';
        } else {
            $db->query("DELETE FROM registers WHERE id = ?", [$registerId]);
            $_SESSION['success'] = 'Register deleted successfully';
            header('Location: registers.php');
            exit;
        }
    } else {
        // Update register
        $data = [
            'location_id' => (int)$_POST['location_id'],
            'register_number' => trim($_POST['register_number']),
            'name' => trim($_POST['name']),
            'register_type' => $_POST['register_type'],
            'description' => trim($_POST['description'] ?? ''),
            'opening_balance' => (float)$_POST['opening_balance'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Check for duplicate register number in same location
        $existing = $db->fetchOne(
            "SELECT id FROM registers WHERE location_id = ? AND register_number = ? AND id != ?",
            [$data['location_id'], $data['register_number'], $registerId]
        );
        
        if ($existing) {
            $_SESSION['error'] = 'A register with this number already exists at this location';
        } else {
            $db->query("
                UPDATE registers SET 
                    location_id = ?,
                    register_number = ?,
                    name = ?,
                    register_type = ?,
                    description = ?,
                    opening_balance = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $data['location_id'],
                $data['register_number'],
                $data['name'],
                $data['register_type'],
                $data['description'],
                $data['opening_balance'],
                $data['is_active'],
                $registerId
            ]);
            
            $_SESSION['success'] = 'Register updated successfully';
            header('Location: registers.php');
            exit;
        }
    }
}

$pageTitle = 'Edit Register';
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="registers.php">Registers</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($register['name']) ?></li>
                </ol>
            </nav>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil me-2"></i>Edit Register: <?= htmlspecialchars($register['name']) ?>
                    </h5>
                    <span class="badge bg-<?= $register['is_active'] ? 'success' : 'danger' ?>">
                        <?= $register['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location <span class="text-danger">*</span></label>
                                <select class="form-select" name="location_id" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= $loc['id'] == $register['location_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Register Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="register_number" 
                                       value="<?= htmlspecialchars($register['register_number']) ?>" required>
                                <small class="text-muted">Unique identifier (e.g., REG-01, BAR-01)</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?= htmlspecialchars($register['name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Register Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="register_type" required>
                                    <option value="pos" <?= $register['register_type'] == 'pos' ? 'selected' : '' ?>>General POS</option>
                                    <option value="retail" <?= $register['register_type'] == 'retail' ? 'selected' : '' ?>>Retail Checkout</option>
                                    <option value="restaurant" <?= $register['register_type'] == 'restaurant' ? 'selected' : '' ?>>Restaurant</option>
                                    <option value="bar" <?= $register['register_type'] == 'bar' ? 'selected' : '' ?>>Bar Counter</option>
                                    <option value="service" <?= $register['register_type'] == 'service' ? 'selected' : '' ?>>Service Desk</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($register['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Opening Float <?= $currencySymbol ? "($currencySymbol)" : '' ?></label>
                                <input type="number" class="form-control" name="opening_balance" 
                                       value="<?= $register['opening_balance'] ?>" min="0" step="0.01">
                                <small class="text-muted">Default cash amount when opening a session</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                           <?= $register['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isActive">Active</label>
                                </div>
                                <small class="text-muted">Inactive registers cannot be opened</small>
                            </div>
                        </div>

                        <hr>

                        <!-- Register Stats -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted d-block">Current Balance</small>
                                    <strong class="fs-5"><?= $currencySymbol ?> <?= number_format($register['current_balance'], 2) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted d-block">Last Opened</small>
                                    <strong><?= $register['last_opened_at'] ? date('M j, Y g:i A', strtotime($register['last_opened_at'])) : 'Never' ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted d-block">Last Closed</small>
                                    <strong><?= $register['last_closed_at'] ? date('M j, Y g:i A', strtotime($register['last_closed_at'])) : 'Never' ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash me-1"></i>Delete Register
                            </button>
                            <div>
                                <a href="registers.php" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Register</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?= htmlspecialchars($register['name']) ?></strong>?</p>
                <p class="text-danger mb-0">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    This action cannot be undone. Registers with existing sessions cannot be deleted.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
