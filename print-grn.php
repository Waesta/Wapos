<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'inventory_manager']);

$db = Database::getInstance();
$grnId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($grnId <= 0) {
    die('Invalid GRN identifier.');
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
    die('GRN not found.');
}

$items = $db->fetchAll("
    SELECT gi.*, p.name AS product_name, p.sku, p.unit
    FROM grn_items gi
    JOIN products p ON gi.product_id = p.id
    WHERE gi.grn_id = ?
    ORDER BY gi.id
", [$grnId]);

$totalAmount = array_reduce($items, function ($carry, $item) {
    return $carry + (float)$item['subtotal'];
}, 0.0);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print GRN - <?= htmlspecialchars($grn['grn_number']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            padding: 24px;
            background: #f8f9fa;
        }
        .print-container {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .brand-section {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .brand-title {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 0.08em;
        }
        .meta-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .totals-card {
            background: #0d6efd;
            color: #fff;
            border-radius: 12px;
            padding: 16px;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print mb-3 text-end">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="goods-received.php" class="btn btn-outline-secondary">Close</a>
    </div>

    <div class="print-container">
        <div class="brand-section d-flex justify-content-between align-items-center">
            <div>
                <div class="brand-title text-primary">WAPOS</div>
                <div class="text-muted">Comprehensive Inventory & POS Suite</div>
            </div>
            <div class="text-end">
                <div class="meta-label">Goods Received Note</div>
                <h4 class="mb-0">#<?= htmlspecialchars($grn['grn_number']) ?></h4>
                <small>Generated: <?= date('d M Y H:i') ?></small>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="meta-label mb-1">Supplier Details</div>
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h5 class="card-title mb-1">
                            <?= $grn['supplier_name'] ? htmlspecialchars($grn['supplier_name']) : '—' ?>
                        </h5>
                        <p class="mb-1">
                            <?php if ($grn['contact_person']): ?>
                                Contact: <?= htmlspecialchars($grn['contact_person']) ?><br>
                            <?php endif; ?>
                            <?php if ($grn['supplier_phone']): ?>
                                Phone: <?= htmlspecialchars($grn['supplier_phone']) ?><br>
                            <?php endif; ?>
                            <?php if ($grn['supplier_email']): ?>
                                Email: <?= htmlspecialchars($grn['supplier_email']) ?><br>
                            <?php endif; ?>
                        </p>
                        <?php if ($grn['po_number']): ?>
                            <span class="badge bg-primary">Linked PO: <?= htmlspecialchars($grn['po_number']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="meta-label mb-1">Receipt Details</div>
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <p class="mb-2"><strong>Received Date:</strong> <?= formatDate($grn['received_date'], 'd M Y') ?></p>
                        <p class="mb-2"><strong>Invoice Number:</strong> <?= $grn['invoice_number'] ? htmlspecialchars($grn['invoice_number']) : '—' ?></p>
                        <p class="mb-0"><strong>Received By:</strong> <?= $grn['received_by_name'] ? htmlspecialchars($grn['received_by_name']) : '—' ?></p>
                        <span class="badge bg-<?= $grn['status'] === 'completed' ? 'success' : ($grn['status'] === 'cancelled' ? 'danger' : 'secondary') ?> mt-2">
                            Status: <?= ucfirst($grn['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Product</th>
                        <th class="text-center" style="width: 120px;">Ordered Qty</th>
                        <th class="text-center" style="width: 120px;">Received Qty</th>
                        <th class="text-end" style="width: 130px;">Unit Cost</th>
                        <th class="text-end" style="width: 140px;">Subtotal</th>
                        <th style="width: 180px;">Batch / Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No items recorded.</td>
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

        <div class="row g-4">
            <div class="col-md-6">
                <?php if (!empty($grn['notes'])): ?>
                    <div class="meta-label mb-1">Additional Notes</div>
                    <div class="card border-0">
                        <div class="card-body bg-light">
                            <?= nl2br(htmlspecialchars($grn['notes'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="totals-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="meta-label text-white-50 mb-1">Total Amount</div>
                            <div class="fs-3 fw-semibold"><?= formatMoney($totalAmount) ?></div>
                        </div>
                        <div class="text-end">
                            <div class="meta-label text-white-50 mb-1">Received Date</div>
                            <div><?= formatDate($grn['received_date'], 'd M Y') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 text-muted">
            <small>Generated by WAPOS • <?= date('d M Y H:i') ?> • User: <?= htmlspecialchars($auth->getUsername() ?? 'System') ?></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
