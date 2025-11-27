<?php
use App\Services\LoyaltyService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'super_admin']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$loyaltyService = new LoyaltyService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        $_SESSION['error_message'] = 'Invalid session token. Please try again.';
        header('Location: loyalty-programs.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'save_program':
                $programData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'points_per_dollar' => (float) ($_POST['points_per_dollar'] ?? 1),
                    'redemption_rate' => (float) ($_POST['redemption_rate'] ?? 0.01),
                    'min_points_redemption' => (int) ($_POST['min_points_redemption'] ?? 100),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];

                $programId = (int) ($_POST['program_id'] ?? 0);
                if ($programId > 0) {
                    $loyaltyService->saveProgram($programId, $programData);
                    $_SESSION['success_message'] = 'Loyalty program updated successfully.';
                } else {
                    $loyaltyService->createProgram($programData);
                    $_SESSION['success_message'] = 'New loyalty program created.';
                }
                break;

            case 'set_active':
                $programId = (int) ($_POST['program_id'] ?? 0);
                $loyaltyService->setActiveProgram($programId);
                $_SESSION['success_message'] = 'Active loyalty program updated.';
                break;

            case 'delete_program':
                $programId = (int) ($_POST['program_id'] ?? 0);
                $loyaltyService->deleteProgram($programId);
                $_SESSION['success_message'] = 'Loyalty program deleted.';
                break;
        }
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header('Location: loyalty-programs.php');
    exit;
}

$programs = $loyaltyService->getPrograms();
$activeProgram = $loyaltyService->getActiveProgram();
$loyaltyStats = $activeProgram ? $loyaltyService->getProgramStats((int) $activeProgram['id']) : null;
$topMembers = $activeProgram ? $loyaltyService->getTopMembers((int) $activeProgram['id'], 5) : [];

$editProgramId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editProgram = $editProgramId ? $loyaltyService->getProgramById($editProgramId) : null;

$pageTitle = 'Loyalty Programs';
include 'includes/header.php';

$csrfToken = generateCSRFToken();
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-stars text-warning me-2"></i>Loyalty Program Management</h1>
            <p class="text-muted mb-0">Configure earn / redeem rules and review top customers.</p>
        </div>
        <a href="pos.php" class="btn btn-outline-primary"><i class="bi bi-cart"></i> POS</a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?= $editProgram ? 'Edit Program' : 'New Program' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="stack-md">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_program">
                        <input type="hidden" name="program_id" value="<?= (int) ($editProgram['id'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($editProgram['name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Visible to staff when linking loyalty"><?= htmlspecialchars($editProgram['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Points per <?= htmlspecialchars(settings('currency_code', 'USD')) ?></label>
                                <input type="number" name="points_per_dollar" class="form-control" min="0" step="0.01"
                                       value="<?= htmlspecialchars($editProgram['points_per_dollar'] ?? 1) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Redemption Rate (value per point)</label>
                                <input type="number" name="redemption_rate" class="form-control" min="0" step="0.001"
                                       value="<?= htmlspecialchars($editProgram['redemption_rate'] ?? 0.01) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Minimum Points to Redeem</label>
                                <input type="number" name="min_points_redemption" class="form-control" min="0" step="1"
                                       value="<?= htmlspecialchars($editProgram['min_points_redemption'] ?? 100) ?>">
                            </div>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1"
                                   <?= !empty($editProgram['is_active']) ? 'checked' : '' ?>
                                   <?= $editProgram ? '' : 'title="Activate immediately"' ?>>
                            <label class="form-check-label" for="isActiveSwitch">Set as active program</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i><?= $editProgram ? 'Update Program' : 'Create Program' ?>
                            </button>
                            <?php if ($editProgram): ?>
                                <a href="loyalty-programs.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Programs</h5>
                    <span class="badge bg-primary">Total: <?= count($programs) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Points</th>
                                <th>Rate</th>
                                <th>Min Redeem</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($programs)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No programs yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($programs as $program): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($program['name']) ?></strong>
                                        <div class="text-muted small">ID #<?= (int) $program['id'] ?></div>
                                    </td>
                                    <td><?= number_format($program['points_per_dollar'], 2) ?> pts</td>
                                    <td><?= formatMoney($program['redemption_rate']) ?> / pt</td>
                                    <td><?= number_format($program['min_points_redemption']) ?> pts</td>
                                    <td>
                                        <?php if (!empty($program['is_active'])): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="loyalty-programs.php?edit=<?= (int) $program['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (empty($program['is_active'])): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="set_active">
                                                    <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-lightning"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this program?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_program">
                                                    <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Loyalty Insights</h5>
                    <?php if ($activeProgram): ?>
                        <span class="badge bg-success">Active: <?= htmlspecialchars($activeProgram['name']) ?></span>
                    <?php else: ?>
                        <span class="badge bg-secondary">No active program</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($activeProgram && $loyaltyStats): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted text-uppercase">Members</small>
                                    <h4 class="mb-0"><?= number_format($loyaltyStats['members']) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted text-uppercase">Points Earned</small>
                                    <h4 class="mb-0 text-primary"><?= number_format($loyaltyStats['points_earned']) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted text-uppercase">Points Redeemed</small>
                                    <h4 class="mb-0 text-success"><?= number_format($loyaltyStats['points_redeemed']) ?></h4>
                                </div>
                            </div>
                        </div>

                        <h6 class="mt-4">Top Members</h6>
                        <?php if (empty($topMembers)): ?>
                            <p class="text-muted mb-0">No loyalty activity yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Contact</th>
                                            <th class="text-end">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topMembers as $member): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($member['name']) ?></strong>
                                                    <div class="text-muted small">Earned <?= number_format($member['points_earned']) ?> pts</div>
                                                </td>
                                                <td>
                                                    <div class="small">📱 <?= htmlspecialchars($member['phone'] ?? 'N/A') ?></div>
                                                    <div class="small">✉️ <?= htmlspecialchars($member['email'] ?? 'N/A') ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-info-subtle text-info"><?= number_format($member['points_balance']) ?> pts</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Activate a loyalty program to start tracking member insights.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
