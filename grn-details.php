<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'inventory_manager']);

$db = Database::getInstance();

$grnId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($grnId <= 0) {
    $_SESSION['error_message'] = 'Invalid GRN identifier.';
    redirect('goods-received.php');
}

$grn = $db->fetchOne("
    SELECT g.*, s.name AS supplier_name, s.contact_person, s.phone AS supplier_phone, s.email AS supplier_email,
           u.full_name AS received_by_name, po.po_number
    FROM goods_received_notes g
    LEFT JOIN suppliers s ON g.supplier_id = s.id
    LEFT JOIN users u ON g.received_by = u.id
    LEFT JOIN purchase_orders po ON g.purchase_order_id = po.id
    WHERE g.id = ?
", [$grnId]);

if (!$grn) {
    $_SESSION['error_message'] = 'GRN not found.';
    redirect('goods-received.php');
}

$items = $db->fetchAll("
    SELECT gi.*, p.name AS product_name, p.sku, p.unit
    FROM grn_items gi
    JOIN products p ON gi.product_id = p.id
    WHERE gi.grn_id = ?
    ORDER BY gi.id
", [$grnId]);

$pageTitle = 'GRN Details - ' . htmlspecialchars($grn['grn_number']);
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-truck me-2"></i>Goods Received Note
        </h4>
        <p class="text-muted mb-0">GRN #: <?= htmlspecialchars($grn['grn_number']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="goods-received.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to GRNs
        </a>
        <a href="print-grn.php?id=<?= $grnId ?>" target="_blank" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Print GRN
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0">GRN Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">GRN Number</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($grn['grn_number']) ?></dd>

                            <dt class="col-sm-5">Received Date</dt>
                            <dd class="col-sm-7"><?= formatDate($grn['received_date'], 'd M Y') ?></dd>

                            <dt class="col-sm-5">Purchase Order</dt>
                            <dd class="col-sm-7"><?= $grn['po_number'] ? htmlspecialchars($grn['po_number']) : '—' ?></dd>

                            <dt class="col-sm-5">Invoice Number</dt>
                            <dd class="col-sm-7"><?= $grn['invoice_number'] ? htmlspecialchars($grn['invoice_number']) : '—' ?></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Supplier</dt>
                            <dd class="col-sm-7">
                                <?= $grn['supplier_name'] ? htmlspecialchars($grn['supplier_name']) : '—' ?><br>
                                <?php if ($grn['supplier_phone']): ?>
                                    <small class="text-muted">Phone: <?= htmlspecialchars($grn['supplier_phone']) ?></small><br>
                                <?php endif; ?>
                                <?php if ($grn['supplier_email']): ?>
                                    <small class="text-muted">Email: <?= htmlspecialchars($grn['supplier_email']) ?></small>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-5">Received By</dt>
                            <dd class="col-sm-7"><?= $grn['received_by_name'] ? htmlspecialchars($grn['received_by_name']) : '—' ?></dd>

                            <dt class="col-sm-5">Status</dt>
                            <dd class="col-sm-7">
                                <span class="badge bg-<?= $grn['status'] === 'completed' ? 'success' : ($grn['status'] === 'cancelled' ? 'danger' : 'secondary') ?>">
                                    <?= ucfirst($grn['status']) ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>
                <?php if (!empty($grn['notes'])): ?>
                    <div class="mt-3">
                        <h6 class="fw-semibold">Notes</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($grn['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Items Received</h5>
                <span class="badge bg-secondary"><?= count($items) ?> items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th class="text-center">Ordered Qty</th>
                                <th class="text-center">Received Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Subtotal</th>
                                <th>Batch / Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$items): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No items recorded for this GRN.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                            <small class="text-muted">SKU: <?= htmlspecialchars($item['sku'] ?? 'N/A') ?></small>
                                        </td>
                                        <td class="text-center"><?= number_format((float)$item['ordered_quantity'], 2) ?></td>
                                        <td class="text-center"><?= number_format((float)$item['received_quantity'], 2) ?></td>
                                        <td class="text-end"><?= formatMoney($item['unit_cost']) ?></td>
                                        <td class="text-end"><?= formatMoney($item['subtotal']) ?></td>
                                        <td>
                                            <?php if ($item['batch_number']): ?>
                                                <div><small class="text-muted">Batch: <?= htmlspecialchars($item['batch_number']) ?></small></div>
                                            <?php endif; ?>
                                            <?php if ($item['expiry_date']): ?>
                                                <div><small class="text-muted">Expiry: <?= formatDate($item['expiry_date'], 'd M Y') ?></small></div>
                                            <?php endif; ?>
                                            <?php if ($item['notes']): ?>
                                                <div><small class="text-muted">Notes: <?= htmlspecialchars($item['notes']) ?></small></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Total Amount:</strong></p>
                        <p class="fs-4 fw-semibold text-primary mb-0"><?= formatMoney($grn['total_amount']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-0">Created on <?= formatDate($grn['created_at'], 'd M Y H:i') ?></p>
                        <?php if (!empty($grn['updated_at'])): ?>
                            <p class="text-muted mb-0">Last updated <?= formatDate($grn['updated_at'], 'd M Y H:i') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0">Quick Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="print-grn.php?id=<?= $grnId ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-printer me-1"></i>Print GRN
                </a>
                <a href="goods-received.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-repeat me-1"></i>Receive More Goods
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Audit Trail</h6>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Recorded By:</strong> <?= $grn['received_by_name'] ? htmlspecialchars($grn['received_by_name']) : '—' ?></p>
                <p class="mb-1"><strong>Received Date:</strong> <?= formatDate($grn['received_date'], 'd M Y') ?></p>
                <p class="mb-0"><strong>Status:</strong> <?= ucfirst($grn['status']) ?></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
