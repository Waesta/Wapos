<?php
// Prevent browser caching for dynamic content
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="<?= function_exists('current_locale') ? current_locale() : 'en' ?>"<?= function_exists('is_rtl') && is_rtl() ? ' dir="rtl"' : '' ?>>
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
    
    <!-- PWA Manifest & Meta -->
    <?php
    $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) 
                   || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
    $manifestFile = $isLocalhost ? 'manifest.json' : 'manifest-prod.json';
    ?>
    <link rel="manifest" href="<?= APP_URL ?>/<?= $manifestFile ?>">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WAPOS">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/images/icons/icon-192.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="WAPOS">
    <meta name="msapplication-TileColor" content="#2563eb">
    <meta name="msapplication-TileImage" content="<?= APP_URL ?>/assets/images/icons/icon-144.png">
    
    <!-- PWA Scripts -->
    <script>window.APP_URL = '<?= APP_URL ?>';</script>
    <script defer src="<?= APP_URL ?>/assets/js/offline-manager.js"></script>
    <script defer src="<?= APP_URL ?>/assets/js/pwa-install.js"></script>
    
    <!-- UX Enhancements -->
    <script defer src="<?= APP_URL ?>/assets/js/ux-enhancements.js"></script>
    <script defer src="<?= APP_URL ?>/assets/js/smart-helpers.js"></script>
    
    <style>
        :root {
            --sidebar-width: 250px;

            /* Primary palette */
            --color-primary: #0d6efd;
            --color-primary-dark: #0a58ca;
            --color-secondary: #6c757d;
            --color-success: #198754;
            --color-info: #0dcaf0;
            --color-warning: #ffc107;
            --color-danger: #dc3545;

            /* Neutral palette */
            --color-surface: #ffffff;
            --color-surface-alt: #f1f3f5;
            --color-background: #f8f9fa;
            --color-border: #dee2e6;
            --color-border-strong: #c0c4cc;
            --color-text: #1f2933;
            --color-text-muted: #6c757d;
            --color-text-inverse: #ffffff;

            /* Shadows & elevation */
            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.08);
            --shadow-sm: 0 2px 6px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 12px 32px rgba(15, 23, 42, 0.12);

            /* Radius */
            --radius-sm: 0.35rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;

            /* Spacing scale */
            --spacing-2xs: 0.125rem;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;

            /* Typography scale */
            --font-base: "Inter", "Segoe UI", system-ui, sans-serif;
            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-md: 0.95rem;
            --text-lg: 1.125rem;
            --text-xl: 1.5rem;
            --text-display: 2rem;
            --line-tight: 1.2;
            --line-normal: 1.45;

            /* Motion */
            --transition-base: 0.2s ease;
        }
        body {
            font-family: var(--font-base);
            font-size: var(--text-md);
            color: var(--color-text);
            background-color: var(--color-background);
            line-height: var(--line-normal);
        }
        h1, h2, h3, h4, h5, h6 {
            line-height: var(--line-tight);
            font-weight: 600;
            color: var(--color-text);
        }
        h1 { font-size: var(--text-display); }
        h2 { font-size: 1.75rem; }
        h3 { font-size: 1.5rem; }
        h4 { font-size: 1.25rem; }
        h5 { font-size: var(--text-lg); }
        h6 { font-size: var(--text-sm); text-transform: uppercase; letter-spacing: 0.04em; }
        .text-muted { color: var(--color-text-muted) !important; }
        .fw-semibold { font-weight: 600 !important; }

        .app-card {
            background: var(--color-surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            padding: var(--spacing-md);
        }
        .app-card[data-elevation="md"] { box-shadow: var(--shadow-md); }
        .app-card-header {
            margin-bottom: var(--spacing-sm);
        }
        .app-table {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .app-table table {
            margin-bottom: 0;
        }
        .app-table thead {
            background-color: #f1f3f5;
            color: #212529;
        }
        .app-table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .app-table .table > :not(caption) > * > * {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }

        .app-status {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: 0.35rem 0.65rem;
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
            font-weight: 600;
        }
        .app-status[data-color="primary"] { background: rgba(13,110,253,0.12); color: var(--color-primary); }
        .app-status[data-color="success"] { background: rgba(25,135,84,0.12); color: var(--color-success); }
        .app-status[data-color="warning"] { background: rgba(255,193,7,0.18); color: #b88600; }
        .app-status[data-color="danger"] { background: rgba(220,53,69,0.12); color: var(--color-danger); }
        .app-status[data-color="info"] { background: rgba(13,202,240,0.12); color: #087990; }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        .btn {
            font-weight: 500;
            transition: transform var(--transition-base), box-shadow var(--transition-base);
        }
        .btn:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .btn-icon:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: var(--shadow-xs);
        }
        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }
        .stack-md { display: flex; flex-direction: column; gap: var(--spacing-md); }
        .stack-lg { display: flex; flex-direction: column; gap: var(--spacing-lg); }
        .stack-sm { display: flex; flex-direction: column; gap: var(--spacing-sm); }
        .stack-sm > * + * { margin-top: 0; }
        .badge-soft {
            border-radius: var(--radius-sm);
            padding: 0.35rem 0.6rem;
            font-size: var(--text-xs);
            font-weight: 600;
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
            transform: translateX(0);
            transition: transform 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: var(--color-background);
            transition: margin-left 0.3s ease;
        }
        .sidebar-brand-title {
            color: #fdfdff;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sidebar-brand-title .brand-icon {
            color: #9ec5ff;
            font-size: 1.35rem;
        }
        .sidebar-brand-subtitle {
            color: rgba(255,255,255,0.85);
            letter-spacing: 0.02em;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) 0 var(--spacing-lg);
        }
        .nav-group {
            padding: 0 var(--spacing-sm);
            border-radius: var(--radius-md);
        }
        .nav-group + .nav-group {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: var(--spacing-sm);
            margin-top: var(--spacing-sm);
        }
        .nav-group-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            color: rgba(255,255,255,0.7);
            font-size: var(--text-xs);
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-xs) var(--spacing-sm);
        }
        .nav-group.open .nav-group-toggle {
            color: #ffffff;
        }
        .nav-group-toggle i {
            transition: transform 0.2s ease;
        }
        .nav-group.open .nav-group-toggle i {
            transform: rotate(180deg);
        }
        .nav-group-items {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease;
        }
        .nav-group.open .nav-group-items {
            max-height: 500px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.82);
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            margin: 0 4px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: var(--text-sm);
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .sidebar .nav-link .nav-label {
            flex: 1;
        }
        .sidebar .nav-link .nav-badge {
            font-size: var(--text-xs);
            background: rgba(255,255,255,0.16);
            padding: 0.1rem 0.45rem;
            border-radius: 999px;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
            transform: translateX(2px);
        }
        .sidebar .nav-link.active {
            background: rgba(13,110,253,0.2);
            color: #ffffff;
        }
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 999;
        }
        .sidebar-overlay.show {
            opacity: 1;
            pointer-events: all;
        }
        body.sidebar-open {
            overflow: hidden;
        }
        .top-bar {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }
        .top-bar-title {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-width: 220px;
        }
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            flex: 1 1 auto;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .top-bar-sync {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            flex-wrap: wrap;
        }
        .top-bar-actions .dropdown .btn {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-xs);
        }
        #online-status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .sidebar-toggle-btn,
        .sidebar-close-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 1200px) {
            :root {
                --sidebar-width: 230px;
            }
        }

        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 280px;
            }
            .sidebar {
                width: min(85vw, 320px);
                transform: translateX(-100%);
                box-shadow: var(--shadow-md);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 20px;
            }
            .top-bar-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: var(--spacing-sm);
            }
            .top-bar-sync {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: var(--spacing-xs);
            }
            .top-bar-sync button {
                width: 100%;
            }
            .top-bar-sync #online-status {
                width: auto;
                align-self: flex-start;
            }
            .top-bar-actions .dropdown {
                width: 100%;
            }
            .top-bar-actions .dropdown > .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            :root {
                --sidebar-width: 100%;
            }
            .top-bar {
                padding: 10px 16px;
            }
            .sidebar .nav-link {
                padding: 0.65rem 0.75rem;
                margin: 0 2px;
            }
            .top-bar-title h4 {
                font-size: 1.05rem;
            }
        }

        @media (min-width: 1025px) {
            .sidebar-toggle-btn,
            .sidebar-close-btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<?php if (isset($auth) && $auth->isLoggedIn()): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
    <aside class="sidebar" id="appSidebar" aria-label="Primary Navigation">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 sidebar-brand-title">
                    <i class="bi bi-shop brand-icon"></i>
                    <?= APP_NAME ?>
                </h5>
                <small class="sidebar-brand-subtitle">Point of Sale System</small>
            </div>
            <button class="btn btn-outline-light sidebar-close-btn" type="button" id="sidebarCloseBtn" aria-label="Close navigation">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="sidebar-nav" id="sidebarNav">
            <?php
                $userRole = $auth->getRole() ?? 'guest';
                $currentPage = basename($_SERVER['PHP_SELF']);
                $systemManager = SystemManager::getInstance();
                $privilegedRoles = ['super_admin', 'superadmin', 'developer'];
                $isPrivileged = in_array($userRole, $privilegedRoles, true);
                
                // Base path - detect localhost vs production
                $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) 
                               || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
                $basePath = $isLocalhost ? '/wapos' : '';
                
                // Determine dashboard URL based on role
                $dashboardUrls = [
                    'super_admin' => $basePath . '/dashboards/admin.php',
                    'superadmin' => $basePath . '/dashboards/admin.php',
                    'developer' => $basePath . '/dashboards/admin.php',
                    'admin' => $basePath . '/dashboards/admin.php',
                    'manager' => $basePath . '/dashboards/manager.php',
                    'accountant' => $basePath . '/dashboards/accountant.php',
                    'cashier' => $basePath . '/dashboards/cashier.php',
                    'waiter' => $basePath . '/dashboards/waiter.php',
                ];
                $userDashboard = $dashboardUrls[$userRole] ?? $basePath . '/pos.php';
                
                $navGroups = [
                    'core' => [
                        'label' => 'Core',
                        'items' => [
                            [
                                'roles' => 'all',
                                'href' => $userDashboard,
                                'page' => basename($userDashboard),
                                'icon' => 'bi-speedometer2',
                                'label' => 'Dashboard'
                            ],
                            [
                                'roles' => 'all',
                                'href' => $basePath . '/feedback.php',
                                'page' => 'feedback.php',
                                'icon' => 'bi-chat-dots',
                                'label' => 'User Feedback'
                            ],
                            [
                                'roles' => 'all',
                                'href' => $basePath . '/user-manual.php',
                                'page' => 'user-manual.php',
                                'icon' => 'bi-book',
                                'label' => 'User Manual'
                            ],
                            [
                                'roles' => ['admin','manager','super_admin','developer'],
                                'href' => $basePath . '/executive-dashboard.php',
                                'page' => 'executive-dashboard.php',
                                'icon' => 'bi-graph-up-arrow',
                                'label' => 'Executive KPIs'
                            ]
                        ]
                    ],
                    'retail' => [
                        'label' => 'Retail',
                        'items' => [
                            [
                                'roles' => ['admin','manager','cashier'],
                                'href' => $basePath . '/pos.php',
                                'page' => 'pos.php',
                                'icon' => 'bi-cart-plus',
                                'label' => 'Retail POS',
                                'module' => 'pos'
                            ],
                            [
                                'roles' => ['admin','manager','cashier'],
                                'href' => $basePath . '/customers.php',
                                'page' => 'customers.php',
                                'icon' => 'bi-people',
                                'label' => 'Customers',
                                'module' => 'customers'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/notifications.php',
                                'page' => 'notifications.php',
                                'icon' => 'bi-bell',
                                'label' => 'Notifications',
                                'module' => 'customers'
                            ],
                            [
                                'roles' => ['super_admin','developer'],
                                'href' => $basePath . '/notification-usage.php',
                                'page' => 'notification-usage.php',
                                'icon' => 'bi-bar-chart-line',
                                'label' => 'Notification Billing',
                                'module' => 'customers'
                            ],
                            [
                                'roles' => ['admin','manager','cashier','accountant'],
                                'href' => $basePath . '/sales.php',
                                'page' => 'sales.php',
                                'icon' => 'bi-receipt',
                                'label' => 'Sales History',
                                'module' => 'sales'
                            ],
                            [
                                'roles' => ['admin','manager','cashier'],
                                'href' => $basePath . '/register-reports.php',
                                'page' => 'register-reports.php',
                                'icon' => 'bi-journal-check',
                                'label' => 'Register Reports',
                                'module' => 'sales'
                            ],
                            [
                                'roles' => ['admin','manager','owner'],
                                'href' => $basePath . '/register-analytics.php',
                                'page' => 'register-analytics.php',
                                'icon' => 'bi-graph-up',
                                'label' => 'Register Analytics',
                                'module' => 'sales'
                            ],
                            [
                                'roles' => ['admin','manager','owner'],
                                'href' => $basePath . '/location-analytics.php',
                                'page' => 'location-analytics.php',
                                'icon' => 'bi-geo-alt',
                                'label' => 'Location Analytics',
                                'module' => 'sales'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/manage-promotions.php',
                                'page' => 'manage-promotions.php',
                                'icon' => 'bi-stars',
                                'label' => 'Promotions',
                                'module' => 'sales'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/receipt-settings.php',
                                'page' => 'receipt-settings.php',
                                'icon' => 'bi-receipt-cutoff',
                                'label' => 'Receipt Settings',
                                'module' => 'sales'
                            ]
                        ]
                    ],
                    'restaurant' => [
                        'label' => 'Restaurant',
                        'items' => [
                            [
                                'roles' => ['admin','manager','waiter','cashier'],
                                'href' => $basePath . '/restaurant.php',
                                'page' => 'restaurant.php',
                                'icon' => 'bi-cup-hot',
                                'label' => 'Orders',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/kitchen-display.php',
                                'page' => 'kitchen-display.php',
                                'icon' => 'bi-fire',
                                'label' => 'Kitchen Display',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/manage-tables.php',
                                'page' => 'manage-tables.php',
                                'icon' => 'bi-grid-3x3',
                                'label' => 'Tables',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager','waiter'],
                                'href' => $basePath . '/restaurant-reservations.php',
                                'page' => 'restaurant-reservations.php',
                                'icon' => 'bi-calendar-event',
                                'label' => 'Reservations',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/manage-modifiers.php',
                                'page' => 'manage-modifiers.php',
                                'icon' => 'bi-sliders2',
                                'label' => 'Modifiers',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/void-order-management.php',
                                'page' => 'void-order-management.php',
                                'icon' => 'bi-x-circle',
                                'label' => 'Void Orders',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/void-reports.php',
                                'page' => 'void-reports.php',
                                'icon' => 'bi-file-bar-graph',
                                'label' => 'Void Reports',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin'],
                                'href' => $basePath . '/void-settings.php',
                                'page' => 'void-settings.php',
                                'icon' => 'bi-sliders',
                                'label' => 'Void Settings',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager','bartender','cashier'],
                                'href' => $basePath . '/bar-management.php',
                                'page' => 'bar-management.php',
                                'icon' => 'bi-cup-straw',
                                'label' => 'Bar & Beverage',
                                'module' => 'bar'
                            ],
                            [
                                'roles' => ['admin','manager','bartender','waiter','cashier'],
                                'href' => $basePath . '/bar-pos.php',
                                'page' => 'bar-pos.php',
                                'icon' => 'bi-wallet2',
                                'label' => 'Bar POS & Tabs',
                                'module' => 'bar'
                            ],
                            [
                                'roles' => ['admin','manager','bartender'],
                                'href' => $basePath . '/bar-kds.php',
                                'page' => 'bar-kds.php',
                                'icon' => 'bi-display',
                                'label' => 'Bar Display (KDS)',
                                'module' => 'bar'
                            ],
                            [
                                'roles' => ['admin','manager','bartender'],
                                'href' => $basePath . '/bartender-dashboard.php',
                                'page' => 'bartender-dashboard.php',
                                'icon' => 'bi-speedometer2',
                                'label' => 'Bartender Stats',
                                'module' => 'bar'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/happy-hour.php',
                                'page' => 'happy-hour.php',
                                'icon' => 'bi-clock-history',
                                'label' => 'Happy Hour',
                                'module' => 'bar'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/bar-floor-plan.php',
                                'page' => 'bar-floor-plan.php',
                                'icon' => 'bi-grid-3x3',
                                'label' => 'Floor Plan',
                                'module' => 'bar'
                            ],
                            [
                                'roles' => ['admin','manager','frontdesk','waiter','bartender'],
                                'href' => $basePath . '/qr-generator.php',
                                'page' => 'qr-generator.php',
                                'icon' => 'bi-qr-code',
                                'label' => 'QR Code Generator',
                                'module' => 'restaurant'
                            ],
                            [
                                'roles' => ['admin','manager','frontdesk'],
                                'href' => $basePath . '/guest-checkin.php',
                                'page' => 'guest-checkin.php',
                                'icon' => 'bi-door-open',
                                'label' => 'Guest Self Check-in',
                                'module' => 'rooms'
                            ]
                        ]
                    ],
                    'property' => [
                        'label' => 'Property',
                        'items' => [
                            [
                                'roles' => ['admin','manager','frontdesk'],
                                'href' => $basePath . '/rooms.php',
                                'page' => 'rooms.php',
                                'icon' => 'bi-calendar2-week',
                                'label' => 'Room Calendar',
                                'module' => 'rooms'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/manage-rooms.php',
                                'page' => 'manage-rooms.php',
                                'icon' => 'bi-door-open',
                                'label' => 'Manage Rooms',
                                'module' => 'rooms'
                            ],
                            [
                                'roles' => ['admin','manager','housekeeping_manager','housekeeping_staff','housekeeper','housekeeping','frontdesk'],
                                'href' => $basePath . '/housekeeping.php',
                                'page' => 'housekeeping.php',
                                'icon' => 'bi-stars',
                                'label' => 'Housekeeping Tasks',
                                'module' => 'housekeeping'
                            ],
                            [
                                'roles' => ['admin','manager','housekeeping_manager','housekeeping_staff'],
                                'href' => $basePath . '/housekeeping-inventory.php',
                                'page' => 'housekeeping-inventory.php',
                                'icon' => 'bi-box-seam',
                                'label' => 'HK Inventory',
                                'module' => 'housekeeping'
                            ],
                            [
                                'roles' => ['admin','manager','maintenance_manager','maintenance_staff','maintenance','technician','engineer','frontdesk'],
                                'href' => $basePath . '/maintenance.php',
                                'page' => 'maintenance.php',
                                'icon' => 'bi-tools',
                                'label' => 'Maintenance',
                                'module' => 'maintenance'
                            ],
                            [
                                'roles' => ['super_admin', 'developer', 'admin', 'manager', 'frontdesk'],
                                'href' => $basePath . '/guest-portal-settings.php',
                                'page' => 'guest-portal-settings.php',
                                'icon' => 'bi-shield-lock',
                                'label' => 'Guest Portal',
                                'module' => 'rooms'
                            ],
                            [
                                'roles' => ['super_admin', 'developer', 'admin', 'manager', 'frontdesk', 'receptionist', 'cashier'],
                                'href' => $basePath . '/whatsapp-inbox.php',
                                'page' => 'whatsapp-inbox.php',
                                'icon' => 'bi-whatsapp',
                                'label' => 'WhatsApp Inbox',
                                'module' => 'rooms'
                            ]
                        ]
                    ],
                    'delivery' => [
                        'label' => 'Delivery',
                        'items' => [
                            [
                                'roles' => ['admin','manager','rider'],
                                'href' => $basePath . '/delivery.php',
                                'page' => 'delivery.php',
                                'icon' => 'bi-truck',
                                'label' => 'Deliveries',
                                'module' => 'delivery'
                            ],
                            [
                                'roles' => ['admin','manager','rider'],
                                'href' => $basePath . '/enhanced-delivery-tracking.php',
                                'page' => 'enhanced-delivery-tracking.php',
                                'icon' => 'bi-geo-alt',
                                'label' => 'Tracking',
                                'module' => 'delivery'
                            ],
                            [
                                'roles' => ['admin','developer'],
                                'href' => $basePath . '/delivery-pricing.php',
                                'page' => 'delivery-pricing.php',
                                'icon' => 'bi-cash-coin',
                                'label' => 'Pricing Rules',
                                'module' => 'delivery'
                            ]
                        ]
                    ],
                    'inventory' => [
                        'label' => 'Inventory',
                        'items' => [
                            [
                                'roles' => ['admin','manager','inventory_manager','cashier'],
                                'href' => $basePath . '/products.php',
                                'page' => 'products.php',
                                'icon' => 'bi-box',
                                'label' => 'Products',
                                'module' => 'inventory'
                            ],
                            [
                                'roles' => ['admin','manager','inventory_manager'],
                                'href' => $basePath . '/inventory.php',
                                'page' => 'inventory.php',
                                'icon' => 'bi-boxes',
                                'label' => 'Stock Levels',
                                'module' => 'inventory'
                            ],
                            [
                                'roles' => ['admin','manager','inventory_manager'],
                                'href' => $basePath . '/goods-received.php',
                                'page' => 'goods-received.php',
                                'icon' => 'bi-box-arrow-in-down',
                                'label' => 'Goods Received',
                                'module' => 'inventory'
                            ]
                        ]
                    ],
                    'finance' => [
                        'label' => 'Finance',
                        'items' => [
                            [
                                'roles' => ['admin','manager','accountant'],
                                'href' => $basePath . '/accounting.php',
                                'page' => 'accounting.php',
                                'icon' => 'bi-calculator',
                                'label' => 'Accounting',
                                'module' => 'accounting'
                            ],
                            [
                                'roles' => ['admin','manager','accountant'],
                                'href' => $basePath . '/reports.php',
                                'page' => 'reports.php',
                                'icon' => 'bi-file-earmark-text',
                                'label' => 'Reports',
                                'module' => 'reports'
                            ],
                            [
                                'roles' => ['admin','accountant'],
                                'href' => $basePath . '/reports/profit-and-loss.php',
                                'page' => 'profit-and-loss.php',
                                'icon' => 'bi-journal-text',
                                'label' => 'Profit & Loss',
                                'module' => 'reports'
                            ],
                            [
                                'roles' => ['admin','accountant'],
                                'href' => $basePath . '/reports/balance-sheet.php',
                                'page' => 'balance-sheet.php',
                                'icon' => 'bi-clipboard-data',
                                'label' => 'Balance Sheet',
                                'module' => 'reports'
                            ],
                            [
                                'roles' => ['admin','accountant'],
                                'href' => $basePath . '/reports/sales-tax-report.php',
                                'page' => 'sales-tax-report.php',
                                'icon' => 'bi-file-spreadsheet',
                                'label' => 'Tax Report',
                                'module' => 'reports'
                            ]
                        ]
                    ],
                    'admin' => [
                        'label' => 'Administration',
                        'items' => [
                            [
                                'roles' => ['admin'],
                                'href' => $basePath . '/users.php',
                                'page' => 'users.php',
                                'icon' => 'bi-person-gear',
                                'label' => 'Users',
                                'module' => 'users'
                            ],
                            [
                                'roles' => ['admin','manager','cashier','waiter','bartender','housekeeping_staff','maintenance_staff','frontdesk'],
                                'href' => $basePath . '/time-clock.php',
                                'page' => 'time-clock.php',
                                'icon' => 'bi-clock-history',
                                'label' => 'Time Clock',
                                'module' => 'users'
                            ],
                            [
                                'roles' => ['admin'],
                                'href' => $basePath . '/permissions.php',
                                'page' => 'permissions.php',
                                'icon' => 'bi-shield-lock',
                                'label' => 'Permissions',
                                'module' => 'users'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/locations.php',
                                'page' => 'locations.php',
                                'icon' => 'bi-geo-alt',
                                'label' => 'Locations',
                                'module' => 'locations'
                            ],
                            [
                                'roles' => ['admin','manager'],
                                'href' => $basePath . '/registers.php',
                                'page' => 'registers.php',
                                'icon' => 'bi-cash-stack',
                                'label' => 'Registers/Tills',
                                'module' => 'sales'
                            ],
                            [
                                'roles' => ['developer', 'super_admin'],
                                'href' => $basePath . '/system-health.php',
                                'page' => 'system-health.php',
                                'icon' => 'bi-heart-pulse',
                                'label' => 'System Health',
                                'module' => 'settings'
                            ],
                            [
                                'roles' => ['developer', 'super_admin', 'admin'],
                                'href' => $basePath . '/system-logs.php',
                                'page' => 'system-logs.php',
                                'icon' => 'bi-journal-text',
                                'label' => 'System Logs',
                                'module' => 'settings'
                            ],
                            [
                                'roles' => ['super_admin', 'admin', 'developer'],
                                'href' => $basePath . '/module-manager.php',
                                'page' => 'module-manager.php',
                                'icon' => 'bi-toggle2-on',
                                'label' => 'Module Manager',
                                'module' => 'settings'
                            ],
                            [
                                'roles' => ['admin', 'developer'],
                                'href' => $basePath . '/settings.php',
                                'page' => 'settings.php',
                                'icon' => 'bi-gear',
                                'label' => 'Settings',
                                'module' => 'settings'
                            ],
                            [
                                'roles' => ['admin'],
                                'href' => $basePath . '/currency-settings.php',
                                'page' => 'currency-settings.php',
                                'icon' => 'bi-currency-exchange',
                                'label' => 'Currency',
                                'module' => 'settings'
                            ],
                            [
                                'roles' => ['super_admin', 'developer'],
                                'href' => $basePath . '/payment-gateways.php',
                                'page' => 'payment-gateways.php',
                                'icon' => 'bi-credit-card',
                                'label' => 'Payment Gateways'
                            ],
                            [
                                'roles' => ['super_admin', 'developer'],
                                'href' => $basePath . '/site-editor.php',
                                'page' => 'site-editor.php',
                                'icon' => 'bi-pencil-square',
                                'label' => 'Site Editor'
                            ]
                        ]
                    ]
                ];

                foreach ($navGroups as $groupKey => $group) {
                    if ($isPrivileged) {
                        $visibleItems = array_map(function ($item) {
                            $item['hidden'] = false;
                            return $item;
                        }, $group['items']);
                    } else {
                        $visibleItems = array_filter($group['items'], function ($item) use ($userRole, $systemManager) {
                            if ($item['roles'] === 'all') {
                                $roleAllowed = true;
                            } else {
                                $roleAllowed = in_array($userRole, $item['roles'], true);
                            }

                            if (empty($item['module'])) {
                                $moduleAllowed = true;
                            } else {
                                $moduleAllowed = $systemManager->isModuleEnabled($item['module']);
                            }

                            return $roleAllowed && $moduleAllowed;
                        });
                    }

                    if (empty($visibleItems)) {
                        continue;
                    }

                    $isGroupActive = $isPrivileged;
                    if (!$isGroupActive) {
                        foreach ($visibleItems as $item) {
                            if ($item['page'] === $currentPage) {
                                $isGroupActive = true;
                                break;
                            }
                        }
                    }

                    $groupId = 'nav-group-' . preg_replace('/[^a-z0-9\-]/i', '-', $groupKey);
            ?>
            <div class="nav-group <?= $isGroupActive ? 'open' : '' ?>" data-group-id="<?= htmlspecialchars($groupId) ?>">
                <button class="nav-group-toggle" type="button" aria-expanded="<?= $isGroupActive ? 'true' : 'false' ?>" data-target="#<?= htmlspecialchars($groupId) ?>">
                    <i class="bi bi-chevron-down"></i>
                    <span><?= htmlspecialchars($group['label']) ?></span>
                </button>
                <div class="nav-group-items" id="<?= htmlspecialchars($groupId) ?>">
                    <?php foreach ($visibleItems as $item): ?>
                        <a class="nav-link<?= $item['page'] === $currentPage ? ' active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                            <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="nav-badge"><?= htmlspecialchars($item['badge']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php } ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="top-bar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-primary sidebar-toggle-btn" type="button" id="sidebarToggleBtn" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
                <div class="top-bar-title">
                    <h4 class="mb-0"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></h4>
                    <small class="text-muted"><?= date('l, F j, Y') ?></small>
                </div>
            </div>
            <div class="top-bar-actions">
                <div class="top-bar-sync d-flex align-items-center gap-2 flex-wrap me-0">
                    <span id="online-status" class="badge bg-secondary d-flex align-items-center gap-1">
                        <i class="bi bi-wifi-off"></i>
                        <span>Offline</span>
                    </span>
                    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center" type="button" id="sync-button" onclick="if(window.offlineManager) offlineManager.forceSyncAll(); else alert('Sync manager not ready');" title="Sync pending transactions">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Sync
                        <span id="pending-count" class="badge bg-danger ms-2" style="display: none;">0</span>
                    </button>
                    <button class="btn btn-sm btn-outline-warning d-flex align-items-center" type="button" onclick="if(window.offlineManager) offlineManager.showOfflineQueue(); else alert('Offline manager not ready');" title="View offline queue">
                        <i class="bi bi-cloud-arrow-up"></i>
                    </button>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($auth->getUser()['username'] ?? 'User') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= $basePath ?>/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= $basePath ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="p-4">
<?php else: ?>
    <div class="container-fluid">
<?php endif; ?>
