<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $pageTitle = 'Sale Details';
    include 'includes/header.php';
    echo '<div class="alert alert-warning">Invalid sale ID.</div>';
    echo '<a href="sales.php" class="btn btn-secondary">Back to Sales</a>';
    include 'includes/footer.php';
    exit;
}

$sale = $db->fetchOne("SELECT s.*, u.full_name AS cashier_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?", [$id]);
$items = $db->fetchAll("SELECT si.*, p.name AS product_name, p.sku FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ? ORDER BY si.id", [$id]);

$pageTitle = 'Sale Details';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Sale Details</h4>
    <a href="print-receipt.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-2"></i>Print Receipt
    </a>
</div>

<?php if (!$sale): ?>
<div class="alert alert-warning">Sale not found.</div>
<a href="sales.php" class="btn btn-secondary">Back to Sales</a>
<?php else: ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Sale #</div>
                <div class="fw-bold"><?= htmlspecialchars($sale['sale_number'] ?? ("SALE-".$sale['id'])) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Date</div>
                <div class="fw-bold"><?= formatDate($sale['created_at'] ?? '', 'd/m/Y H:i') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Customer</div>
                <div class="fw-bold"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Cashier</div>
                <div class="fw-bold"><?= htmlspecialchars($sale['cashier_name'] ?? ($sale['user_id'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>SKU</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No items found for this sale</td></tr>
                    <?php else: ?>
                        <?php $i=1; $grand=0; foreach ($items as $it): $sub = (float)($it['quantity'] * $it['unit_price']); $grand += $sub; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($it['product_name'] ?? ('#'.$it['product_id'])) ?></td>
                            <td><?= htmlspecialchars($it['sku'] ?? '') ?></td>
                            <td class="text-end"><?= htmlspecialchars($it['quantity']) ?></td>
                            <td class="text-end"><?= formatMoney($it['unit_price']) ?></td>
                            <td class="text-end"><?= formatMoney($sub) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">Total</th>
                        <th class="text-end"><?= formatMoney($sale['total_amount'] ?? ($grand ?? 0)) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="sales.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Sales</a>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
