<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

use App\Services\PromotionService;

$db = Database::getInstance();
$pdo = $db->getConnection();
$service = new PromotionService($pdo);
$service->ensureSchema();

$products = $db->fetchAll("SELECT id, name FROM products WHERE is_active = 1 ORDER BY name") ?: [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $promotionId = $action === 'update' ? (int)($_POST['promotion_id'] ?? 0) : null;
            $service->savePromotion($_POST, $promotionId, $auth->getUserId());
            $_SESSION['success_message'] = $promotionId ? 'Promotion updated successfully.' : 'Promotion created successfully.';
        } elseif ($action === 'delete') {
            $promotionId = (int)($_POST['promotion_id'] ?? 0);
            $service->deletePromotion($promotionId);
            $_SESSION['success_message'] = 'Promotion removed.';
        } elseif ($action === 'toggle') {
            $promotionId = (int)($_POST['promotion_id'] ?? 0);
            $isActive = !empty($_POST['is_active']);
            $service->togglePromotion($promotionId, $isActive);
            $_SESSION['success_message'] = 'Promotion status updated.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    redirect($_SERVER['PHP_SELF']);
}

$promotions = $service->listPromotions();

$pageTitle = 'Promotions';
include 'includes/header.php';
$csrfToken = generateCSRFToken();
?>

<style>
    .promo-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xl);
    }
    .promo-grid {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .promo-grid {
            grid-template-columns: minmax(0, 6fr) minmax(0, 4fr);
        }
    }
    .promo-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
    }
    .promo-card header {
        padding: var(--spacing-md);
        border-bottom: 1px solid var(--color-border-subtle);
    }
    .promo-card .card-body {
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .promo-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 600;
    }
    .promo-actions {
        display: flex;
        gap: var(--spacing-sm);
        flex-wrap: wrap;
    }
    .promo-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--color-text-muted);
        font-weight: 600;
    }
</style>

