<?php
/**
 * WAPOS - About Page
 * Product information and company details
 * 
 * @copyright <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

$pageTitle = 'About WAPOS - Unified Point of Sale System';
$pageDescription = 'Learn about WAPOS, the comprehensive business management system developed by Waesta Enterprises U Ltd for retail, restaurant, and hospitality businesses.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="robots" content="index, follow">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-deep: #0f172a;
            --brand-primary: #2563eb;
            --brand-accent: #22d3ee;
            --brand-muted: #64748b;
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --border-soft: #e2e8f0;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--surface-muted);
            color: var(--brand-deep);
            line-height: 1.7;
            margin: 0;
        }

        .page-nav {
            background: var(--surface);
            padding: 16px 0;
            border-bottom: 1px solid var(--border-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-nav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--brand-deep);
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.2rem;
        }

        .brand-name {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .nav-links {
            display: flex;
            gap: 8px;
        }

        .nav-links a {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--brand-muted);
            transition: all 0.2s;
        }

        .nav-links a:hover {
            background: var(--surface-muted);
            color: var(--brand-deep);
        }

        .nav-links a.btn-primary {
            background: var(--brand-primary);
            color: #fff;
        }

        .nav-links a.btn-primary:hover {
            background: #1d4ed8;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            color: #fff;
            padding: 80px 0;
            text-align: center;
        }

        .hero-section h1 {
            font-size: 2.5rem;
            margin: 0 0 16px;
        }

        .hero-section p {
            font-size: 1.15rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .content-section {
            padding: 64px 0;
        }

        .content-section:nth-child(even) {
            background: var(--surface);
        }

        .section-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-header h2 {
            font-size: 1.8rem;
            margin: 0 0 12px;
        }

        .section-header p {
            color: var(--brand-muted);
            font-size: 1.05rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .about-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 32px;
        }

        .about-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 32px;
            border: 1px solid var(--border-soft);
        }

        .content-section:nth-child(even) .about-card {
            background: var(--surface-muted);
        }

        .about-card h3 {
            font-size: 1.2rem;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .about-card h3 i {
            color: var(--brand-primary);
            font-size: 1.4rem;
        }

        .about-card p {
            color: var(--brand-muted);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 2.5rem;
            color: var(--brand-primary);
            margin: 0;
        }

        .stat-item p {
            color: var(--brand-muted);
            margin: 8px 0 0;
            font-size: 0.95rem;
        }

        .company-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .company-info {
                grid-template-columns: 1fr;
            }
        }

        .company-logo {
            text-align: center;
        }

        .company-logo .logo-box {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            border-radius: 24px;
            display: inline-grid;
            place-items: center;
            color: #fff;
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .company-logo h3 {
            font-size: 1.5rem;
            margin: 0 0 4px;
        }

        .company-logo p {
            color: var(--brand-muted);
            margin: 0;
        }

        .company-details h3 {
            font-size: 1.3rem;
            margin: 0 0 16px;
        }

        .company-details p {
            color: var(--brand-muted);
            margin: 0 0 16px;
        }

        .company-details ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .company-details li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--brand-muted);
        }

        .company-details li i {
            color: var(--brand-primary);
            width: 20px;
        }

        .timeline {
            max-width: 600px;
            margin: 0 auto;
        }

        .timeline-item {
            display: flex;
            gap: 24px;
            padding-bottom: 32px;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 15px;
            top: 40px;
            bottom: 0;
            width: 2px;
            background: var(--border-soft);
        }

        .timeline-year {
            width: 32px;
            height: 32px;
            background: var(--brand-primary);
            color: #fff;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 600;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .timeline-content h4 {
            margin: 0 0 4px;
            font-size: 1rem;
        }

        .timeline-content p {
            margin: 0;
            color: var(--brand-muted);
            font-size: 0.9rem;
        }

        .footer {
            background: var(--brand-deep);
            color: #fff;
            padding: 48px 0 24px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 32px;
        }

        @media (max-width: 768px) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }
        }

        .footer-brand p {
            color: rgba(255,255,255,0.7);
            margin: 12px 0 0;
            font-size: 0.9rem;
        }

        .footer h4 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 16px;
            color: rgba(255,255,255,0.5);
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer li {
            margin-bottom: 8px;
        }

        .footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .footer a:hover {
            color: #fff;
        }

        .footer-bottom {
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-bottom p {
            margin: 0;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="page-nav">
        <div class="container">
            <a href="<?= APP_URL ?>" class="brand-mark">
                <div class="brand-icon"><i class="bi bi-shop"></i></div>
                <span class="brand-name">WAPOS</span>
            </a>
            <div class="nav-links">
                <a href="<?= APP_URL ?>">Home</a>
                <a href="about.php">About</a>
                <a href="resources.php">User Manual</a>
                <a href="contact.php">Contact</a>
                <a href="login.php" class="btn-primary">Sign In</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero-section">
        <div class="container">
            <h1>About WAPOS</h1>
            <p>A comprehensive business management system built for modern retail, restaurant, and hospitality operations.</p>
        </div>
    </section>

    <!-- What is WAPOS -->
    <section class="content-section">
        <div class="container">
            <div class="section-header">
                <h2>What is WAPOS?</h2>
                <p>WAPOS (Waesta Point of Sale) is an all-in-one business management platform designed to streamline operations across multiple industries.</p>
            </div>
            <div class="about-grid">
                <div class="about-card">
                    <h3><i class="bi bi-cart-check"></i> Point of Sale</h3>
                    <p>Fast, reliable checkout with support for multiple payment methods, barcode scanning, customer loyalty, and real-time inventory updates.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-cup-straw"></i> Restaurant Management</h3>
                    <p>Complete restaurant operations including table management, kitchen display system, reservations, and order tracking.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-boxes"></i> Inventory Control</h3>
                    <p>Track stock levels, manage suppliers, receive goods, and get alerts when inventory runs low.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-truck"></i> Delivery Management</h3>
                    <p>Dispatch orders, track riders in real-time with GPS, and manage delivery zones and pricing.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-house-check"></i> Housekeeping</h3>
                    <p>Room status tracking, task assignment, cleaning schedules, and staff workload management for hospitality.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-calculator"></i> Accounting</h3>
                    <p>Built-in accounting with chart of accounts, journal entries, financial reports, and tax compliance.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="content-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>8+</h3>
                    <p>Integrated Modules</p>
                </div>
                <div class="stat-item">
                    <h3>10+</h3>
                    <p>User Roles</p>
                </div>
                <div class="stat-item">
                    <h3>100%</h3>
                    <p>Web-Based</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>System Availability</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose WAPOS -->
    <section class="content-section">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose WAPOS?</h2>
                <p>Built with modern businesses in mind, WAPOS offers flexibility, security, and ease of use.</p>
            </div>
            <div class="about-grid">
                <div class="about-card">
                    <h3><i class="bi bi-currency-exchange"></i> Currency Neutral</h3>
                    <p>Works with any currency worldwide. Configure your preferred symbol, format, and decimal places without code changes.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-people"></i> Role-Based Access</h3>
                    <p>Granular permissions ensure each user sees only what they need. From cashiers to accountants, everyone has their own dashboard.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-cloud-check"></i> Cloud Ready</h3>
                    <p>Deploy on any hosting platform - cPanel, AWS, DigitalOcean, or your own servers. No vendor lock-in.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-phone"></i> Responsive Design</h3>
                    <p>Works seamlessly on desktop, tablet, and mobile devices. Take orders tableside or manage from anywhere.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-shield-check"></i> Secure & Compliant</h3>
                    <p>Built with security best practices including encrypted sessions, rate limiting, audit trails, and GDPR compliance.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-gear"></i> Highly Configurable</h3>
                    <p>Enable only the modules you need. Customize receipts, reports, and workflows to match your business.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Company Info -->
    <section class="content-section">
        <div class="container">
            <div class="section-header">
                <h2>Developed by Waesta Enterprises</h2>
                <p>WAPOS is proudly developed and maintained by Waesta Enterprises U Ltd.</p>
            </div>
            <div class="company-info">
                <div class="company-logo">
                    <div class="logo-box"><i class="bi bi-building"></i></div>
                    <h3>Waesta Enterprises U Ltd</h3>
                    <p>Software Development & Business Solutions</p>
                </div>
                <div class="company-details">
                    <h3>Our Mission</h3>
                    <p>To empower businesses with affordable, reliable, and easy-to-use software solutions that drive growth and efficiency.</p>
                    <h3>Contact Information</h3>
                    <ul>
                        <li><i class="bi bi-globe"></i> <a href="https://waesta.com">www.waesta.com</a></li>
                        <li><i class="bi bi-envelope"></i> <a href="mailto:info@waesta.com">info@waesta.com</a></li>
                        <li><i class="bi bi-telephone"></i> Contact us for support</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Industries -->
    <section class="content-section">
        <div class="container">
            <div class="section-header">
                <h2>Industries We Serve</h2>
                <p>WAPOS is designed to adapt to various business types and industries.</p>
            </div>
            <div class="about-grid">
                <div class="about-card">
                    <h3><i class="bi bi-shop-window"></i> Retail Stores</h3>
                    <p>Supermarkets, convenience stores, boutiques, electronics shops, and general retail.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-cup-hot"></i> Restaurants & Cafes</h3>
                    <p>Full-service restaurants, fast food, cafes, bars, and food courts.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-building"></i> Hotels & Hospitality</h3>
                    <p>Hotels, lodges, guest houses, and serviced apartments.</p>
                </div>
                <div class="about-card">
                    <h3><i class="bi bi-bag-check"></i> Quick Service</h3>
                    <p>Takeaway outlets, delivery kitchens, and food trucks.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="<?= APP_URL ?>" class="brand-mark">
                        <div class="brand-icon"><i class="bi bi-shop"></i></div>
                        <span class="brand-name" style="color:#fff">WAPOS</span>
                    </a>
                    <p>Unified Point of Sale System for retail, restaurant, and hospitality businesses.</p>
                </div>
                <div>
                    <h4>Product</h4>
                    <ul>
                        <li><a href="<?= APP_URL ?>/#features">Features</a></li>
                        <li><a href="resources.php">User Manual</a></li>
                        <li><a href="login.php">Sign In</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Company</h4>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="https://waesta.com" target="_blank">Waesta Enterprises</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="terms-of-service.php">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.</p>
                <p>WAPOS v1.0</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
