<?php
/**
 * WAPOS - Professional Landing Page
 * Welcome page with login access
 */

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'includes/bootstrap.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    $role = strtolower($auth->getUser()['role'] ?? '');

    switch ($role) {
        case 'super_admin':
        case 'developer':
        case 'admin':
            redirect('dashboards/admin.php');
            break;
        case 'manager':
            redirect('dashboards/manager.php');
            break;
        case 'accountant':
            redirect('dashboards/accountant.php');
            break;
        case 'cashier':
            redirect('dashboards/cashier.php');
            break;
        case 'waiter':
            redirect('dashboards/waiter.php');
            break;
        default:
            redirect('pos.php');
    }
}

$pageTitle = 'Welcome to WAPOS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --brand-deep: #0f172a;
            --brand-primary: #2563eb;
            --brand-accent: #22d3ee;
            --brand-muted: #64748b;
            --surface: #ffffff;
            --surface-muted: #f5f7fb;
            --border-soft: #e2e8f0;
            --radius-xl: 26px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: radial-gradient(circle at top, rgba(37,99,235,0.15), transparent 60%), var(--surface-muted);
            color: var(--brand-deep);
            min-height: 100vh;
        }

        .page-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .landing-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: rgba(255,255,255,0.85);
            border-radius: 999px;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(226,232,240,0.7);
            margin-bottom: 32px;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.4rem;
        }

        .nav-actions {
            display: flex;
            gap: 12px;
        }

        .btn-outline,
        .btn-primary {
            border-radius: 999px;
            padding: 10px 20px;
            font-weight: 600;
            border: 1px solid var(--border-soft);
            background: #fff;
            color: var(--brand-deep);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(120deg, var(--brand-primary), #1d4ed8);
            border: none;
            color: #fff;
            box-shadow: 0 12px 30px rgba(37,99,235,0.3);
        }

        .hero-panel {
            background: var(--surface);
            border-radius: var(--radius-xl);
            padding: 48px;
            box-shadow: 0 30px 80px rgba(15,23,42,0.08);
            margin-bottom: 32px;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
        }

        .hero-copy h1 {
            font-size: clamp(2.4rem, 4vw, 3.5rem);
            margin: 0 0 16px;
            line-height: 1.1;
        }

        .hero-copy p {
            font-size: 1.08rem;
            color: var(--brand-muted);
            margin-bottom: 24px;
        }

        .hero-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .pill {
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(37,99,235,0.12);
            color: var(--brand-primary);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .hero-visual {
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(34,211,238,0.12));
            border: 1px solid rgba(148,163,184,0.3);
            padding: 32px;
            position: relative;
            overflow: hidden;
        }

        .hero-visual::after,
        .hero-visual::before {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(37,99,235,0.18);
            filter: blur(20px);
        }

        .hero-visual::before {
            width: 180px;
            height: 180px;
            top: -40px;
            right: -20px;
        }

        .hero-visual::after {
            width: 120px;
            height: 120px;
            bottom: -20px;
            left: -30px;
        }

        .chart-card {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: inset 0 0 0 1px rgba(148,163,184,0.2);
            position: relative;
            z-index: 2;
        }

        .chart-bar {
            height: 10px;
            border-radius: 999px;
            background: rgba(148,163,184,0.4);
            overflow: hidden;
            margin-bottom: 14px;
        }

        .chart-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--brand-primary), var(--brand-accent));
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 40px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid rgba(148,163,184,0.25);
        }

        .stat-card h4 {
            margin: 0;
            font-size: 2rem;
        }

        .stat-card span {
            color: var(--brand-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .section-heading {
            margin: 48px 0 20px;
        }

        .section-heading p {
            color: var(--brand-muted);
            margin: 4px 0 0;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            border: 1px solid rgba(148,163,184,0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(15,23,42,0.12);
        }

        .feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(37,99,235,0.12);
            color: var(--brand-primary);
            display: grid;
            place-items: center;
            font-size: 1.4rem;
        }

        .cta-panel {
            margin-top: 56px;
            border-radius: 24px;
            padding: 40px 32px;
            background: linear-gradient(120deg, var(--brand-primary), #0ea5e9);
            color: #fff;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .cta-panel p {
            margin: 0;
            color: rgba(255,255,255,0.85);
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: var(--brand-muted);
        }

        @media (max-width: 768px) {
            .landing-nav {
                flex-direction: column;
                gap: 12px;
            }

            .hero-panel {
                padding: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="landing-nav">
            <div class="brand-mark">
                <div class="brand-icon"><i class="bi bi-shop"></i></div>
                <span>WAPOS Suite</span>
            </div>
            <div class="nav-actions">
                <a class="btn-outline" href="diagnostics.php"><i class="bi bi-activity"></i> Diagnostics</a>
                <a class="btn-primary" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
            </div>
        </header>

        <section class="hero-panel">
            <div class="hero-grid">
                <div class="hero-copy">
                    <div class="hero-pills">
                        <span class="pill"><i class="bi bi-stars"></i>Omni-channel POS</span>
                        <span class="pill"><i class="bi bi-shield-lock"></i>Role-aware Access</span>
                    </div>
                    <h1>Operate retail, restaurant, and hospitality workflows from one console.</h1>
                    <p>WAPOS orchestrates inventory, payments, delivery logistics, and finance in real time. Configurable modules keep every team aligned—sales, kitchen, riders, and accounting.</p>
                    <div class="nav-actions">
                        <a class="btn-primary" href="login.php"><i class="bi bi-play-circle"></i>Launch Workspace</a>
                        <a class="btn-outline" href="settings.php"><i class="bi bi-gear"></i>Review Settings</a>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <small class="text-uppercase text-muted">Today's Signal</small>
                                <h5 class="mb-0">Operational Pulse</h5>
                            </div>
                            <span class="badge bg-light text-dark">Live</span>
                        </div>
                        <div class="chart-bar"><span style="width: 82%"></span></div>
                        <div class="chart-bar"><span style="width: 64%"></span></div>
                        <div class="chart-bar"><span style="width: 92%"></span></div>
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="d-flex justify-content-between"><span>POS & Reservations</span><strong>+18% vs last week</strong></li>
                            <li class="d-flex justify-content-between"><span>Deliveries in SLA</span><strong>96% on-time</strong></li>
                            <li class="d-flex justify-content-between"><span>Accounting checks</span><strong>No exceptions</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <h4>15</h4>
                    <span>Active Modules</span>
                </div>
                <div class="stat-card">
                    <h4>24/7</h4>
                    <span>Monitoring</span>
                </div>
                <div class="stat-card">
                    <h4>5m+</h4>
                    <span>Transactions</span>
                </div>
                <div class="stat-card">
                    <h4>120+</h4>
                    <span>Roles & Permissions</span>
                </div>
            </div>
        </section>

        <section>
            <div class="section-heading">
                <h2>Everything operators need, from front-of-house to finance.</h2>
                <p>Deploy the same workspace for retail counters, dining rooms, delivery teams, and corporate controllers.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-cart-check"></i></div>
                    <h4>Unified Point of Sale</h4>
                    <p>Lightning-fast basket building, loyalty capture, held orders, and omni tendering built in.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-columns-gap"></i></div>
                    <h4>Role-focused Dashboards</h4>
                    <p>Managers, accountants, riders, and super admins see only what they need—with contextual guardrails.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-truck"></i></div>
                    <h4>Delivery & Logistics</h4>
                    <p>Live rider tracking, SLA alerts, and Google Distance Matrix integrations keep field ops accountable.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-calculator"></i></div>
                    <h4>Accounting-ready</h4>
                    <p>Auto-locked journals, tax packs, and ledger exports simplify handoffs to finance systems.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                    <h4>Security & Audit</h4>
                    <p>Module toggles, privilege bypass for super admins, and compliance logs baked into every action.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-phone"></i></div>
                    <h4>Responsive & Offline-aware</h4>
                    <p>PWA-ready experience keeps service moving on tablets and touch devices.</p>
                </div>
            </div>
        </section>

        <section class="cta-panel">
            <div>
                <h3 class="mb-1">Ready to continue operations?</h3>
                <p>Sign in with your assigned role to access dashboards, modules, and live diagnostics.</p>
            </div>
            <a class="btn btn-light btn-lg" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i>Login to Workspace</a>
        </section>

        <footer class="footer">
            <small>&copy; <?= date('Y') ?> WAPOS • Unified commerce & hospitality control center.</small>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
