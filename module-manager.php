<?php
require_once 'includes/bootstrap.php';

// Only super_admin can access Module Manager
$auth->requireRole('super_admin');

$systemManager = SystemManager::getInstance();
$moduleCatalog = require __DIR__ . '/includes/module-catalog.php';

$refreshModuleStates = static function () use (&$moduleCatalog, $systemManager): void {
    foreach ($moduleCatalog as $key => $module) {
        $moduleCatalog[$key]['enabled'] = $systemManager->isModuleEnabled($key);
    }
};

$refreshModuleStates();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }

    $enabledModulesInput = array_keys($_POST['modules'] ?? []);
    $changes = 0;

    foreach ($moduleCatalog as $moduleKey => $moduleMeta) {
        $currentState = !empty($moduleMeta['enabled']);
        $targetState = in_array($moduleKey, $enabledModulesInput, true);

        if (!empty($moduleMeta['locked'])) {
            $targetState = true;
        }

        if ($targetState !== $currentState) {
            if ($systemManager->setModuleEnabled($moduleKey, $targetState)) {
                $moduleCatalog[$moduleKey]['enabled'] = $targetState;
                $changes++;
            }
        }
    }

    if ($changes > 0) {
        $systemManager->forceRefresh();
        $_SESSION['success_message'] = 'Module visibility updated for this deployment.';
    } else {
        $_SESSION['info_message'] = 'No changes were made.';
    }

    redirect($_SERVER['PHP_SELF']);
}

$totalModules = count($moduleCatalog);
$enabledModules = array_filter($moduleCatalog, static fn ($module) => !empty($module['enabled']));
$enabledCount = count($enabledModules);
$lockedCount = count(array_filter($moduleCatalog, static fn ($module) => !empty($module['locked'])));

$moduleGroups = [];
foreach ($moduleCatalog as $moduleKey => $moduleMeta) {
    $category = $moduleMeta['category'] ?? 'Other Modules';
    if (!isset($moduleGroups[$category])) {
        $moduleGroups[$category] = [];
    }
    $moduleGroups[$category][$moduleKey] = $moduleMeta;
}

$pageTitle = 'Module Manager';
$csrfToken = generateCSRFToken();
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-toggle2-on me-2"></i>Module Manager</h4>
            <p class="text-muted mb-0">Enable or disable modules per client deployment. Locked modules remain on to keep mission-critical flows stable.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="settings.php#client_modules" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i>Settings Sections
            </a>
            <button form="moduleManagerForm" type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle me-1"></i>Save Changes
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-info-circle me-2"></i><?= $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Enabled Modules</p>
                <h4 class="fw-bold mb-0"><?= $enabledCount ?> / <?= $totalModules ?></h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Locked Modules</p>
                <h4 class="fw-bold text-warning mb-0"><?= $lockedCount ?></h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card" data-elevation="md">
                <p class="text-muted small mb-1">Client Ready</p>
                <h4 class="fw-bold text-success mb-0"><?= $enabledCount === $totalModules ? 'Full Suite' : 'Custom' ?></h4>
            </div>
        </div>
    </div>

    <form method="POST" id="moduleManagerForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <?php foreach ($moduleGroups as $groupName => $modules): ?>
            <section class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title mb-1"><?= htmlspecialchars($groupName) ?></h5>
                            <p class="text-muted small mb-0">Manage <?= count($modules) ?> module(s) in this family.</p>
                        </div>
                        <span class="badge bg-light text-dark">
                            <?= array_sum(array_map(static fn ($module) => !empty($module['enabled']) ? 1 : 0, $modules)) ?> enabled
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($modules as $moduleKey => $module): ?>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body d-flex align-items-start justify-content-between gap-3">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <i class="bi <?= htmlspecialchars($module['icon'] ?? 'bi-grid') ?> text-primary"></i>
                                                <span class="fw-semibold"><?= htmlspecialchars($module['label'] ?? ucfirst($moduleKey)) ?></span>
                                                <?php if (!empty($module['locked'])): ?>
                                                    <span class="badge bg-secondary">Required</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-muted small mb-0"><?= htmlspecialchars($module['description'] ?? 'No description provided.') ?></p>
                                        </div>
                                        <div class="form-check form-switch">
                                            <?php
                                                $isEnabled = !empty($module['enabled']);
                                                $isLocked = !empty($module['locked']);
                                            ?>
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                name="modules[<?= htmlspecialchars($moduleKey) ?>]"
                                                value="1"
                                                id="module-<?= htmlspecialchars($moduleKey) ?>"
                                                <?= $isEnabled ? 'checked' : '' ?>
                                                <?= $isLocked ? 'disabled' : '' ?>
                                            >
                                            <label class="form-check-label" for="module-<?= htmlspecialchars($moduleKey) ?>">
                                                <?= $isEnabled ? 'Enabled' : 'Disabled' ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check2-circle me-2"></i>Save Module Visibility
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
