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

// Get settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
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
                                <h6 class="text-primary">Payment Method</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="cash" value="cash" checked>
                                        <label class="form-check-label" for="cash">
                                            <i class="bi bi-cash-stack me-2"></i>Cash
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="card" value="card">
                                        <label class="form-check-label" for="card">
                                            <i class="bi bi-credit-card me-2"></i>Credit/Debit Card
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="mobile_money" value="mobile_money">
                                        <label class="form-check-label" for="mobile_money">
                                            <i class="bi bi-phone me-2"></i>Mobile Money
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="bank_transfer" value="bank_transfer">
                                        <label class="form-check-label" for="bank_transfer">
                                            <i class="bi bi-bank me-2"></i>Bank Transfer
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-primary">Payment Details</h6>
                                
                                <!-- Cash Payment Details -->
                                <div id="cashDetails" class="payment-details">
                                    <div class="mb-3">
                                        <label for="amountReceived" class="form-label">Amount Received</label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="number" class="form-control" id="amountReceived" 
                                                   value="<?= $order['total_amount'] ?>" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Change Due</label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="text" class="form-control" id="changeDue" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Payment Details -->
                                <div id="cardDetails" class="payment-details d-none">
                                    <div class="mb-3">
                                        <label for="cardNumber" class="form-label">Card Number (Last 4 digits)</label>
                                        <input type="text" class="form-control" id="cardNumber" placeholder="****" maxlength="4">
                                    </div>
                                    <div class="mb-3">
                                        <label for="cardType" class="form-label">Card Type</label>
                                        <select class="form-select" id="cardType">
                                            <option value="visa">Visa</option>
                                            <option value="mastercard">Mastercard</option>
                                            <option value="amex">American Express</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="authCode" class="form-label">Authorization Code</label>
                                        <input type="text" class="form-control" id="authCode" placeholder="Optional">
                                    </div>
                                </div>
                                
                                <!-- Mobile Money Details -->
                                <div id="mobileDetails" class="payment-details d-none">
                                    <div class="mb-3">
                                        <label for="mobileProvider" class="form-label">Provider</label>
                                        <select class="form-select" id="mobileProvider">
                                            <option value="mpesa">M-Pesa</option>
                                            <option value="airtel">Airtel Money</option>
                                            <option value="tkash">T-Kash</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="mobileNumber" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="mobileNumber" placeholder="0700000000">
                                    </div>
                                    <div class="mb-3">
                                        <label for="transactionId" class="form-label">Transaction ID</label>
                                        <input type="text" class="form-control" id="transactionId" placeholder="Optional">
                                    </div>
                                </div>
                                
                                <!-- Bank Transfer Details -->
                                <div id="bankDetails" class="payment-details d-none">
                                    <div class="mb-3">
                                        <label for="bankName" class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" id="bankName" placeholder="Bank name">
                                    </div>
                                    <div class="mb-3">
                                        <label for="referenceNumber" class="form-label">Reference Number</label>
                                        <input type="text" class="form-control" id="referenceNumber" placeholder="Transfer reference">
                                    </div>
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
// Payment method switching
document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Hide all payment details
        document.querySelectorAll('.payment-details').forEach(detail => {
            detail.classList.add('d-none');
        });
        
        // Show selected payment details
        const selectedMethod = this.value;
        const detailsMap = {
            'cash': 'cashDetails',
            'card': 'cardDetails',
            'mobile_money': 'mobileDetails',
            'bank_transfer': 'bankDetails'
        };
        
        if (detailsMap[selectedMethod]) {
            document.getElementById(detailsMap[selectedMethod]).classList.remove('d-none');
        }
    });
});

// Calculate change for cash payments
document.getElementById('amountReceived').addEventListener('input', function() {
    const totalAmount = parseFloat(document.getElementById('totalAmount').value);
    const amountReceived = parseFloat(this.value) || 0;
    const change = amountReceived - totalAmount;
    
    document.getElementById('changeDue').value = change >= 0 ? change.toFixed(2) : '0.00';
    
    // Highlight if insufficient payment
    if (change < 0) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

async function processPayment() {
    const orderId = document.getElementById('orderId').value;
    const totalAmount = parseFloat(document.getElementById('totalAmount').value);
    const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
    
    // Validate payment based on method
    let paymentData = {
        order_id: orderId,
        payment_method: paymentMethod,
        amount_paid: totalAmount,
        notes: document.getElementById('paymentNotes').value
    };
    
    // Add method-specific data
    if (paymentMethod === 'cash') {
        const amountReceived = parseFloat(document.getElementById('amountReceived').value);
        if (amountReceived < totalAmount) {
            alert('Insufficient payment amount');
            return;
        }
        paymentData.amount_received = amountReceived;
        paymentData.change_amount = amountReceived - totalAmount;
    } else if (paymentMethod === 'card') {
        paymentData.card_last_four = document.getElementById('cardNumber').value;
        paymentData.card_type = document.getElementById('cardType').value;
        paymentData.auth_code = document.getElementById('authCode').value;
    } else if (paymentMethod === 'mobile_money') {
        paymentData.mobile_provider = document.getElementById('mobileProvider').value;
        paymentData.mobile_number = document.getElementById('mobileNumber').value;
        paymentData.transaction_id = document.getElementById('transactionId').value;
    } else if (paymentMethod === 'bank_transfer') {
        paymentData.bank_name = document.getElementById('bankName').value;
        paymentData.reference_number = document.getElementById('referenceNumber').value;
    }
    
    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'process_payment',
                ...paymentData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Payment processed successfully!');
            
            // Ask if want to print receipt
            if (confirm('Print customer receipt?')) {
                window.open('print-customer-receipt.php?id=' + orderId, '_blank', 'width=400,height=600');
            }
            
            // Redirect to restaurant main page
            window.location.href = 'restaurant.php';
            
        } else {
            alert('Error processing payment: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Initialize change calculation
document.getElementById('amountReceived').dispatchEvent(new Event('input'));
</script>

<?php include 'includes/footer.php'; ?>
