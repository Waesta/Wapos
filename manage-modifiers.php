<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();
$pdo = $db->getConnection();

if (!function_exists('redirect')) {
    function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        $_SESSION['error_message'] = 'Invalid request token. Please try again.';
        redirect('manage-modifiers.php');
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_modifier':
                $modifierId = (int)($_POST['modifier_id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $category = trim((string)($_POST['category'] ?? ''));
                $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
                $isActive = !empty($_POST['is_active']) ? 1 : 0;

                if ($name === '') {
                    throw new RuntimeException('Modifier name is required.');
                }

                $payload = [
                    'name' => $name,
                    'category' => $category === '' ? 'General' : $category,
                    'price' => $price,
                    'is_active' => $isActive,
                ];

                if ($modifierId > 0) {
                    $db->update(
                        'modifiers',
                        $payload,
                        'id = :id',
                        ['id' => $modifierId]
                    );
                    $_SESSION['success_message'] = 'Modifier updated successfully.';
                } else {
                    $db->insert('modifiers', $payload);
                    $_SESSION['success_message'] = 'Modifier added successfully.';
                }
                break;

            case 'toggle_modifier':
                $modifierId = (int)($_POST['modifier_id'] ?? 0);
                $currentStatus = isset($_POST['current_status']) ? (int)$_POST['current_status'] : 1;
                if ($modifierId <= 0) {
                    throw new RuntimeException('Invalid modifier.');
                }
                $db->update(
                    'modifiers',
                    ['is_active' => $currentStatus ? 0 : 1],
                    'id = :id',
                    ['id' => $modifierId]
                );
                $_SESSION['success_message'] = 'Modifier status updated.';
                break;

            case 'delete_modifier':
                $modifierId = (int)($_POST['modifier_id'] ?? 0);
                if ($modifierId <= 0) {
                    throw new RuntimeException('Invalid modifier.');
                }
                $db->query('DELETE FROM modifiers WHERE id = ?', [$modifierId]);
                $_SESSION['success_message'] = 'Modifier deleted.';
                break;
        }
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    redirect('manage-modifiers.php');
}

$modifiers = $db->fetchAll('SELECT * FROM modifiers ORDER BY category, name');
$grouped = [];
foreach ($modifiers as $modifier) {
    $groupKey = $modifier['category'] ?? 'Uncategorized';
    if (!isset($grouped[$groupKey])) {
        $grouped[$groupKey] = [];
    }
    $grouped[$groupKey][] = $modifier;
}

$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();
$csrfToken = generateCSRFToken();
$pageTitle = 'Manage Modifiers';
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-sliders2 text-primary me-2"></i>Modifier Library</h1>
            <p class="text-muted mb-0">Add-ons, preferences, and upsell options used across restaurant orders.</p>
        </div>
        <a href="restaurant.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Restaurant</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0" id="formTitle">Add Modifier</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="modifierForm" class="stack-md">
                        <input type="hidden" name="action" value="save_modifier">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="modifier_id" id="modifierId" value="0">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="modifierName" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" id="modifierCategory" placeholder="e.g., Add-ons, Preferences">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Price Adjustment</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= htmlspecialchars($currencySymbol) ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" name="price" id="modifierPrice" value="0">
                            </div>
                            <div class="form-text">Set to 0 for free preferences.</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="modifierActive" name="is_active" checked>
                            <label class="form-check-label" for="modifierActive">Active</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-shrink-0">
                                <i class="bi bi-save me-1"></i><span id="submitLabel">Save Modifier</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetModifierForm()">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Current Modifiers</h5>
                    <span class="badge bg-primary-subtle text-primary">Total <?= count($modifiers) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($modifiers)): ?>
                        <div class="p-4 text-center text-muted">No modifiers yet. Use the form to add your first option.</div>
                    <?php else: ?>
                        <?php foreach ($grouped as $category => $items): ?>
                            <div class="p-3 border-bottom">
                                <div class="d-flex align-items-center mb-2">
                                    <h6 class="mb-0 flex-grow-1"><?= htmlspecialchars($category) ?></h6>
                                    <span class="badge bg-light text-dark"><?= count($items) ?> item(s)</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th class="text-end">Price</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <?= formatMoney($item['price'], false) ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($item['is_active'])): ?>
                                                        <span class="badge bg-success-subtle text-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Hidden</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" type="button"
                                                            onclick='editModifier(<?= json_encode([
                                                                'id' => (int)$item['id'],
                                                                'name' => $item['name'],
                                                                'category' => $item['category'],
                                                                'price' => $item['price'],
                                                                'is_active' => (int)$item['is_active'],
                                                            ]) ?>)'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="toggle_modifier">
                                                            <input type="hidden" name="modifier_id" value="<?= (int)$item['id'] ?>">
                                                            <input type="hidden" name="current_status" value="<?= (int)$item['is_active'] ?>">
                                                            <button type="submit" class="btn btn-outline-secondary">
                                                                <i class="bi <?= !empty($item['is_active']) ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this modifier?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="delete_modifier">
                                                            <input type="hidden" name="modifier_id" value="<?= (int)$item['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editModifier(data) {
    document.getElementById('modifierId').value = data.id;
    document.getElementById('modifierName').value = data.name || '';
    document.getElementById('modifierCategory').value = data.category || '';
    document.getElementById('modifierPrice').value = parseFloat(data.price || 0).toFixed(2);
    document.getElementById('modifierActive').checked = parseInt(data.is_active || 0, 10) === 1;

    document.getElementById('formTitle').textContent = 'Edit Modifier';
    document.getElementById('submitLabel').textContent = 'Update Modifier';
}

function resetModifierForm() {
    document.getElementById('modifierForm').reset();
    document.getElementById('modifierId').value = 0;
    document.getElementById('modifierPrice').value = '0.00';
    document.getElementById('modifierActive').checked = true;
    document.getElementById('formTitle').textContent = 'Add Modifier';
    document.getElementById('submitLabel').textContent = 'Save Modifier';
}
</script>

<?php include 'includes/footer.php'; ?>
