<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    <link rel="icon" href="<?= APP_URL ?>/assets/images/logo.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        .sidebar .brand {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left-color: #3498db;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 0;
        }
        .top-bar {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .content-wrapper {
            padding: 0 1.5rem 1.5rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
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
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Logo -->
        <div class="brand text-center">
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="WAPOS" style="max-width: 120px; margin: 0 auto;">
        </div>
        
        <nav class="nav flex-column mt-3">
            <!-- Dashboard - Always visible -->
            <a href="<?= APP_URL ?>/index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            
            <?php 
            $userRole = $auth->getRole();
            $menuItems = [];
            
            // Define menu items based on role
            if ($userRole === 'admin') {
                $menuItems = [
                    'operations' => [
                        'pos' => ['Retail POS', 'bi-cart-plus'],
                        'restaurant' => ['Restaurant', 'bi-shop'],
                        'rooms' => ['Rooms', 'bi-building'],
                        'delivery' => ['Delivery', 'bi-truck'],
                        'products' => ['Products', 'bi-box-seam'],
                        'sales' => ['Sales', 'bi-receipt'],
                        'customers' => ['Customers', 'bi-people'],
                        'reports' => ['Reports', 'bi-graph-up'],
                        'accounting' => ['Accounting', 'bi-calculator']
                    ],
                    'management' => [
                        'manage-tables' => ['Manage Tables', 'bi-table'],
                        'manage-rooms' => ['Manage Rooms', 'bi-door-open'],
                        'locations' => ['Locations', 'bi-geo-alt'],
                        'users' => ['Users', 'bi-person-badge'],
                        'permissions' => ['Permissions', 'bi-shield-lock'],
                        'system-health' => ['System Health', 'bi-heart-pulse'],
                        'settings' => ['Settings', 'bi-gear']
                    ]
                ];
            } elseif ($userRole === 'manager') {
                $menuItems = [
                    'operations' => [
                        'pos' => ['Retail POS', 'bi-cart-plus'],
                        'restaurant' => ['Restaurant', 'bi-shop'],
                        'rooms' => ['Rooms', 'bi-building'],
                        'delivery' => ['Delivery', 'bi-truck'],
                        'products' => ['Products', 'bi-box-seam'],
                        'sales' => ['Sales', 'bi-receipt'],
                        'customers' => ['Customers', 'bi-people'],
                        'reports' => ['Reports', 'bi-graph-up'],
                        'accounting' => ['Accounting', 'bi-calculator']
                    ]
                ];
            } elseif ($userRole === 'cashier') {
                $menuItems = [
                    'operations' => [
                        'pos' => ['Retail POS', 'bi-cart-plus'],
                        'customers' => ['Customers', 'bi-people']
                    ]
                ];
            } elseif ($userRole === 'waiter') {
                $menuItems = [
                    'operations' => [
                        'restaurant' => ['Restaurant', 'bi-shop']
                    ]
                ];
            } elseif ($userRole === 'rider') {
                $menuItems = [
                    'operations' => [
                        'delivery' => ['Delivery', 'bi-truck']
                    ]
                ];
            } elseif ($userRole === 'inventory_manager') {
                $menuItems = [
                    'operations' => [
                        'products' => ['Products', 'bi-box-seam']
                    ]
                ];
            }
            
            // Display menu sections
            foreach ($menuItems as $sectionKey => $items):
                if (!empty($items)):
            ?>
            <div class="mt-3 px-3">
                <small class="text-white-50 text-uppercase"><?= ucfirst($sectionKey) ?></small>
            </div>
            <?php foreach ($items as $page => $info): ?>
            <a href="<?= APP_URL ?>/<?= $page ?>.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === $page . '.php' ? 'active' : '' ?>">
                <i class="<?= $info[1] ?> me-2"></i><?= $info[0] ?>
            </a>
            <?php endforeach; ?>
            <?php 
                endif;
            endforeach; 
            ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><?= isset($pageTitle) ? $pageTitle : 'Dashboard' ?></h5>
                    <small class="text-muted"><?= date('l, F j, Y') ?></small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($auth->getUsername()) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="content-wrapper">
