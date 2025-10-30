<?php
// Prevent browser caching for dynamic content
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
            <?php $userRole = $auth->getUser()['role'] ?? 'guest'; ?>
            
            <!-- Dashboard (All roles) -->
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? ' active' : '' ?>" href="/wapos/index.php">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            
            <?php if (in_array($userRole, ['admin', 'manager', 'cashier'])): ?>
            <!-- POS (Admin, Manager, Cashier) -->
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? ' active' : '' ?>" href="/wapos/pos.php">
                <i class="bi bi-cart-plus me-2"></i>Retail POS
            </a>
            <?php endif; ?>
            
            <?php if (in_array($userRole, ['admin', 'manager', 'waiter', 'cashier'])): ?>
            <!-- Restaurant Operations (Admin, Manager, Waiter, Cashier) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Restaurant</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'restaurant.php' ? ' active' : '' ?>" href="/wapos/restaurant.php">
                <i class="bi bi-cup-hot me-2"></i>Orders
            </a>
            <?php if (in_array($userRole, ['admin', 'manager'])): ?>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'kitchen-display.php' ? ' active' : '' ?>" href="/wapos/kitchen-display.php">
                <i class="bi bi-fire me-2"></i>Kitchen Display
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage-tables.php' ? ' active' : '' ?>" href="/wapos/manage-tables.php">
                <i class="bi bi-table me-2"></i>Manage Tables
            </a>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (in_array($userRole, ['admin', 'manager', 'rider'])): ?>
            <!-- Delivery (Admin, Manager, Rider) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Delivery</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'delivery.php' ? ' active' : '' ?>" href="/wapos/delivery.php">
                <i class="bi bi-truck me-2"></i>Deliveries
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'enhanced-delivery-tracking.php' ? ' active' : '' ?>" href="/wapos/enhanced-delivery-tracking.php">
                <i class="bi bi-geo-alt me-2"></i>Tracking
            </a>
            <?php endif; ?>
            
            <?php if (in_array($userRole, ['admin', 'manager'])): ?>
            <!-- Management Tools (Admin, Manager) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Management</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'void-order-management.php' ? ' active' : '' ?>" href="/wapos/void-order-management.php">
                <i class="bi bi-x-circle me-2"></i>Void Orders
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'rooms.php' ? ' active' : '' ?>" href="/wapos/rooms.php">
                <i class="bi bi-building me-2"></i>Rooms
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="/wapos/locations.php">
                <i class="bi bi-geo-alt me-2"></i>Locations
            </a>
            <?php endif; ?>
            
            <?php if (in_array($userRole, ['admin', 'manager', 'inventory_manager', 'cashier'])): ?>
            <!-- Inventory (Admin, Manager, Inventory Manager, Cashier) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Inventory</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="/wapos/products.php">
                <i class="bi bi-box me-2"></i>Products
            </a>
            <?php if (in_array($userRole, ['admin', 'manager', 'inventory_manager'])): ?>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="/wapos/inventory.php">
                <i class="bi bi-boxes me-2"></i>Inventory
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'goods-received.php' ? ' active' : '' ?>" href="/wapos/goods-received.php">
                <i class="bi bi-truck me-2"></i>Goods Received
            </a>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (in_array($userRole, ['admin', 'manager', 'cashier', 'accountant'])): ?>
            <!-- Sales (Admin, Manager, Cashier, Accountant) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Sales</small>
            </div>
            <?php if (in_array($userRole, ['admin', 'manager', 'cashier'])): ?>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? ' active' : '' ?>" href="/wapos/customers.php">
                <i class="bi bi-people me-2"></i>Customers
            </a>
            <?php endif; ?>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? ' active' : '' ?>" href="/wapos/sales.php">
                <i class="bi bi-receipt me-2"></i>Sales History
            </a>
            <?php endif; ?>
            
            <?php if (in_array($userRole, ['admin', 'manager', 'accountant'])): ?>
            <!-- Financial Management (Admin, Manager, Accountant) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Finance</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'accounting.php' ? ' active' : '' ?>" href="/wapos/accounting.php">
                <i class="bi bi-calculator me-2"></i>Accounting
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? ' active' : '' ?>" href="/wapos/reports.php">
                <i class="bi bi-file-earmark-text me-2"></i>Reports
            </a>
            <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'profit-and-loss.php' ? ' active' : '' ?>" href="/wapos/reports/profit-and-loss.php">
                <i class="bi bi-journal-text me-2"></i>Profit & Loss
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'balance-sheet.php' ? ' active' : '' ?>" href="/wapos/reports/balance-sheet.php">
                <i class="bi bi-clipboard-data me-2"></i>Balance Sheet
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'sales-tax-report.php' ? ' active' : '' ?>" href="/wapos/reports/sales-tax-report.php">
                <i class="bi bi-file-spreadsheet me-2"></i>Tax Report
            </a>
            <?php endif; ?>
            <?php endif; ?>
            
            
            <?php if ($userRole === 'admin'): ?>
            <!-- System Administration (Admin Only) -->
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase fw-bold">Administration</small>
            </div>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="/wapos/users.php">
                <i class="bi bi-person-gear me-2"></i>Users
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'permissions.php' ? ' active' : '' ?>" href="/wapos/permissions.php">
                <i class="bi bi-shield-lock me-2"></i>Permissions
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'system-health.php' ? ' active' : '' ?>" href="/wapos/system-health.php">
                <i class="bi bi-heart-pulse me-2"></i>System Health
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? ' active' : '' ?>" href="/wapos/settings.php">
                <i class="bi bi-gear me-2"></i>Settings
            </a>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'currency-settings.php' ? ' active' : '' ?>" href="/wapos/currency-settings.php">
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
                    <li><a class="dropdown-item" href="/wapos/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/wapos/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        <div class="p-4">
<?php else: ?>
    <div class="container-fluid">
<?php endif; ?>
