<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . " - " : "" ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    
    <style>
        :root {
            --sidebar-width: 250px;
        }
        body {
            font-size: 0.9rem;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f8f9fa;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .top-bar {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<?php if (isset($auth) && $auth->isLoggedIn()): ?>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3 border-bottom">
            <h5 class="mb-0"><i class="bi bi-shop me-2"></i><?= APP_NAME ?></h5>
            <small class="text-light opacity-75">Point of Sale System</small>
        </div>
        
        <nav class="nav flex-column">
            <!-- Core Operations -->
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? ' active' : '' ?>" href="index.php">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? ' active' : '' ?>" href="pos.php">
                <i class="bi bi-cart-plus me-2"></i>Retail POS
            </a>
            
            <!-- Business Operations -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Business Operations</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'restaurant.php' ? ' active' : '' ?>" href="restaurant.php">
                <i class="bi bi-cup-hot me-2"></i>Restaurant
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'kitchen-display.php' ? ' active' : '' ?>" href="kitchen-display.php">
                <i class="bi bi-fire me-2"></i>Kitchen Display
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'delivery.php' ? ' active' : '' ?>" href="delivery.php">
                <i class="bi bi-truck me-2"></i>Delivery
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'enhanced-delivery-tracking.php' ? ' active' : '' ?>" href="enhanced-delivery-tracking.php">
                <i class="bi bi-geo-alt me-2"></i>Enhanced Tracking
            </a>
            
            <!-- WhatsApp Integration -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">WhatsApp</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'whatsapp-integration.php' ? ' active' : '' ?>" href="whatsapp-integration.php">
                <i class="bi bi-whatsapp me-2"></i>WhatsApp Setup
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'whatsapp-orders.php' ? ' active' : '' ?>" href="whatsapp-orders.php">
                <i class="bi bi-chat-square-dots me-2"></i>WhatsApp Orders
            </a>
            
            <!-- Void Management -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Void Management</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'void-order-management.php' ? ' active' : '' ?>" href="void-order-management.php">
                <i class="bi bi-x-circle me-2"></i>Void Orders
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'void-settings.php' ? ' active' : '' ?>" href="void-settings.php">
                <i class="bi bi-gear me-2"></i>Void Settings
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'rooms.php' ? ' active' : '' ?>" href="rooms.php">
                <i class="bi bi-building me-2"></i>Rooms
            </a>
            
            <!-- Inventory & Products -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Inventory</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="products.php">
                <i class="bi bi-box me-2"></i>Products
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="inventory.php">
                <i class="bi bi-boxes me-2"></i>Inventory Management
            </a>
            
            <!-- Customer & Sales -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Sales & CRM</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? ' active' : '' ?>" href="customers.php">
                <i class="bi bi-people me-2"></i>Customers
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? ' active' : '' ?>" href="sales.php">
                <i class="bi bi-graph-up me-2"></i>Sales
            </a>
            
            <!-- Financial Management -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Finance</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'accounting.php' ? ' active' : '' ?>" href="accounting.php">
                <i class="bi bi-calculator me-2"></i>Accounting
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'profit-and-loss.php' ? ' active' : '' ?>" href="reports/profit-and-loss.php">
                <i class="bi bi-journal-text me-2"></i>Profit &amp; Loss
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'balance-sheet.php' ? ' active' : '' ?>" href="reports/balance-sheet.php">
                <i class="bi bi-clipboard-data me-2"></i>Balance Sheet
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'sales-tax-report.php' ? ' active' : '' ?>" href="reports/sales-tax-report.php">
                <i class="bi bi-file-spreadsheet me-2"></i>Sales Tax Report
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? ' active' : '' ?>" href="reports.php">
                <i class="bi bi-file-earmark-text me-2"></i>Reports
            </a>
            
            <!-- Management (Admin/Manager only) -->
            <?php if (isset($auth) && ($auth->hasRole('admin') || $auth->hasRole('manager'))): ?>
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Management</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage-tables.php' ? ' active' : '' ?>" href="manage-tables.php">
                <i class="bi bi-table me-2"></i>Manage Tables
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage-rooms.php' ? ' active' : '' ?>" href="manage-rooms.php">
                <i class="bi bi-door-open me-2"></i>Manage Rooms
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="locations.php">
                <i class="bi bi-geo-alt me-2"></i>Locations
            </a>
            <?php endif; ?>
            
            <!-- System Administration (Admin only) -->
            <?php if (isset($auth) && $auth->hasRole('admin')): ?>
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Administration</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="users.php">
                <i class="bi bi-person-gear me-2"></i>Users
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'permissions.php' ? ' active' : '' ?>" href="permissions.php">
                <i class="bi bi-shield-lock me-2"></i>Permissions
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'system-health.php' ? ' active' : '' ?>" href="system-health.php">
                <i class="bi bi-heart-pulse me-2"></i>System Health
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'receipt-settings.php' ? ' active' : '' ?>" href="receipt-settings.php">
                <i class="bi bi-receipt me-2"></i>Receipt Settings
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'restaurant-printer-settings.php' ? ' active' : '' ?>" href="restaurant-printer-settings.php">
                <i class="bi bi-printer me-2"></i>Restaurant Printers
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? ' active' : '' ?>" href="settings.php">
                <i class="bi bi-gear me-2"></i>Settings
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'currency-settings.php' ? ' active' : '' ?>" href="currency-settings.php">
                <i class="bi bi-currency-exchange me-2"></i>Currency
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h4 class="mb-0"><?= isset($pageTitle) ? $pageTitle : 'Dashboard' ?></h4>
                <small class="text-muted"><?= date('l, F j, Y') ?></small>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i><?= $auth->getUser()['username'] ?? 'User' ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        <div class="p-4">
<?php else: ?>
    <div class="container-fluid">
<?php endif; ?>
