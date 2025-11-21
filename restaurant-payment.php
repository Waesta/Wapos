<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$orderId = $_GET['order_id'] ?? 0;

// Get order details
$order = $db->fetchOne("
    SELECT o.*, rt.table_number, rt.table_name, u.full_name as waiter_name
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.payment_status = 'pending'
", [$orderId]);

if (!$order) {
    die('Order not found or already paid');
}

// Get order items
$items = $db->fetchAll("
    SELECT oi.*, p.sku 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
", [$orderId]);

// Existing partial payments if any
$existingPayments = [];
$pdo = $db->getConnection();
try {
    $stmt = $pdo->prepare('SHOW TABLES LIKE "order_payments"');
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        $existingPayments = $db->fetchAll(
            "SELECT payment_method, amount, tip_amount, metadata, created_at
             FROM order_payments
             WHERE order_id = ? ORDER BY id ASC",
            [$orderId]
        );
        foreach ($existingPayments as &$paymentRow) {
            if (!empty($paymentRow['metadata'])) {
                $decoded = json_decode($paymentRow['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $paymentRow['metadata'] = $decoded;
                }
            }
        }
        unset($paymentRow);
    }
} catch (Throwable $e) {
    $existingPayments = [];
}

// Get settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Checked-in rooms for room charge payments (matches retail POS experience)
$checkedInRooms = [];
try {
    $pdo = $db->getConnection();
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'room_bookings'");
    if ($tablesStmt && $tablesStmt->fetchColumn()) {
        $roomsStmt = $pdo->prepare(
            "SELECT b.id, b.booking_number, b.guest_name, b.guest_phone, r.room_number
             FROM room_bookings b
             JOIN rooms r ON b.room_id = r.id
             WHERE b.status = 'checked_in'
             ORDER BY r.room_number ASC, b.booking_number ASC"
        );
        $roomsStmt->execute();
        $rows = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $guestName = trim((string)($row['guest_name'] ?? ''));
            $roomNumber = trim((string)($row['room_number'] ?? ''));
            $bookingNumber = trim((string)($row['booking_number'] ?? ''));

            $labelParts = [];
            if ($roomNumber !== '') {
                $labelParts[] = 'Room ' . $roomNumber;
            }
            if ($guestName !== '') {
                $labelParts[] = $guestName;
            }

            $label = implode(' · ', $labelParts);
            if ($bookingNumber !== '') {
                $label .= ($label !== '' ? ' ' : '') . '(' . $bookingNumber . ')';
            }

            if ($label === '') {
                $label = 'Booking #' . (int) $row['id'];
            }

            $checkedInRooms[] = [
                'id' => (int) $row['id'],
                'label' => $label,
            ];
        }
    }
} catch (Throwable $e) {
    $checkedInRooms = [];
}

$pageTitle = 'Process Payment - Order ' . $order['order_number'];
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="bi bi-credit-card me-2"></i>Payment Processing</h5>
                </div>
                <div class="card-body">
                    <!-- Order Summary -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-primary">Order Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Order #:</strong></td>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date:</strong></td>
                                    <td><?= formatDate($order['created_at'], 'd/m/Y H:i') ?></td>
                                </tr>
                                <?php if ($order['table_number']): ?>
                                <tr>
                                    <td><strong>Table:</strong></td>
                                    <td><?= htmlspecialchars($order['table_number']) ?></td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td><strong>Service:</strong></td>
                                    <td><?= ucfirst($order['order_type']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($order['customer_name']): ?>
                                <tr>
                                    <td><strong>Customer:</strong></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>Waiter:</strong></td>
                                    <td><?= htmlspecialchars($order['waiter_name']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Payment Summary</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td>Subtotal:</td>
                                    <td class="text-end"><?= formatMoney($order['subtotal']) ?></td>
                                </tr>
                                <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <td>Discount:</td>
                                    <td class="text-end text-success">-<?= formatMoney($order['discount_amount']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($order['tax_amount'] > 0): ?>
                                <tr>
                                    <td>Tax (VAT):</td>
                                    <td class="text-end"><?= formatMoney($order['tax_amount']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($order['delivery_fee'] > 0): ?>
                                <tr>
                                    <td>Delivery Fee:</td>
                                    <td class="text-end"><?= formatMoney($order['delivery_fee']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-primary">
                                    <td><strong>Total Amount:</strong></td>
                                    <td class="text-end"><strong><?= formatMoney($order['total_amount']) ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <form id="paymentForm">
                        <input type="hidden" id="orderId" value="<?= $order['id'] ?>">
                        <input type="hidden" id="totalAmount" value="<?= $order['total_amount'] ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary d-flex justify-content-between align-items-center">
                                    <span>Payments</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addPaymentBtn">
                                        <i class="bi bi-plus-circle me-1"></i>Add Payment
                                    </button>
                                </h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm align-middle" id="paymentsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 18%">Method</th>
                                                <th style="width: 16%" class="text-end">Amount</th>
                                                <th style="width: 16%" class="text-end">Tip</th>
                                                <th style="width: 30%">Details</th>
                                                <th style="width: 12%" class="text-center">Primary</th>
                                                <th style="width: 8%"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="alert alert-secondary small" id="paymentsHelp">
                                    <strong>Instructions:</strong> Add one or more payments to cover the order total. Use the tip column to record gratuity for the primary payment. Room charge must be a single payment covering the entire bill.
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-primary">Payment Details</h6>
                                
                                <div class="payment-detail-panel" id="paymentDetailPanel" hidden>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="text-primary mb-0" id="paymentDetailTitle">Payment Details</h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="closePaymentDetailBtn">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                    <div id="paymentDetailContent"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="paymentNotes" class="form-label">Payment Notes (Optional)</label>
                                    <textarea class="form-control" id="paymentNotes" rows="2" placeholder="Any additional notes about the payment..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="restaurant-order.php?id=<?= $order['id'] ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Order
                            </a>
                            <button type="button" class="btn btn-success btn-lg" onclick="processPayment()">
                                <i class="bi bi-check-circle me-2"></i>Complete Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-list-check"></i> Order Items</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($items as $item): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($item['product_name']) ?></h6>
                                    <small class="text-muted">
                                        <?= $item['quantity'] ?> x <?= formatMoney($item['unit_price']) ?>
                                        <?php if ($item['sku']): ?>
                                        <br>SKU: <?= htmlspecialchars($item['sku']) ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($item['modifiers_data']): ?>
                                    <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                                    <?php if ($modifiers && count($modifiers) > 0): ?>
                                    <div class="mt-1">
                                        <?php foreach ($modifiers as $modifier): ?>
                                        <small class="badge bg-light text-dark">+ <?= htmlspecialchars($modifier['name']) ?></small>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($item['special_instructions']): ?>
                                    <div class="mt-1">
                                        <small class="text-info"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($item['special_instructions']) ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-primary"><?= formatMoney($item['total_price']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="bi bi-info-circle"></i> Payment Information</h6>
                </div>
                <div class="card-body">
                    <small>
                        <strong>Payment Methods:</strong><br>
                        • Cash payments require exact change calculation<br>
                        • Card payments may require authorization codes<br>
                        • Mobile money transactions should include phone numbers<br>
                        • Bank transfers require reference numbers<br><br>
                        
                        <strong>After Payment:</strong><br>
                        • Customer receipt will be automatically printed<br>
                        • Order status will be updated to "Preparing"<br>
                        • Kitchen will be notified to start preparation<br>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function processPayment() {
    const orderId = document.getElementById('orderId').value;
    const totalAmount = parseFloat(document.getElementById('totalAmount').value);
    const payments = collectPayments();

    if (!payments || !payments.length) {
        alert('Please add at least one payment.');
        return;
    }

    const payload = {
        order_id: orderId,
        payments,
        notes: document.getElementById('paymentNotes').value
    };

    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'process_payment',
                ...payload
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showPaymentSuccess(result);
        } else {
            alert('Error processing payment: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function collectPayments() {
    const rows = document.querySelectorAll('#paymentsTable tbody tr');
    const payments = [];
    let primaryAssigned = false;
    let totalAmount = 0;
    let totalTip = 0;

    rows.forEach(row => {
        const method = row.dataset.method;
        const amountInput = row.querySelector('.payment-amount');
        const tipInput = row.querySelector('.payment-tip');
        const details = row.dataset.details ? JSON.parse(row.dataset.details) : {};
        const amount = parseFloat(amountInput.value) || 0;
        const tip = parseFloat(tipInput.value) || 0;
        const isPrimary = row.querySelector('.primary-payment').checked;

        if (!method || amount <= 0) {
            return;
        }

        if (method === 'room_charge' && tip > 0) {
            alert('Tips cannot be applied to room charge payments.');
            throw new Error('Tip on room charge');
        }

        if (isPrimary && primaryAssigned) {
            row.querySelector('.primary-payment').checked = false;
        }

        if (isPrimary) {
            primaryAssigned = true;
        }

        payments.push({
            payment_method: method,
            amount: amount,
            tip_amount: isPrimary ? tip : 0,
            metadata: Object.keys(details).length ? details : undefined,
            room_booking_id: details.room_booking_id,
            room_charge_description: details.room_charge_description,
        });

        totalAmount += amount;
        totalTip += isPrimary ? tip : 0;
    });

    if (!payments.length) {
        return null;
    }

    const orderTotal = parseFloat(document.getElementById('totalAmount').value);
    const totalDue = orderTotal;

    if (totalAmount < totalDue - 0.01) {
        alert('Split amounts do not cover the order total.');
        return null;
    }

    if (!primaryAssigned) {
        payments[0].tip_amount = payments[0].tip_amount || totalTip;
    }

    return payments;
}

function showPaymentSuccess(result) {
    const receiptPrompt = result.payment_status === 'paid'
        ? 'Payment processed successfully! Print customer receipt?'
        : `Payment recorded as ${result.payment_status}. Print receipt?`;

    const shouldPrint = confirm(receiptPrompt);
    if (shouldPrint) {
        window.open('print-customer-receipt.php?id=' + result.order_id, '_blank', 'width=400,height=600');
    }

    if (result.change_due > 0) {
        alert(`Change due: ${result.change_due.toFixed(2)}`);
    }

    window.location.href = 'restaurant.php';
}

const paymentTemplates = {
    cash: () => ({
        title: 'Cash Payment',
        fields: `
            <div class="mb-3">
                <label class="form-label">Amount Received</label>
                <input type="number" class="form-control form-control-sm" name="amount_received" min="0" step="0.01" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Change to Return (optional)</label>
                <input type="number" class="form-control form-control-sm" name="change_amount" min="0" step="0.01">
            </div>
        `,
    }),
    card: () => ({
        title: 'Card Payment',
        fields: `
            <div class="mb-3">
                <label class="form-label">Card Type</label>
                <select class="form-select form-select-sm" name="card_type">
                    <option value="visa">Visa</option>
                    <option value="mastercard">Mastercard</option>
                    <option value="amex">American Express</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Last 4 Digits</label>
                <input type="text" class="form-control form-control-sm" name="card_last_four" maxlength="4" pattern="\\d{4}" placeholder="1234">
            </div>
            <div class="mb-3">
                <label class="form-label">Auth Code (optional)</label>
                <input type="text" class="form-control form-control-sm" name="auth_code">
            </div>
        `,
    }),
    mobile_money: () => ({
        title: 'Mobile Money',
        fields: `
            <div class="mb-3">
                <label class="form-label">Provider</label>
                <select class="form-select form-select-sm" name="mobile_provider">
                    <option value="mpesa">M-Pesa</option>
                    <option value="airtel">Airtel Money</option>
                    <option value="tkash">T-Kash</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" class="form-control form-control-sm" name="mobile_number" placeholder="0700000000">
            </div>
            <div class="mb-3">
                <label class="form-label">Transaction ID</label>
                <input type="text" class="form-control form-control-sm" name="transaction_id">
            </div>
        `,
    }),
    bank_transfer: () => ({
        title: 'Bank Transfer',
        fields: `
            <div class="mb-3">
                <label class="form-label">Bank Name</label>
                <input type="text" class="form-control form-control-sm" name="bank_name">
            </div>
            <div class="mb-3">
                <label class="form-label">Reference Number</label>
                <input type="text" class="form-control form-control-sm" name="reference_number">
            </div>
        `,
    }),
    room_charge: () => ({
        title: 'Room Charge',
        fields: `
            <?php if (!empty($checkedInRooms)): ?>
            <div class="mb-3">
                <label class="form-label">Checked-in Room</label>
                <select class="form-select form-select-sm" name="room_booking_id" required>
                    <option value="">Select a checked-in room...</option>
                    <?php foreach ($checkedInRooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>">
                            <?= htmlspecialchars($room['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Folio Note (optional)</label>
                <input type="text" class="form-control form-control-sm" name="room_charge_description" maxlength="120" placeholder="e.g. Dinner at restaurant">
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <div class="small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>No checked-in rooms available.</div>
                <div class="small">Check in a guest before charging orders to a room.</div>
            </div>
            <?php endif; ?>
        `,
    }),
};

function openPaymentDetailModal(method, preset = {}) {
    const templateFactory = paymentTemplates[method];
    if (!templateFactory) {
        document.getElementById('paymentDetailPanel').hidden = true;
        return;
    }

    const template = templateFactory();
    const titleEl = document.getElementById('paymentDetailTitle');
    const contentEl = document.getElementById('paymentDetailContent');

    titleEl.textContent = template.title;
    contentEl.innerHTML = template.fields;
    document.getElementById('paymentDetailPanel').hidden = false;

    Object.entries(preset).forEach(([key, value]) => {
        const input = contentEl.querySelector(`[name="${key}"]`);
        if (input) {
            input.value = value;
        }
    });
}

function closePaymentDetailPanel() {
    document.getElementById('paymentDetailPanel').hidden = true;
    document.getElementById('paymentDetailContent').innerHTML = '';
}

document.getElementById('closePaymentDetailBtn').addEventListener('click', closePaymentDetailPanel);

document.getElementById('addPaymentBtn').addEventListener('click', () => {
    openPaymentEditor();
});

function openPaymentEditor(existingRow = null) {
    const dialog = document.createElement('div');
    dialog.className = 'modal fade';
    dialog.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${existingRow ? 'Edit Payment' : 'Add Payment'}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="room_charge">Charge to Room</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-control" name="amount" min="0" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tip (gratuity)</label>
                            <input type="number" class="form-control" name="tip_amount" min="0" step="0.01" value="0">
                            <div class="form-text">Tip is applied only to the primary payment. Others will record zero tip.</div>
                        </div>
                        <div id="methodSpecificFields"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="savePaymentBtn">
                        <i class="bi bi-save me-2"></i>Save Payment
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(dialog);
    const modal = new bootstrap.Modal(dialog);
    const form = dialog.querySelector('#paymentForm');
    const methodSelect = form.querySelector('select[name="payment_method"]');
    const methodFieldsContainer = form.querySelector('#methodSpecificFields');

    function renderMethodFields(method, preset = {}) {
        const template = paymentTemplates[method];
        methodFieldsContainer.innerHTML = template ? template().fields : '';

        Object.entries(preset).forEach(([key, value]) => {
            const input = methodFieldsContainer.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = value;
            }
        });
    }

    methodSelect.addEventListener('change', () => {
        renderMethodFields(methodSelect.value);
    });

    let existingData = null;
    if (existingRow) {
        existingData = JSON.parse(existingRow.dataset.details || '{}');
        existingData.amount = existingRow.querySelector('.payment-amount').value;
        existingData.tip_amount = existingRow.querySelector('.payment-tip').value;
        methodSelect.value = existingRow.dataset.method;
        renderMethodFields(methodSelect.value, existingData);
        form.querySelector('input[name="amount"]').value = existingData.amount;
        form.querySelector('input[name="tip_amount"]').value = existingData.tip_amount;
    } else {
        renderMethodFields(methodSelect.value);
    }

    dialog.querySelector('#savePaymentBtn').addEventListener('click', () => {
        if (!form.reportValidity()) {
            return;
        }

        const formData = new FormData(form);
        const payment = Object.fromEntries(formData.entries());
        payment.amount = parseFloat(payment.amount || '0');
        payment.tip_amount = parseFloat(payment.tip_amount || '0');

        const method = payment.payment_method;
        delete payment.payment_method;

        addOrUpdatePaymentRow({ method, payment }, existingRow);
        modal.hide();
    });

    dialog.addEventListener('hidden.bs.modal', () => {
        dialog.remove();
    });

    modal.show();
}

function addOrUpdatePaymentRow({ method, payment }, existingRow = null) {
    const tableBody = document.querySelector('#paymentsTable tbody');
    const row = existingRow || document.createElement('tr');

    row.dataset.method = method;
    row.dataset.details = JSON.stringify(payment);

    const detailsPreview = Object.entries(payment)
        .filter(([key]) => !['amount', 'tip_amount'].includes(key))
        .map(([key, value]) => `<span class="badge bg-light text-dark me-1">${key.replace(/_/g, ' ')}: ${value}</span>`)
        .join('');

    row.innerHTML = `
        <td class="text-capitalize">${method.replace('_', ' ')}</td>
        <td class="text-end">
            <input type="number" class="form-control form-control-sm payment-amount" value="${payment.amount ?? 0}" min="0" step="0.01">
        </td>
        <td class="text-end">
            <input type="number" class="form-control form-control-sm payment-tip" value="${payment.tip_amount ?? 0}" min="0" step="0.01">
        </td>
        <td>${detailsPreview || '<span class="text-muted">No extra details</span>'}</td>
        <td class="text-center">
            <input type="radio" name="primaryPayment" class="form-check-input primary-payment" ${!document.querySelector('.primary-payment:checked') ? 'checked' : ''}>
        </td>
        <td class="text-end">
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-action="edit"><i class="bi bi-pencil"></i></button>
                <button type="button" class="btn btn-outline-danger" data-action="remove"><i class="bi bi-trash"></i></button>
            </div>
        </td>
    `;

    if (!existingRow) {
        tableBody.appendChild(row);
    }
}

document.querySelector('#paymentsTable tbody').addEventListener('click', event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const row = button.closest('tr');
    const action = button.getAttribute('data-action');

    if (action === 'edit') {
        openPaymentEditor(row);
    } else if (action === 'remove') {
        row.remove();
    }
});

function seedExistingPayments() {
    const seed = <?= json_encode($existingPayments, JSON_THROW_ON_ERROR); ?>;
    if (!Array.isArray(seed) || !seed.length) {
        addOrUpdatePaymentRow({ method: 'cash', payment: { amount: <?= (float)$order['total_amount'] ?>, tip_amount: 0 } });
        return;
    }

    seed.forEach(payment => {
        const method = payment.payment_method;
        const payload = {
            amount: parseFloat(payment.amount ?? 0),
            tip_amount: parseFloat(payment.tip_amount ?? 0),
            ...(payment.metadata && typeof payment.metadata === 'object' ? payment.metadata : {})
        };

        addOrUpdatePaymentRow({ method, payment: payload });
    });
}

seedExistingPayments();
</script>

<?php include 'includes/footer.php'; ?>
