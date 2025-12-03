<?php
require_once 'includes/bootstrap.php';

$db = Database::getInstance();
$saleId = $_GET['id'] ?? 0;
$token = $_GET['token'] ?? '';

// Verify token
$expectedToken = md5($saleId . 'receipt_token');
if ($token !== $expectedToken) {
    die('Invalid receipt access token');
}

// Get sale details
$sale = $db->fetchOne("
    SELECT s.*, u.full_name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
", [$saleId]);

if (!$sale) {
    die('Receipt not found');
}

// Get sale items
$items = $db->fetchAll("
    SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id
", [$saleId]);

// Get settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$pageTitle = 'Digital Receipt - ' . $sale['sale_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .receipt-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .digital-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 15px;
            display: inline-block;
        }
        .feedback-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: center;
        }
        .social-share {
            margin-top: 20px;
        }
        .share-btn {
            margin: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 12px;
        }
        .share-whatsapp { background: #25d366; }
        .share-email { background: #6c757d; }
        .share-download { background: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="digital-badge">
                    <i class="bi bi-phone"></i> Digital Receipt
                </div>
                <h2><?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?></h2>
                <p class="text-muted"><?= htmlspecialchars($settings['business_address'] ?? '') ?></p>
                <p class="text-muted">
                    <?= htmlspecialchars($settings['business_phone'] ?? '') ?>
                    <?php if (!empty($settings['business_email'])): ?>
                    | <?= htmlspecialchars($settings['business_email']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <strong>Receipt #:</strong> <?= htmlspecialchars($sale['sale_number']) ?><br>
                    <strong>Date:</strong> <?= formatDate($sale['created_at'], 'd/m/Y H:i') ?><br>
                    <strong>Cashier:</strong> <?= htmlspecialchars($sale['cashier_name']) ?>
                </div>
                <div class="col-md-6">
                    <?php if ($sale['customer_name']): ?>
                    <strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?><br>
                    <?php endif; ?>
                    <strong>Payment:</strong> <?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?><br>
                    <?php if ($sale['payment_method'] === 'mobile_money' && !empty($sale['mobile_money_phone'])): ?>
                    <strong>Mobile No.:</strong> <?= htmlspecialchars($sale['mobile_money_phone']) ?><br>
                    <?php endif; ?>
                    <?php if ($sale['payment_method'] === 'mobile_money' && !empty($sale['mobile_money_reference'])): ?>
                    <strong>Transaction ID:</strong> <?= htmlspecialchars($sale['mobile_money_reference']) ?><br>
                    <?php endif; ?>
                    <strong>Total:</strong> <span class="text-success fw-bold"><?= formatMoney($sale['total_amount']) ?></span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= formatMoney($item['unit_price']) ?></td>
                            <td><?= formatMoney($item['total_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Subtotal:</th>
                            <th><?= formatMoney($sale['subtotal']) ?></th>
                        </tr>
                        <?php if ($sale['tax_amount'] > 0): ?>
                        <tr>
                            <th colspan="3">Tax:</th>
                            <th><?= formatMoney($sale['tax_amount']) ?></th>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-success">
                            <th colspan="3">TOTAL:</th>
                            <th><?= formatMoney($sale['total_amount']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="social-share text-center">
                <p><strong>Share this receipt:</strong></p>
                <a href="whatsapp://send?text=My receipt from <?= urlencode($settings['business_name'] ?? APP_NAME) ?>: <?= urlencode($_SERVER['REQUEST_URI']) ?>" class="share-btn share-whatsapp">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                <a href="mailto:?subject=Receipt from <?= urlencode($settings['business_name'] ?? APP_NAME) ?>&body=Here's my receipt: <?= urlencode($_SERVER['REQUEST_URI']) ?>" class="share-btn share-email">
                    <i class="bi bi-envelope"></i> Email
                </a>
                <a href="print-receipt.php?id=<?= $sale['id'] ?>" target="_blank" class="share-btn share-download">
                    <i class="bi bi-download"></i> Print/Download
                </a>
            </div>

            <div class="feedback-section">
                <h5><i class="bi bi-star"></i> Rate Your Experience</h5>
                <p class="text-muted">Help us improve by sharing your feedback</p>
                <div class="rating-stars mb-3">
                    <i class="bi bi-star text-warning" data-rating="1"></i>
                    <i class="bi bi-star text-warning" data-rating="2"></i>
                    <i class="bi bi-star text-warning" data-rating="3"></i>
                    <i class="bi bi-star text-warning" data-rating="4"></i>
                    <i class="bi bi-star text-warning" data-rating="5"></i>
                </div>
                <textarea class="form-control mb-3" placeholder="Tell us about your experience..." rows="3"></textarea>
                <button class="btn btn-success" onclick="submitFeedback()">
                    <i class="bi bi-send"></i> Submit Feedback
                </button>
            </div>

            <?php if (!empty($settings['current_promotion'])): ?>
            <div class="alert alert-info text-center mt-4">
                <h6><i class="bi bi-gift"></i> Special Offer</h6>
                <p class="mb-1"><?= htmlspecialchars($settings['current_promotion']) ?></p>
                <?php if (!empty($settings['promo_code'])): ?>
                <small>Use code: <strong><?= htmlspecialchars($settings['promo_code']) ?></strong></small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <p class="text-muted small">
                    Thank you for choosing <?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?>!<br>
                    This digital receipt is valid for returns and exchanges.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Star rating functionality
        document.querySelectorAll('.rating-stars i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                document.querySelectorAll('.rating-stars i').forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill');
                    } else {
                        s.classList.remove('bi-star-fill');
                        s.classList.add('bi-star');
                    }
                });
            });
        });

        function submitFeedback() {
            const rating = document.querySelectorAll('.rating-stars .bi-star-fill').length;
            const comment = document.querySelector('textarea').value;
            
            if (rating === 0) {
                alert('Please select a rating');
                return;
            }

            // Here you would typically send the feedback to your server
            alert('Thank you for your feedback!');
        }
    </script>
</body>
</html>
