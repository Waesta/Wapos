<?php
/**
 * WAPOS - Unified Point of Sale System
 * Professional Landing Page - Powered by Waesta Enterprises U Ltd
 * 
 * @copyright <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.
 * @link https://waesta.com
 */

// Prevent caching for dynamic content
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'includes/bootstrap.php';
require_once 'app/Services/ContentManager.php';

use App\Services\ContentManager;

// If already logged in, redirect to appropriate dashboard
if ($auth->isLoggedIn()) {
    redirectToDashboard($auth);
}

// Get editable content
$cm = ContentManager::getInstance();

// SEO Meta Data (editable)
$pageTitle = $cm->get('seo_title', 'WAPOS - Unified Point of Sale System | Retail, Restaurant & Hospitality');
$pageDescription = $cm->get('seo_description', 'WAPOS is a comprehensive point of sale system for retail, restaurant, and hospitality businesses. Manage sales, inventory, deliveries, and accounting from one unified platform.');
$pageKeywords = $cm->get('seo_keywords', 'POS system, point of sale, retail POS, restaurant POS, hospitality management, inventory management, sales tracking, business software');
$canonicalUrl = rtrim(APP_URL, '/');

// Editable content
$companyName = $cm->get('company_name', 'WAPOS');
$companyTagline = $cm->get('company_tagline', 'by Waesta Enterprises');
$companyFullName = $cm->get('company_full_name', 'Waesta Enterprises U Ltd');
$heroTitle = $cm->get('home_hero_title', 'Complete Business Management System');
$heroSubtitle = $cm->get('home_hero_subtitle', 'Point of Sale, Restaurant Operations, Inventory, Deliveries, Housekeeping, Maintenance, and Accounting — all in one unified platform.');
$ctaButton = $cm->get('home_cta_button', 'Sign In to Dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>">
    <meta name="author" content="Waesta Enterprises U Ltd">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:site_name" content="WAPOS">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= APP_URL ?>/assets/favicon.ico">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/apple-touch-icon.png">
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Structured Data (JSON-LD) for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "WAPOS",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web Browser",
        "description": "<?= htmlspecialchars($pageDescription) ?>",
        "url": "<?= htmlspecialchars($canonicalUrl) ?>",
        "author": {
            "@type": "Organization",
            "name": "Waesta Enterprises U Ltd",
            "url": "https://waesta.com"
        },
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        }
    }
    </script>
    
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

        .brand-name {
            display: block;
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.2;
        }

        .brand-tagline {
            display: block;
            font-size: 0.7rem;
            color: var(--brand-muted);
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link {
            padding: 8px 14px;
            color: var(--brand-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 6px;
            transition: color 0.2s, background 0.2s;
        }

        .nav-link:hover {
            color: var(--brand-deep);
            background: rgba(0,0,0,0.04);
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
            margin-top: 48px;
            padding: 40px 0 24px;
            border-top: 1px solid var(--border-soft);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
            margin-bottom: 24px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--brand-deep);
        }

        .footer-logo i {
            color: var(--brand-primary);
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
        }

        .footer-links a {
            color: var(--brand-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--brand-primary);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid var(--border-soft);
        }

        .footer-bottom p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--brand-muted);
        }

        .footer-bottom strong {
            color: var(--brand-deep);
        }

        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-content h1 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            margin: 0 0 20px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--brand-muted);
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .btn-lg {
            padding: 16px 32px;
            font-size: 1.1rem;
        }

        /* Modules Section */
        .modules-section {
            padding: 48px 0 32px;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .module-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 28px;
            border: 1px solid var(--border-soft);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(15,23,42,0.1);
        }

        .module-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 1.3rem;
            margin-bottom: 16px;
        }

        .module-card h3 {
            font-size: 1.15rem;
            margin: 0 0 12px;
            color: var(--brand-deep);
        }

        .module-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .module-features li {
            padding: 6px 0;
            font-size: 0.9rem;
            color: var(--brand-muted);
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .module-features li:last-child {
            border-bottom: none;
        }

        .module-features li::before {
            content: "✓";
            color: var(--brand-primary);
            font-weight: 600;
            font-size: 0.8rem;
        }

        /* Capabilities Section */
        .capabilities-section {
            padding: 32px 0 48px;
        }

        .capabilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }

        .capability {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px;
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border-soft);
        }

        .capability i {
            font-size: 1.5rem;
            color: var(--brand-primary);
            flex-shrink: 0;
        }

        .capability strong {
            display: block;
            font-size: 0.95rem;
            color: var(--brand-deep);
            margin-bottom: 4px;
        }

        .capability span {
            font-size: 0.85rem;
            color: var(--brand-muted);
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .landing-nav {
                flex-direction: column;
                gap: 12px;
            }

            .nav-actions {
                flex-wrap: wrap;
                justify-content: center;
            }

            .hero-panel {
                padding: 32px 20px;
            }

            .module-grid {
                grid-template-columns: 1fr;
            }

            .capabilities-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .footer-links {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="landing-nav" role="banner">
            <div class="brand-mark">
                <div class="brand-icon"><i class="bi bi-shop"></i></div>
                <div>
                    <span class="brand-name"><?= htmlspecialchars($companyName) ?></span>
                    <small class="brand-tagline"><?= htmlspecialchars($companyTagline) ?></small>
                </div>
            </div>
            <nav class="nav-actions" role="navigation" aria-label="Main navigation">
                <a class="nav-link" href="about.php">About</a>
                <a class="nav-link" href="resources.php">User Manual</a>
                <a class="nav-link" href="guest-portal.php">Guest Portal</a>
                <a class="nav-link" href="rider-login.php"><i class="bi bi-bicycle"></i> Riders</a>
                <a class="nav-link" href="contact.php">Contact</a>
                <a class="btn-primary" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
            </nav>
        </header>

        <main>
        <!-- Hero Section -->
        <section class="hero-panel" aria-labelledby="hero-heading">
            <div class="hero-content">
                <h1 id="hero-heading"><?= htmlspecialchars($heroTitle) ?></h1>
                <p class="hero-subtitle"><?= htmlspecialchars($heroSubtitle) ?></p>
                <a class="btn-primary btn-lg" href="login.php"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= htmlspecialchars($ctaButton) ?></a>
            </div>
        </section>

        <!-- Core Modules -->
        <section id="features" class="modules-section" aria-labelledby="features-heading">
            <div class="section-heading">
                <h2 id="features-heading">System Modules</h2>
            </div>
            
            <div class="module-grid">
                <!-- Point of Sale -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-cart-check" aria-hidden="true"></i></div>
                    <h3>Point of Sale</h3>
                    <ul class="module-features">
                        <li>Fast checkout with barcode scanning</li>
                        <li>M-Pesa STK Push & mobile money payments</li>
                        <li>Cash, Card, Split & multi-payment support</li>
                        <li>Customer loyalty & promotions</li>
                        <li>Held orders & order management</li>
                        <li>Receipt printing & email</li>
                    </ul>
                </article>

                <!-- Restaurant -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-egg-fried" aria-hidden="true"></i></div>
                    <h3>Restaurant</h3>
                    <ul class="module-features">
                        <li>Table management & floor plan</li>
                        <li>Dine-in, takeout & delivery orders</li>
                        <li>Kitchen Display System (KDS)</li>
                        <li>Menu & modifier management</li>
                        <li>Reservations & waitlist</li>
                        <li>Split billing & item transfers</li>
                        <li>Digital menu with QR codes</li>
                        <li>Recipe-based inventory deduction</li>
                    </ul>
                </article>

                <!-- Inventory -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-boxes" aria-hidden="true"></i></div>
                    <h3>Inventory</h3>
                    <ul class="module-features">
                        <li>Product catalog with categories</li>
                        <li>Stock level tracking</li>
                        <li>Low stock alerts</li>
                        <li>Goods received notes (GRN)</li>
                        <li>Supplier management</li>
                        <li>Stock adjustments & transfers</li>
                    </ul>
                </article>

                <!-- Delivery -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-truck" aria-hidden="true"></i></div>
                    <h3>Delivery</h3>
                    <ul class="module-features">
                        <li>Order dispatch & assignment</li>
                        <li>Live rider GPS tracking</li>
                        <li>Delivery status updates</li>
                        <li>Distance-based pricing rules</li>
                        <li>Rider performance metrics</li>
                        <li>Delivery zones management</li>
                    </ul>
                </article>

                <!-- Bar & Beverage -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(23,162,184,0.12); color: #17a2b8;"><i class="bi bi-cup-straw" aria-hidden="true"></i></div>
                    <h3>Bar & Beverage</h3>
                    <ul class="module-features">
                        <li>Dedicated Bar POS with tabs</li>
                        <li>Portion-based sales (tots, shots, glasses)</li>
                        <li>Cocktail recipe management</li>
                        <li>Open bottle tracking & pour logging</li>
                        <li>Bar KDS for drink orders</li>
                        <li>Happy hour pricing rules</li>
                        <li>Yield & variance reports</li>
                        <li>Floor plan with table status</li>
                    </ul>
                </article>

                <!-- Housekeeping -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-house-check" aria-hidden="true"></i></div>
                    <h3>Housekeeping</h3>
                    <ul class="module-features">
                        <li>Room status board</li>
                        <li>Task assignment & tracking</li>
                        <li>Linen & laundry tracking</li>
                        <li>Minibar consumption logging</li>
                        <li>Supplies inventory</li>
                        <li>Real-time status updates</li>
                    </ul>
                </article>

                <!-- Maintenance -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-tools" aria-hidden="true"></i></div>
                    <h3>Maintenance</h3>
                    <ul class="module-features">
                        <li>Work order management</li>
                        <li>Request submission & tracking</li>
                        <li>Priority-based scheduling</li>
                        <li>Technician assignment</li>
                        <li>Asset maintenance history</li>
                        <li>Completion reporting</li>
                    </ul>
                </article>

                <!-- Accounting -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-calculator" aria-hidden="true"></i></div>
                    <h3>Accounting</h3>
                    <ul class="module-features">
                        <li>Chart of accounts</li>
                        <li>Journal entries & ledgers</li>
                        <li>Profit & Loss statements</li>
                        <li>Balance sheet reports</li>
                        <li>Sales tax reporting</li>
                        <li>Payment reconciliation</li>
                    </ul>
                </article>

                <!-- Payment Gateways -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-credit-card" aria-hidden="true"></i></div>
                    <h3>Payment Gateways</h3>
                    <ul class="module-features">
                        <li>M-Pesa STK Push (Daraja API)</li>
                        <li>M-Pesa Paybill & Till integration</li>
                        <li>Airtel Money (KE, UG, RW, TZ)</li>
                        <li>MTN Mobile Money (UG, RW)</li>
                        <li>Card payments (Visa/Mastercard)</li>
                        <li>PesaPal multi-method gateway</li>
                    </ul>
                </article>

                <!-- Notifications & Marketing -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(37,211,102,0.12); color: #25D366;"><i class="bi bi-bell" aria-hidden="true"></i></div>
                    <h3>Notifications & Marketing</h3>
                    <ul class="module-features">
                        <li>SMS via EgoSMS, SMSLeopard, Africa's Talking</li>
                        <li>WhatsApp via AiSensy or Meta API</li>
                        <li>Email campaigns & templates</li>
                        <li>Birthday wishes & thank you messages</li>
                        <li>Marketing campaigns & segmentation</li>
                        <li>Usage billing for resellers</li>
                    </ul>
                </article>

                <!-- Register & Till Management -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(16,185,129,0.12); color: #10b981;"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
                    <h3>Register & Till Management</h3>
                    <ul class="module-features">
                        <li>Multi-register support per location</li>
                        <li>Cash drawer tracking & reconciliation</li>
                        <li>X, Y, Z register reports</li>
                        <li>Session-based cash management</li>
                        <li>Register performance analytics</li>
                        <li>Variance tracking & approval</li>
                    </ul>
                </article>

                <!-- Location Analytics -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;"><i class="bi bi-geo-alt" aria-hidden="true"></i></div>
                    <h3>Location Analytics</h3>
                    <ul class="module-features">
                        <li>Multi-location performance comparison</li>
                        <li>Revenue & transaction trends</li>
                        <li>Staff performance by location</li>
                        <li>Top products per location</li>
                        <li>Payment method breakdown</li>
                        <li>Month-over-month growth tracking</li>
                    </ul>
                </article>

                <!-- Administration -->
                <article class="module-card">
                    <div class="module-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
                    <h3>Administration</h3>
                    <ul class="module-features">
                        <li>User & role management</li>
                        <li>Granular permissions</li>
                        <li>Multi-location support</li>
                        <li>System settings & configuration</li>
                        <li>Audit logs & activity tracking</li>
                        <li>Data backup & restore</li>
                    </ul>
                </article>

                <!-- Property Management -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(139,92,246,0.12); color: #8b5cf6;"><i class="bi bi-building" aria-hidden="true"></i></div>
                    <h3>Property Management</h3>
                    <ul class="module-features">
                        <li>Room booking & reservations</li>
                        <li>Guest check-in/out with QR codes</li>
                        <li>Room service ordering</li>
                        <li>Folio & billing management</li>
                        <li>Guest self-service portal</li>
                        <li>Occupancy reports</li>
                    </ul>
                </article>

                <!-- Employee Management -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(236,72,153,0.12); color: #ec4899;"><i class="bi bi-person-badge" aria-hidden="true"></i></div>
                    <h3>Employee Management</h3>
                    <ul class="module-features">
                        <li>Time clock with PIN entry</li>
                        <li>Shift scheduling</li>
                        <li>Attendance tracking</li>
                        <li>Overtime calculations</li>
                        <li>Timesheet reports</li>
                        <li>Role-based access control</li>
                    </ul>
                </article>

                <!-- QR Code & Digital Tools -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(20,184,166,0.12); color: #14b8a6;"><i class="bi bi-qr-code" aria-hidden="true"></i></div>
                    <h3>QR Code & Digital Tools</h3>
                    <ul class="module-features">
                        <li>Digital menu QR codes</li>
                        <li>Table ordering via QR</li>
                        <li>Guest self check-in QR</li>
                        <li>Loyalty card QR codes</li>
                        <li>Receipt & feedback QR</li>
                        <li>Barcode scanner support</li>
                    </ul>
                </article>

                <!-- Events & Banquet Management -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;"><i class="bi bi-calendar-event" aria-hidden="true"></i></div>
                    <h3>Events & Banquet Management</h3>
                    <ul class="module-features">
                        <li>Venue management & booking</li>
                        <li>Event type categorization</li>
                        <li>Customer & guest tracking</li>
                        <li>Service add-ons & packages</li>
                        <li>Payment tracking with accounting</li>
                        <li>Contract & document management</li>
                        <li>Event coordinator assignment</li>
                        <li>Setup requirements & checklists</li>
                    </ul>
                </article>

                <!-- Security Management -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(220,38,38,0.12); color: #dc2626;"><i class="bi bi-shield-check" aria-hidden="true"></i></div>
                    <h3>Security Management</h3>
                    <ul class="module-features">
                        <li>Personnel & guard management</li>
                        <li>Shift scheduling & posts</li>
                        <li>Patrol route tracking</li>
                        <li>Incident reporting & logging</li>
                        <li>Visitor entry/exit logs</li>
                        <li>Equipment tracking</li>
                        <li>Training records</li>
                        <li>Handover notes & briefings</li>
                    </ul>
                </article>

                <!-- HR & Employee Management -->
                <article class="module-card">
                    <div class="module-icon" style="background: rgba(168,85,247,0.12); color: #a855f7;"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
                    <h3>HR & Employee Management</h3>
                    <ul class="module-features">
                        <li>Department & position management</li>
                        <li>Employee records & documents</li>
                        <li>Payroll processing with accounting</li>
                        <li>Leave management & approvals</li>
                        <li>Performance reviews & cycles</li>
                        <li>Disciplinary actions tracking</li>
                        <li>Training & development records</li>
                        <li>Benefits & salary history</li>
                    </ul>
                </article>
            </div>
        </section>

        <!-- Key Capabilities -->
        <section class="capabilities-section" aria-labelledby="capabilities-heading">
            <div class="section-heading">
                <h2 id="capabilities-heading">Key Capabilities</h2>
            </div>
            <div class="capabilities-grid">
                <div class="capability">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <div>
                        <strong>Role-Based Dashboards</strong>
                        <span>Admin, Manager, Cashier, Waiter, Rider, Accountant, Housekeeper</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-currency-exchange" aria-hidden="true"></i>
                    <div>
                        <strong>Currency Neutral</strong>
                        <span>Works with any currency — configure symbol, format & position</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-laptop" aria-hidden="true"></i>
                    <div>
                        <strong>Responsive Design</strong>
                        <span>Works on desktop, tablet & mobile devices</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-credit-card-2-front" aria-hidden="true"></i>
                    <div>
                        <strong>Mobile Payments</strong>
                        <span>M-Pesa STK Push, Airtel Money, MTN MoMo & Card payments</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-graph-up" aria-hidden="true"></i>
                    <div>
                        <strong>Real-Time Reports</strong>
                        <span>Sales, inventory, payments & performance analytics</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-cloud-check" aria-hidden="true"></i>
                    <div>
                        <strong>Cloud Ready</strong>
                        <span>Deploy on cPanel, AWS, DigitalOcean or any LAMP server</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <div>
                        <strong>Secure & Compliant</strong>
                        <span>GDPR ready, rate limiting, audit trails & encrypted sessions</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-chat-dots" aria-hidden="true"></i>
                    <div>
                        <strong>Multi-Channel Notifications</strong>
                        <span>SMS, WhatsApp, Email — EgoSMS, SMSLeopard, AiSensy & more</span>
                    </div>
                </div>
                <div class="capability">
                    <i class="bi bi-cash-stack" aria-hidden="true"></i>
                    <div>
                        <strong>Register Management</strong>
                        <span>Multi-register, session tracking, X/Y/Z reports & variance control</span>
                    </div>
                </div>
            </div>
        </section>
        </main>

        <footer class="footer" role="contentinfo">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <i class="bi bi-shop"></i>
                        <span><?= htmlspecialchars($companyName) ?></span>
                    </div>
                </div>
                <div class="footer-links">
                    <a href="about.php">About</a>
                    <a href="resources.php">User Manual</a>
                    <a href="contact.php">Contact</a>
                    <a href="privacy-policy.php">Privacy Policy</a>
                    <a href="terms-of-service.php">Terms of Service</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($companyFullName) ?></strong>. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