<div class="container-fluid py-4 promo-shell">
    <section class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="mb-1"><i class="bi bi-stars text-primary me-2"></i>Promotions</h1>
            <p class="text-muted mb-0">Create scheduled offers for specific products, quantities, and days of the week.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promotionModal" onclick="startCreatePromotion()">
            <i class="bi bi-plus-circle me-2"></i>New Promotion
        </button>
    </section>

    <?php if (!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>

    <?php if (!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>

    <section class="promo-grid">
        <article class="promo-card">
            <header class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Active Promotions</h5>
                <span class="badge bg-light text-dark"><?= count($promotions) ?> configured</span>
            </header>
            <div class="card-body" id="promotionList">
                <?php if (empty($promotions)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-magic fs-1 mb-2"></i>
                    <p class="mb-0">No scheduled promotions yet.</p>
                    <small>Create your first offer to boost selected product sales.</small>
                </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($promotions as $promotion): ?>
                        <div class="border rounded p-3 d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($promotion['name']) ?>
                                        <?php if (!empty($promotion['product_name'])): ?>
                                        <small class="text-muted">· <?= htmlspecialchars($promotion['product_name']) ?></small>
                                        <?php endif; ?>
                                    </h6>
                                    <?php if (!empty($promotion['description'])): ?>
                                    <p class="text-muted mb-0 small"><?= htmlspecialchars($promotion['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $promotion['is_active'] ? 'bg-success' : 'bg-secondary' ?> promo-badge">
                                    <?= $promotion['is_active'] ? 'Active' : 'Paused' ?>
                                </span>
                            </div>

                            <div class="d-grid gap-2 small">
                                <div>
                                    <span class="promo-label">Type</span>
                                    <div>
                                        <?php if ($promotion['promotion_type'] === 'bundle_price'): ?>
                                            Bundle price: <?= formatMoney($promotion['bundle_price']) ?> for <?= (int)$promotion['min_quantity'] ?> item(s)
                                        <?php elseif ($promotion['promotion_type'] === 'percent'): ?>
                                            <?= formatMoney($promotion['discount_value']) ?>% off (min qty <?= (int)$promotion['min_quantity'] ?>)
                                        <?php else: ?>
                                            <?= formatMoney($promotion['discount_value']) ?> off each (min qty <?= (int)$promotion['min_quantity'] ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-3">
                                    <div>
                                        <span class="promo-label">Days</span>
                                        <div>
                                            <?php if (empty($promotion['days_of_week'])): ?>
                                                All days
                                            <?php else: ?>
                                                <?php foreach ($promotion['days_of_week'] as $day): ?>
                                                    <span class="badge bg-light text-dark me-1"><?= date('D', strtotime("Sunday +{$day} days")) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="promo-label">Time window</span>
                                        <div>
                                            <?php if (!$promotion['start_time'] && !$promotion['end_time']): ?>
                                                All day
                                            <?php else: ?>
                                                <?= $promotion['start_time'] ? htmlspecialchars(substr($promotion['start_time'], 0, 5)) : '00:00' ?> -
                                                <?= $promotion['end_time'] ? htmlspecialchars(substr($promotion['end_time'], 0, 5)) : '23:59' ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="promo-label">Schedule</span>
                                        <div>
                                            <?php
                                            $start = $promotion['start_date'] ? date('M j, Y', strtotime($promotion['start_date'])) : 'Any';
                                            $end = $promotion['end_date'] ? date('M j, Y', strtotime($promotion['end_date'])) : 'Ongoing';
                                            ?>
                                            <?= $start ?> → <?= $end ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="promo-actions">
                                <button class="btn btn-outline-primary btn-sm" onclick='editPromotion(<?= json_encode($promotion, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT) ?>)'>
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <form method="POST" onsubmit="return confirm('Remove this promotion?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="promotion_id" value="<?= (int)$promotion['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="promotion_id" value="<?= (int)$promotion['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $promotion['is_active'] ? '0' : '1' ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                                        <?= $promotion['is_active'] ? '<i class="bi bi-pause-circle"></i> Pause' : '<i class="bi bi-play-circle"></i> Resume' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="promo-card">
            <header>
                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>How promotions work</h5>
            </header>
            <div class="card-body">
                <ol class="small mb-0">
                    <li>Define the product, minimum quantity, and discount or bundle price.</li>
                    <li>Choose the days of week, date range, and time window when the offer applies.</li>
                    <li>During checkout, the POS automatically applies the best eligible promotion.</li>
                    <li>Multiple promotions on the same product are evaluated by highest savings.</li>
                </ol>
            </div>
        </article>
    </section>
</div>

<!-- Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promotionModalTitle">Create Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="promotionForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="create" id="promotionAction">
                    <input type="hidden" name="promotion_id" id="promotionId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Promotion Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="promoName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select name="product_id" id="promoProduct" class="form-select" required>
                                <option value="">Select product...</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="promoDescription" class="form-control" rows="2" placeholder="Optional details shown to staff"></textarea>
                        </div>
                    </div>

                    <div class="border rounded p-3 my-3">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <label class="form-label me-2 mb-0">Promotion Type</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="promotion_type" id="typeBundle" value="bundle_price" checked>
                                <label class="form-check-label" for="typeBundle">Bundle price</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="promotion_type" id="typePercent" value="percent">
                                <label class="form-check-label" for="typePercent">Percentage off</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="promotion_type" id="typeFixed" value="fixed">
                                <label class="form-check-label" for="typeFixed">Fixed discount</label>
                            </div>
                        </div>
                        <div class="row g-3" id="bundleFields">
                            <div class="col-md-6">
                                <label class="form-label">Bundle price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="bundle_price" id="bundlePrice" class="form-control" placeholder="Bundle price">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quantity in bundle <span class="text-danger">*</span></label>
                                <input type="number" min="1" name="min_quantity" id="minQuantity" class="form-control" value="5">
                            </div>
                        </div>
                        <div class="row g-3" id="discountFields" style="display:none;">
                            <div class="col-md-6">
                                <label class="form-label">Discount value <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="discount_value" id="discountValue" class="form-control" placeholder="Discount value">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Minimum quantity</label>
                                <input type="number" min="1" name="min_quantity_alt" id="minQuantityAlt" class="form-control" value="1">
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Days of week</label>
                                <select name="days_of_week[]" id="promoDays" class="form-select" multiple>
                                    <option value="0">Sunday</option>
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                </select>
                                <small class="text-muted">Leave blank for all days</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date range</label>
                                <div class="input-group">
                                    <input type="date" name="start_date" id="startDate" class="form-control">
                                    <span class="input-group-text">to</span>
                                    <input type="date" name="end_date" id="endDate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start time</label>
                                <input type="time" name="start_time" id="startTime" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End time</label>
                                <input type="time" name="end_time" id="endTime" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" checked>
                                    <label class="form-check-label" for="isActive">Promotion is active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="promotionSubmitBtn">
                        <i class="bi bi-check-circle me-2"></i>Save Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const promotionForm = document.getElementById('promotionForm');
    const promotionModal = document.getElementById('promotionModal');
    const promotionModalTitle = document.getElementById('promotionModalTitle');
    const promotionAction = document.getElementById('promotionAction');
    const promotionId = document.getElementById('promotionId');
    const bundleFields = document.getElementById('bundleFields');
    const discountFields = document.getElementById('discountFields');
    const minQuantity = document.getElementById('minQuantity');
    const minQuantityAlt = document.getElementById('minQuantityAlt');

    function showBundleFields() {
        bundleFields.style.display = '';
        discountFields.style.display = 'none';
        minQuantityAlt.removeAttribute('name');
        minQuantity.setAttribute('name', 'min_quantity');
    }

    function showDiscountFields() {
        bundleFields.style.display = 'none';
        discountFields.style.display = '';
        minQuantity.removeAttribute('name');
        minQuantityAlt.setAttribute('name', 'min_quantity');
    }

    promotionForm.addEventListener('change', (event) => {
        if (event.target.name === 'promotion_type') {
            if (event.target.value === 'bundle_price') {
                showBundleFields();
            } else {
                showDiscountFields();
            }
        }
    });

    window.startCreatePromotion = () => {
        promotionForm.reset();
        promotionAction.value = 'create';
        promotionId.value = '';
        promotionModalTitle.textContent = 'Create Promotion';
        showBundleFields();
        bootstrap.Modal.getOrCreateInstance(promotionModal).show();
    };

    window.editPromotion = (promotion) => {
        promotionForm.reset();
        promotionAction.value = 'update';
        promotionId.value = promotion.id;
        promotionModalTitle.textContent = 'Edit Promotion';

        document.getElementById('promoName').value = promotion.name ?? '';
        document.getElementById('promoProduct').value = promotion.product_id ?? '';
        document.getElementById('promoDescription').value = promotion.description ?? '';
        document.querySelectorAll('input[name="promotion_type"]').forEach((radio) => {
            radio.checked = radio.value === promotion.promotion_type;
        });

        if (promotion.promotion_type === 'bundle_price') {
            showBundleFields();
            document.getElementById('bundlePrice').value = promotion.bundle_price ?? '';
            minQuantity.value = promotion.min_quantity ?? 1;
        } else {
            showDiscountFields();
            document.getElementById('discountValue').value = promotion.discount_value ?? '';
            minQuantityAlt.value = promotion.min_quantity ?? 1;
        }

        const daysSelect = document.getElementById('promoDays');
        Array.from(daysSelect.options).forEach((option) => {
            option.selected = (promotion.days_of_week || []).includes(Number(option.value));
        });

        document.getElementById('startDate').value = promotion.start_date ?? '';
        document.getElementById('endDate').value = promotion.end_date ?? '';
        document.getElementById('startTime').value = promotion.start_time ? promotion.start_time.substring(0,5) : '';
        document.getElementById('endTime').value = promotion.end_time ? promotion.end_time.substring(0,5) : '';
        document.getElementById('isActive').checked = promotion.is_active === 1;

        bootstrap.Modal.getOrCreateInstance(promotionModal).show();
    };
})();
</script>

<?php include 'includes/footer.php'; ?>
