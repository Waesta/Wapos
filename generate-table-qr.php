<?php
/**
 * Generate QR Codes for Table Digital Menus
 * 
 * Creates printable QR codes that link to the digital menu for each table
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

// Get all active tables
$tables = $db->fetchAll("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number");

// Get business info
$businessName = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'business_name'")['setting_value'] ?? 'Restaurant';
$businessLogo = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'branding_logo'")['setting_value'] ?? '';
$primaryColor = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'branding_primary_color'")['setting_value'] ?? '#0d6efd';

// Base URL for digital menu
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

$pageTitle = 'Table QR Codes';
include 'includes/header.php';
?>

<style>
    .qr-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    
    .qr-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        page-break-inside: avoid;
    }
    
    .qr-card h4 {
        margin-bottom: 1rem;
        color: <?= htmlspecialchars($primaryColor) ?>;
    }
    
    .qr-code-container {
        background: white;
        padding: 1rem;
        border-radius: 8px;
        display: inline-block;
        margin-bottom: 1rem;
    }
    
    .qr-code-container img,
    .qr-code-container canvas {
        max-width: 200px;
        height: auto;
    }
    
    .qr-instructions {
        font-size: 0.875rem;
        color: #666;
        margin-top: 0.5rem;
    }
    
    .print-header {
        display: none;
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        
        .print-header {
            display: block;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .qr-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .qr-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        body {
            background: white !important;
        }
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="mb-1"><i class="bi bi-qr-code me-2"></i>Table QR Codes</h4>
            <p class="text-muted mb-0">Generate and print QR codes for digital menu access at each table</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage-tables.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Tables
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print All QR Codes
            </button>
        </div>
    </div>
    
    <!-- Print Header -->
    <div class="print-header">
        <?php if ($businessLogo): ?>
        <img src="<?= htmlspecialchars($businessLogo) ?>" alt="" style="max-height: 60px; margin-bottom: 1rem;">
        <?php endif; ?>
        <h2><?= htmlspecialchars($businessName) ?></h2>
        <p>Scan the QR code to view our digital menu</p>
    </div>
    
    <!-- Info Card -->
    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>How It Works</h6>
                    <p class="text-muted mb-0">
                        Each QR code links to your digital menu with the table pre-selected. 
                        Print these codes and place them on tables for guests to scan with their phones.
                        The digital menu URL is: <code><?= htmlspecialchars($baseUrl) ?>/digital-menu.php</code>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="digital-menu.php" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>Preview Digital Menu
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (empty($tables)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No tables configured yet.
        <a href="manage-tables.php">Add tables first</a> to generate QR codes.
    </div>
    <?php else: ?>
    
    <!-- QR Code Grid -->
    <div class="qr-grid">
        <?php foreach ($tables as $table): ?>
        <?php 
        $menuUrl = $baseUrl . '/digital-menu.php?table=' . $table['id'];
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($menuUrl);
        ?>
        <div class="qr-card">
            <h4>
                <i class="bi bi-table me-1"></i>
                Table <?= htmlspecialchars($table['table_number']) ?>
            </h4>
            <?php if ($table['table_name']): ?>
            <p class="text-muted mb-2"><?= htmlspecialchars($table['table_name']) ?></p>
            <?php endif; ?>
            
            <div class="qr-code-container">
                <img src="<?= htmlspecialchars($qrApiUrl) ?>" 
                     alt="QR Code for Table <?= htmlspecialchars($table['table_number']) ?>"
                     loading="lazy">
            </div>
            
            <div class="qr-instructions">
                <i class="bi bi-phone me-1"></i>Scan to view menu
            </div>
            
            <div class="mt-2 no-print">
                <small class="text-muted d-block" style="word-break: break-all;">
                    <?= htmlspecialchars($menuUrl) ?>
                </small>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyUrl('<?= htmlspecialchars($menuUrl) ?>')">
                    <i class="bi bi-clipboard me-1"></i>Copy Link
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</div>

<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Link copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
        prompt('Copy this link:', url);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
