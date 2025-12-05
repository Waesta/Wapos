<?php
/**
 * WAPOS - Resources & User Manual
 * Comprehensive documentation for system users
 * 
 * @copyright <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

$pageTitle = 'Resources & User Manual - WAPOS';
$pageDescription = 'Complete user manual and documentation for WAPOS - Point of Sale, Restaurant, Inventory, Delivery, and Business Management System.';
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
        }

        .page-header {
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            color: #fff;
            padding: 48px 0;
        }

        .page-header h1 {
            font-size: 2.2rem;
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .back-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .back-link:hover {
            color: #fff;
        }

        .manual-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .manual-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 32px;
        }

        @media (max-width: 992px) {
            .manual-layout {
                grid-template-columns: 1fr;
            }
            .manual-nav {
                position: static !important;
            }
        }

        .manual-nav {
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .nav-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-soft);
        }

        .nav-card h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--brand-muted);
            margin: 0 0 12px;
        }

        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-links li {
            margin-bottom: 4px;
        }

        .nav-links a {
            display: block;
            padding: 8px 12px;
            color: var(--brand-deep);
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--surface-muted);
            color: var(--brand-primary);
        }

        .nav-links a i {
            margin-right: 8px;
            color: var(--brand-muted);
        }

        .manual-content {
            background: var(--surface);
            border-radius: 16px;
            padding: 40px;
            border: 1px solid var(--border-soft);
        }

        .manual-content h2 {
            font-size: 1.6rem;
            margin: 48px 0 16px;
            padding-top: 24px;
            border-top: 1px solid var(--border-soft);
            color: var(--brand-deep);
        }

        .manual-content h2:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        .manual-content h3 {
            font-size: 1.2rem;
            margin: 32px 0 12px;
            color: var(--brand-deep);
        }

        .manual-content h4 {
            font-size: 1rem;
            margin: 24px 0 8px;
            color: var(--brand-muted);
        }

        .manual-content p {
            margin: 0 0 16px;
            color: #475569;
        }

        .manual-content ul, .manual-content ol {
            margin: 0 0 16px;
            padding-left: 24px;
            color: #475569;
        }

        .manual-content li {
            margin-bottom: 8px;
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid var(--brand-primary);
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }

        .info-box.warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }

        .info-box.success {
            background: #ecfdf5;
            border-left-color: #10b981;
        }

        .info-box p {
            margin: 0;
            font-size: 0.95rem;
        }

        .info-box strong {
            color: var(--brand-deep);
        }

        .steps-list {
            counter-reset: step-counter;
            list-style: none;
            padding: 0;
        }

        .steps-list li {
            counter-increment: step-counter;
            padding-left: 48px;
            position: relative;
            margin-bottom: 16px;
        }

        .steps-list li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            width: 32px;
            height: 32px;
            background: var(--brand-primary);
            color: #fff;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            background: var(--surface-muted);
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--brand-muted);
            margin-right: 4px;
        }

        .role-badge.admin { background: #fee2e2; color: #dc2626; }
        .role-badge.manager { background: #fef3c7; color: #d97706; }
        .role-badge.cashier { background: #dbeafe; color: #2563eb; }
        .role-badge.waiter { background: #d1fae5; color: #059669; }
        .role-badge.accountant { background: #e0e7ff; color: #4f46e5; }

        .shortcut-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }

        .shortcut-table th,
        .shortcut-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-soft);
        }

        .shortcut-table th {
            background: var(--surface-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .shortcut-table code {
            background: var(--surface-muted);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .footer {
            text-align: center;
            padding: 32px 0;
            color: var(--brand-muted);
            font-size: 0.9rem;
        }

        .footer strong {
            color: var(--brand-deep);
        }

        @media print {
            .manual-nav, .page-header .back-link {
                display: none;
            }
            .manual-layout {
                grid-template-columns: 1fr;
            }
            .manual-content {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container">
            <a href="<?= APP_URL ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to Home</a>
            <h1><i class="bi bi-book"></i> WAPOS User Manual</h1>
            <p>Complete guide to using the WAPOS business management system</p>
        </div>
    </header>

    <div class="manual-container">
        <div class="manual-layout">
            <!-- Navigation Sidebar -->
            <aside class="manual-nav">
                <div class="nav-card">
                    <h3>Contents</h3>
                    <ul class="nav-links">
                        <li><a href="#introduction"><i class="bi bi-info-circle"></i> Introduction</a></li>
                        <li><a href="#getting-started"><i class="bi bi-play-circle"></i> Getting Started</a></li>
                        <li><a href="#user-roles"><i class="bi bi-people"></i> User Roles</a></li>
                        <li><a href="#pos"><i class="bi bi-cart-check"></i> Point of Sale</a></li>
                        <li><a href="#restaurant"><i class="bi bi-cup-straw"></i> Restaurant</a></li>
                        <li><a href="#inventory"><i class="bi bi-boxes"></i> Inventory</a></li>
                        <li><a href="#delivery"><i class="bi bi-truck"></i> Delivery</a></li>
                        <li><a href="#housekeeping"><i class="bi bi-house"></i> Housekeeping</a></li>
                        <li><a href="#maintenance"><i class="bi bi-tools"></i> Maintenance</a></li>
                        <li><a href="#guest-portal"><i class="bi bi-shield-lock"></i> Guest Portal</a></li>
                        <li><a href="#accounting"><i class="bi bi-calculator"></i> Accounting</a></li>
                        <li><a href="#payments"><i class="bi bi-credit-card"></i> Payment Gateways</a></li>
                        <li><a href="#reports"><i class="bi bi-graph-up"></i> Reports</a></li>
                        <li><a href="#administration"><i class="bi bi-gear"></i> Administration</a></li>
                        <li><a href="#shortcuts"><i class="bi bi-keyboard"></i> Keyboard Shortcuts</a></li>
                        <li><a href="#troubleshooting"><i class="bi bi-question-circle"></i> Troubleshooting</a></li>
                    </ul>
                </div>

                <div class="nav-card mt-3">
                    <h3>Quick Actions</h3>
                    <ul class="nav-links">
                        <li><a href="login.php"><i class="bi bi-box-arrow-in-right"></i> Sign In</a></li>
                        <li><a href="#" onclick="window.print(); return false;"><i class="bi bi-printer"></i> Print Manual</a></li>
                    </ul>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="manual-content">
                <!-- Introduction -->
                <section id="introduction">
                    <h2><i class="bi bi-info-circle me-2"></i>Introduction</h2>
                    <p>Welcome to <strong>WAPOS</strong> (Waesta Point of Sale), a comprehensive business management system designed for retail, restaurant, and hospitality operations. This manual will guide you through all features and functionalities of the system.</p>
                    
                    <h3>What is WAPOS?</h3>
                    <p>WAPOS is an all-in-one platform that combines:</p>
                    <ul>
                        <li><strong>Point of Sale (POS)</strong> - Fast checkout and payment processing</li>
                        <li><strong>Restaurant Management</strong> - Table management, kitchen display, and reservations</li>
                        <li><strong>Inventory Control</strong> - Stock tracking and supplier management</li>
                        <li><strong>Delivery Management</strong> - Order dispatch and rider tracking</li>
                        <li><strong>Housekeeping</strong> - Room status and task management</li>
                        <li><strong>Maintenance</strong> - Work order and asset management</li>
                        <li><strong>Accounting</strong> - Financial reporting and tax compliance</li>
                    </ul>

                    <div class="info-box">
                        <p><strong>Currency Neutral:</strong> WAPOS works with any currency. Configure your preferred currency symbol, format, and decimal places in Settings.</p>
                    </div>

                    <h3>Device Compatibility</h3>
                    <p>WAPOS is fully responsive and works on:</p>
                    <ul>
                        <li><strong>Desktop</strong> - Full-featured experience on Windows, Mac, Linux</li>
                        <li><strong>Tablet</strong> - Optimized for iPad, Android tablets (landscape & portrait)</li>
                        <li><strong>Mobile</strong> - Touch-friendly interface for smartphones</li>
                    </ul>
                    
                    <div class="info-box success">
                        <p><strong>PWA Support:</strong> WAPOS can be installed as an app on your device. Look for the "Add to Home Screen" option in your browser for quick access.</p>
                    </div>
                </section>

                <!-- Getting Started -->
                <section id="getting-started">
                    <h2><i class="bi bi-play-circle me-2"></i>Getting Started</h2>
                    
                    <h3>Logging In</h3>
                    <ol class="steps-list">
                        <li>Open your web browser and navigate to your WAPOS URL</li>
                        <li>Enter your username and password provided by your administrator</li>
                        <li>Click <strong>Sign In</strong> to access your dashboard</li>
                        <li>You will be redirected to your role-specific dashboard</li>
                    </ol>

                    <h3>Understanding Your Dashboard</h3>
                    <p>After logging in, you'll see a dashboard tailored to your role. The dashboard displays:</p>
                    <ul>
                        <li><strong>Quick Stats</strong> - Key metrics relevant to your role</li>
                        <li><strong>Recent Activity</strong> - Latest transactions or tasks</li>
                        <li><strong>Navigation Menu</strong> - Access to all available modules</li>
                        <li><strong>Notifications</strong> - Alerts and important messages</li>
                    </ul>

                    <div class="info-box success">
                        <p><strong>Tip:</strong> Bookmark your WAPOS URL for quick access. The system remembers your session, so you won't need to log in every time.</p>
                    </div>
                </section>

                <!-- User Roles -->
                <section id="user-roles">
                    <h2><i class="bi bi-people me-2"></i>User Roles & Permissions</h2>
                    <p>WAPOS uses role-based access control. Each user is assigned a role that determines what they can see and do.</p>

                    <h3>Available Roles</h3>
                    
                    <h4><span class="role-badge admin">Admin</span> Administrator</h4>
                    <p>Full system access including user management, settings, and all modules.</p>

                    <h4><span class="role-badge manager">Manager</span> Manager</h4>
                    <p>Access to operations, reports, inventory, and staff management. Cannot modify system settings.</p>

                    <h4><span class="role-badge cashier">Cashier</span> Cashier</h4>
                    <p>Point of Sale operations, customer management, and basic sales reports.</p>

                    <h4><span class="role-badge waiter">Waiter</span> Waiter</h4>
                    <p>Restaurant order taking, table management, and order status updates.</p>

                    <h4><span class="role-badge accountant">Accountant</span> Accountant</h4>
                    <p>Financial reports, accounting entries, tax reports, and payment reconciliation.</p>

                    <h4><span class="role-badge">Rider</span> Delivery Rider</h4>
                    <p>View assigned deliveries, update delivery status, and GPS tracking.</p>

                    <h4><span class="role-badge">Housekeeper</span> Housekeeping Staff</h4>
                    <p>View assigned rooms, update cleaning status, and report issues.</p>
                </section>

                <!-- Point of Sale -->
                <section id="pos">
                    <h2><i class="bi bi-cart-check me-2"></i>Point of Sale (POS)</h2>
                    <p>The POS module is the heart of WAPOS, enabling fast and efficient checkout.</p>

                    <h3>Making a Sale</h3>
                    <ol class="steps-list">
                        <li>Click on products or scan barcodes to add items to the cart</li>
                        <li>Adjust quantities using the +/- buttons or enter manually</li>
                        <li>Apply discounts or promotions if applicable</li>
                        <li>Select the customer (optional) for loyalty points</li>
                        <li>Click <strong>Checkout</strong> to proceed to payment</li>
                        <li>Select payment method: Cash, Card, Mobile Money, or Bank Transfer</li>
                        <li>Complete the transaction and print receipt</li>
                    </ol>

                    <h3>Payment Methods</h3>
                    <ul>
                        <li><strong>Cash</strong> - Enter amount received, system calculates change</li>
                        <li><strong>Card</strong> - Process through connected card terminal</li>
                        <li><strong>Mobile Money</strong> - Record mobile payment reference</li>
                        <li><strong>Bank Transfer</strong> - Record transfer reference number</li>
                        <li><strong>Split Payment</strong> - Combine multiple payment methods</li>
                    </ul>

                    <h3>Held Orders</h3>
                    <p>Need to pause a transaction? Use the <strong>Hold</strong> feature:</p>
                    <ol class="steps-list">
                        <li>Click <strong>Hold Order</strong> during checkout</li>
                        <li>Add a reference name (e.g., customer name)</li>
                        <li>The order is saved and cart is cleared</li>
                        <li>Retrieve held orders from the <strong>Held Orders</strong> panel</li>
                    </ol>

                    <h3>Voids & Refunds</h3>
                    <div class="info-box warning">
                        <p><strong>Important:</strong> Void and refund operations require manager approval and are logged for audit purposes.</p>
                    </div>
                    <ul>
                        <li><strong>Void Item</strong> - Remove an item before completing sale</li>
                        <li><strong>Void Transaction</strong> - Cancel entire sale (requires reason)</li>
                        <li><strong>Refund</strong> - Process return after sale completion</li>
                    </ul>

                    <h3>Register Reports</h3>
                    <p>End-of-shift reporting for cashiers:</p>
                    <ul>
                        <li><strong>X Report</strong> - Mid-shift summary (doesn't close register)</li>
                        <li><strong>Y Report</strong> - Detailed breakdown by payment method</li>
                        <li><strong>Z Report</strong> - End-of-day closing report (closes register)</li>
                    </ul>
                </section>

                <!-- Restaurant -->
                <section id="restaurant">
                    <h2><i class="bi bi-cup-straw me-2"></i>Restaurant Module</h2>
                    <p>Complete restaurant management including tables, orders, and kitchen operations.</p>

                    <h3>Table Management</h3>
                    <ul>
                        <li><strong>Floor Plan</strong> - Visual layout of your restaurant</li>
                        <li><strong>Table Status</strong> - Available, Occupied, Reserved, Cleaning</li>
                        <li><strong>Merge Tables</strong> - Combine tables for large parties</li>
                        <li><strong>Transfer Table</strong> - Move guests to different table</li>
                    </ul>

                    <h3>Taking Orders</h3>
                    <ol class="steps-list">
                        <li>Select a table from the floor plan</li>
                        <li>Choose order type: Dine-in, Takeout, or Delivery</li>
                        <li>Add menu items with modifiers (e.g., "no onions")</li>
                        <li>Add special instructions for the kitchen</li>
                        <li>Send order to Kitchen Display System (KDS)</li>
                    </ol>

                    <h3>Kitchen Display System (KDS)</h3>
                    <p>The KDS shows incoming orders to kitchen staff:</p>
                    <ul>
                        <li>Orders appear automatically when sent from POS</li>
                        <li>Color-coded by order age (green → yellow → red)</li>
                        <li>Mark items as "Preparing" or "Ready"</li>
                        <li>Bump completed orders off the screen</li>
                    </ul>

                    <h3>Reservations</h3>
                    <ol class="steps-list">
                        <li>Go to <strong>Restaurant → Reservations</strong></li>
                        <li>Click <strong>New Reservation</strong></li>
                        <li>Enter guest name, phone, party size, date/time</li>
                        <li>Select preferred table (optional)</li>
                        <li>Add special requests or notes</li>
                        <li>Save reservation - guest receives confirmation</li>
                    </ol>
                </section>

                <!-- Inventory -->
                <section id="inventory">
                    <h2><i class="bi bi-boxes me-2"></i>Inventory Management</h2>
                    <p>Track stock levels, manage products, and handle supplier relationships.</p>

                    <h3>Products</h3>
                    <p>Managing your product catalog:</p>
                    <ul>
                        <li><strong>Add Product</strong> - Name, SKU, barcode, price, category</li>
                        <li><strong>Categories</strong> - Organize products into groups</li>
                        <li><strong>Variants</strong> - Size, color, or other variations</li>
                        <li><strong>Images</strong> - Upload product photos</li>
                        <li><strong>Track Inventory</strong> - Enable stock tracking per product</li>
                    </ul>

                    <h3>Stock Management</h3>
                    <ol class="steps-list">
                        <li>Go to <strong>Inventory → Stock Levels</strong></li>
                        <li>View current stock for all products</li>
                        <li>Use filters to find low stock items</li>
                        <li>Click product to view stock history</li>
                    </ol>

                    <h3>Goods Received Notes (GRN)</h3>
                    <p>Record incoming stock from suppliers:</p>
                    <ol class="steps-list">
                        <li>Go to <strong>Inventory → Goods Received</strong></li>
                        <li>Click <strong>New GRN</strong></li>
                        <li>Select supplier and enter reference number</li>
                        <li>Add products and quantities received</li>
                        <li>Verify and submit - stock levels update automatically</li>
                    </ol>

                    <h3>Stock Alerts</h3>
                    <div class="info-box">
                        <p><strong>Low Stock Alerts:</strong> Set minimum stock levels for products. When stock falls below this threshold, you'll receive alerts on your dashboard.</p>
                    </div>
                </section>

                <!-- Delivery -->
                <section id="delivery">
                    <h2><i class="bi bi-truck me-2"></i>Delivery Management</h2>
                    <p>Manage delivery orders, assign riders, and track deliveries in real-time.</p>

                    <h3>Creating Delivery Orders</h3>
                    <ol class="steps-list">
                        <li>Create order in POS and select <strong>Delivery</strong> type</li>
                        <li>Enter customer address and contact details</li>
                        <li>System calculates delivery fee based on distance</li>
                        <li>Complete payment (can be Cash on Delivery)</li>
                        <li>Order appears in Delivery queue</li>
                    </ol>

                    <h3>Dispatching Orders</h3>
                    <ol class="steps-list">
                        <li>Go to <strong>Delivery → Dispatch</strong></li>
                        <li>View pending deliveries</li>
                        <li>Assign rider to delivery</li>
                        <li>Rider receives notification</li>
                    </ol>

                    <h3>Live Tracking</h3>
                    <p>Track riders in real-time:</p>
                    <ul>
                        <li>View all active riders on map</li>
                        <li>See estimated arrival times</li>
                        <li>Monitor delivery status updates</li>
                        <li>Contact rider directly if needed</li>
                    </ul>

                    <h3>Delivery Status Flow</h3>
                    <p><code>Pending</code> → <code>Assigned</code> → <code>Picked Up</code> → <code>In Transit</code> → <code>Delivered</code></p>
                </section>

                <!-- Housekeeping -->
                <section id="housekeeping">
                    <h2><i class="bi bi-house me-2"></i>Housekeeping Module</h2>
                    <p>Manage room cleaning schedules and housekeeping tasks.</p>

                    <h3>Room Status Board</h3>
                    <p>Visual overview of all rooms and their status:</p>
                    <ul>
                        <li><strong>Clean</strong> - Ready for guests (green)</li>
                        <li><strong>Dirty</strong> - Needs cleaning (red)</li>
                        <li><strong>In Progress</strong> - Being cleaned (yellow)</li>
                        <li><strong>Inspected</strong> - Verified clean (blue)</li>
                        <li><strong>Out of Order</strong> - Not available (gray)</li>
                    </ul>

                    <h3>Assigning Tasks</h3>
                    <ol class="steps-list">
                        <li>Select room(s) from the board</li>
                        <li>Click <strong>Assign Task</strong></li>
                        <li>Select housekeeper from available staff</li>
                        <li>Set priority (Normal, High, Urgent)</li>
                        <li>Add special instructions if needed</li>
                    </ol>

                    <h3>For Housekeeping Staff</h3>
                    <ol class="steps-list">
                        <li>Log in to see your assigned rooms</li>
                        <li>Tap room to start cleaning (status → In Progress)</li>
                        <li>Complete cleaning checklist</li>
                        <li>Mark as complete (status → Clean)</li>
                        <li>Report any maintenance issues found</li>
                    </ol>
                </section>

                <!-- Maintenance -->
                <section id="maintenance">
                    <h2><i class="bi bi-tools me-2"></i>Maintenance Module</h2>
                    <p>Track maintenance requests, work orders, and asset management.</p>

                    <h3>Submitting Requests</h3>
                    <p>Any staff member can submit maintenance requests:</p>
                    <ol class="steps-list">
                        <li>Go to <strong>Maintenance → New Request</strong></li>
                        <li>Select location/room with the issue</li>
                        <li>Choose issue category (Electrical, Plumbing, HVAC, etc.)</li>
                        <li>Describe the problem</li>
                        <li>Set priority level</li>
                        <li>Submit request</li>
                    </ol>

                    <h3>Work Order Management</h3>
                    <p>For maintenance managers:</p>
                    <ul>
                        <li>Review incoming requests</li>
                        <li>Assign technicians to work orders</li>
                        <li>Set estimated completion time</li>
                        <li>Track progress and costs</li>
                        <li>Close completed work orders</li>
                    </ul>

                    <h3>Work Order Status</h3>
                    <p><code>Submitted</code> → <code>Assigned</code> → <code>In Progress</code> → <code>Completed</code> → <code>Verified</code></p>
                </section>

                <!-- Accounting -->
                <section id="accounting">
                    <h2><i class="bi bi-calculator me-2"></i>Accounting Module</h2>
                    <p>Financial management, reporting, and tax compliance.</p>

                    <h3>Chart of Accounts</h3>
                    <p>WAPOS includes a standard chart of accounts:</p>
                    <ul>
                        <li><strong>Assets</strong> - Cash, inventory, equipment</li>
                        <li><strong>Liabilities</strong> - Accounts payable, loans</li>
                        <li><strong>Equity</strong> - Owner's equity, retained earnings</li>
                        <li><strong>Revenue</strong> - Sales, service income</li>
                        <li><strong>Expenses</strong> - Operating costs, wages</li>
                    </ul>

                    <h3>Journal Entries</h3>
                    <p>Sales transactions automatically create journal entries. Manual entries can be made for:</p>
                    <ul>
                        <li>Adjustments and corrections</li>
                        <li>Non-sales transactions</li>
                        <li>Accruals and deferrals</li>
                    </ul>

                    <h3>Financial Reports</h3>
                    <ul>
                        <li><strong>Profit & Loss</strong> - Income and expenses for a period</li>
                        <li><strong>Balance Sheet</strong> - Assets, liabilities, and equity</li>
                        <li><strong>Cash Flow</strong> - Money in and out</li>
                        <li><strong>Tax Report</strong> - Sales tax collected and payable</li>
                    </ul>

                    <h3>Payment Reconciliation</h3>
                    <ol class="steps-list">
                        <li>Go to <strong>Accounting → Reconciliation</strong></li>
                        <li>Select payment method to reconcile</li>
                        <li>Compare system totals with bank/provider statements</li>
                        <li>Mark discrepancies for investigation</li>
                        <li>Complete reconciliation</li>
                    </ol>
                </section>

                <!-- Guest Portal -->
                <section id="guest-portal">
                    <h2><i class="bi bi-shield-lock me-2"></i>Secure Guest Portal</h2>
                    <p>Allow registered guests to submit maintenance and housekeeping requests through a secure, authenticated portal.</p>

                    <div class="info-box warning">
                        <p><strong>Security First:</strong> The guest portal requires authentication. Credentials are generated at check-in and automatically expire at checkout.</p>
                    </div>

                    <h3>Security Features</h3>
                    <ul>
                        <li><strong>AES-256-GCM Encryption</strong> - All sensitive data encrypted at rest</li>
                        <li><strong>Argon2ID Password Hashing</strong> - Industry-leading password security</li>
                        <li><strong>Rate Limiting</strong> - 5 failed attempts = 30 minute lockout</li>
                        <li><strong>Auto-Expiry</strong> - Access expires on checkout date</li>
                        <li><strong>Secure Sessions</strong> - HTTPOnly cookies, SHA-256 tokens</li>
                        <li><strong>Activity Logging</strong> - All access attempts logged with IP</li>
                    </ul>

                    <h3>Creating Guest Access (At Check-in)</h3>
                    <ol class="steps-list">
                        <li>Go to <strong>Settings → Guest Portal</strong></li>
                        <li>Fill in guest name, room number, and dates</li>
                        <li>Click <strong>Generate Credentials</strong></li>
                        <li>Share credentials via WhatsApp, Email, or print</li>
                    </ol>

                    <h3>Guest Login Options</h3>
                    <ul>
                        <li><strong>Direct Link</strong> - One-click secure access (recommended)</li>
                        <li><strong>Username/Password</strong> - Manual login with Guest ID</li>
                    </ul>

                    <h3>How Guests Use the Portal</h3>
                    <ol class="steps-list">
                        <li>Guest receives credentials at check-in</li>
                        <li>Clicks the secure link or logs in manually</li>
                        <li>Submits maintenance or housekeeping request</li>
                        <li>Receives tracking code for status updates</li>
                        <li>Access automatically expires on checkout</li>
                    </ol>

                    <h3>Managing Guest Access</h3>
                    <p>From the Guest Portal settings page, staff can:</p>
                    <ul>
                        <li><strong>View Active Accesses</strong> - See all current guest credentials</li>
                        <li><strong>Regenerate Credentials</strong> - Issue new password if needed</li>
                        <li><strong>Revoke Access</strong> - Immediately disable guest access</li>
                        <li><strong>Track Logins</strong> - Monitor guest portal usage</li>
                    </ul>

                    <h3>For Staff</h3>
                    <p>Guest requests appear in the Maintenance dashboard with a "Guest" tag. Staff can:</p>
                    <ul>
                        <li>View all guest requests in one place</li>
                        <li>Assign technicians or housekeepers</li>
                        <li>Update status (guests see updates in real-time)</li>
                        <li>Contact guest if needed</li>
                    </ul>
                </section>

                <!-- Payment Gateways -->
                <section id="payments">
                    <h2><i class="bi bi-credit-card me-2"></i>Payment Gateways</h2>
                    <p>WAPOS supports multiple payment gateways for seamless payment processing.</p>

                    <h3>Supported Payment Methods</h3>
                    <ul>
                        <li><strong>M-Pesa (Daraja API)</strong> - Direct Safaricom integration for Kenya</li>
                        <li><strong>Airtel Money</strong> - Kenya, Uganda, Rwanda, Tanzania via Relworx</li>
                        <li><strong>MTN Mobile Money</strong> - Uganda, Rwanda via Relworx</li>
                        <li><strong>Card Payments</strong> - Visa/Mastercard via Relworx or PesaPal</li>
                        <li><strong>PesaPal</strong> - Multi-method payment aggregator</li>
                    </ul>

                    <h3>M-Pesa STK Push (USSD Prompt)</h3>
                    <p>The most common payment method in Kenya. Sends a payment prompt directly to the customer's phone.</p>
                    
                    <h4>How STK Push Works</h4>
                    <ol class="steps-list">
                        <li>Customer provides their Safaricom phone number at checkout</li>
                        <li>Cashier clicks <strong>Pay with M-Pesa</strong></li>
                        <li>Customer receives USSD prompt on their phone</li>
                        <li>Customer enters M-Pesa PIN to authorize payment</li>
                        <li>Payment confirmation appears in WAPOS automatically</li>
                        <li>Receipt is printed with M-Pesa reference number</li>
                    </ol>

                    <div class="info-box success">
                        <p><strong>Instant Confirmation:</strong> STK Push payments are confirmed within seconds. The system automatically updates the sale status when payment is received.</p>
                    </div>

                    <h4>Accepted Phone Formats</h4>
                    <p>Enter customer phone in any of these formats:</p>
                    <ul>
                        <li><code>0712345678</code> - Local format</li>
                        <li><code>712345678</code> - Without leading zero</li>
                        <li><code>254712345678</code> - With country code</li>
                        <li><code>+254712345678</code> - International format</li>
                    </ul>

                    <h3>M-Pesa Paybill & Till</h3>
                    <p>For customers who prefer to initiate payment themselves:</p>
                    
                    <h4>Paybill Payment</h4>
                    <ol class="steps-list">
                        <li>Customer opens M-Pesa on their phone</li>
                        <li>Selects <strong>Lipa na M-Pesa → Pay Bill</strong></li>
                        <li>Enters your Business Number (Paybill)</li>
                        <li>Enters Account Number (invoice/order number)</li>
                        <li>Enters amount and M-Pesa PIN</li>
                        <li>WAPOS receives confirmation automatically</li>
                    </ol>

                    <h4>Till/Buy Goods Payment</h4>
                    <ol class="steps-list">
                        <li>Customer opens M-Pesa on their phone</li>
                        <li>Selects <strong>Lipa na M-Pesa → Buy Goods</strong></li>
                        <li>Enters your Till Number</li>
                        <li>Enters amount and M-Pesa PIN</li>
                        <li>WAPOS receives confirmation automatically</li>
                    </ol>

                    <h3>Airtel Money Payments</h3>
                    <p>Accept Airtel Money payments from customers in East Africa:</p>
                    <ul>
                        <li><strong>Kenya</strong> - Airtel Kenya subscribers</li>
                        <li><strong>Uganda</strong> - Airtel Uganda subscribers</li>
                        <li><strong>Rwanda</strong> - Airtel Rwanda subscribers</li>
                        <li><strong>Tanzania</strong> - Airtel Tanzania subscribers</li>
                    </ul>
                    
                    <h4>Processing Airtel Payments</h4>
                    <ol class="steps-list">
                        <li>Select <strong>Airtel Money</strong> as payment method</li>
                        <li>Enter customer's Airtel phone number</li>
                        <li>Customer receives payment prompt</li>
                        <li>Customer enters PIN to confirm</li>
                        <li>Payment confirmation received</li>
                    </ol>

                    <h3>MTN Mobile Money</h3>
                    <p>Accept MTN MoMo payments from Uganda and Rwanda:</p>
                    <ol class="steps-list">
                        <li>Select <strong>MTN MoMo</strong> as payment method</li>
                        <li>Enter customer's MTN phone number</li>
                        <li>Customer receives payment prompt</li>
                        <li>Customer enters PIN to confirm</li>
                        <li>Payment confirmation received</li>
                    </ol>

                    <h3>Card Payments</h3>
                    <p>Accept Visa and Mastercard payments:</p>
                    <ul>
                        <li>Integrated card terminal (if connected)</li>
                        <li>Online card payment via PesaPal hosted page</li>
                        <li>Manual card entry for phone orders</li>
                    </ul>

                    <h3>Payment Gateway Setup</h3>
                    <div class="info-box warning">
                        <p><strong>Admin Only:</strong> Payment gateway configuration requires Super Admin or Developer access. Go to <strong>Settings → Payment Gateways</strong> to configure.</p>
                    </div>

                    <h4>M-Pesa Daraja Setup</h4>
                    <ol class="steps-list">
                        <li>Register at <a href="https://developer.safaricom.co.ke" target="_blank">developer.safaricom.co.ke</a></li>
                        <li>Create an app to get Consumer Key and Secret</li>
                        <li>Request Passkey for STK Push</li>
                        <li>Enter credentials in WAPOS Payment Gateways settings</li>
                        <li>Test in Sandbox mode first</li>
                        <li>Apply for Go-Live when ready for production</li>
                    </ol>

                    <h4>Relworx Setup</h4>
                    <ol class="steps-list">
                        <li>Register at <a href="https://relworx.com" target="_blank">relworx.com</a></li>
                        <li>Get API Key and Secret from dashboard</li>
                        <li>Enter credentials in WAPOS settings</li>
                        <li>Configure callback URL</li>
                        <li>Test and go live</li>
                    </ol>

                    <h4>PesaPal Setup</h4>
                    <ol class="steps-list">
                        <li>Register at <a href="https://www.pesapal.com" target="_blank">pesapal.com</a></li>
                        <li>Get Consumer Key and Secret</li>
                        <li>Configure IPN (Instant Payment Notification) URL</li>
                        <li>Enter credentials in WAPOS settings</li>
                        <li>Test in Sandbox, then switch to Live</li>
                    </ol>

                    <h3>Payment Troubleshooting</h3>
                    
                    <h4>STK Push not received</h4>
                    <ul>
                        <li>Verify phone number is correct and active</li>
                        <li>Check customer has sufficient M-Pesa balance</li>
                        <li>Ensure phone has network signal</li>
                        <li>Try again after 30 seconds (M-Pesa timeout)</li>
                    </ul>

                    <h4>Payment confirmed but sale not updated</h4>
                    <ul>
                        <li>Check callback URL is correctly configured</li>
                        <li>Verify server can receive external requests</li>
                        <li>Check payment logs for errors</li>
                        <li>Manually reconcile if needed</li>
                    </ul>

                    <h4>Wrong amount charged</h4>
                    <ul>
                        <li>Verify sale total before sending payment request</li>
                        <li>Process refund through M-Pesa if needed</li>
                        <li>Document discrepancy for reconciliation</li>
                    </ul>
                </section>

                <!-- Reports -->
                <section id="reports">
                    <h2><i class="bi bi-graph-up me-2"></i>Reports</h2>
                    <p>WAPOS provides comprehensive reporting across all modules.</p>

                    <h3>Sales Reports</h3>
                    <ul>
                        <li><strong>Daily Sales</strong> - Today's transactions and totals</li>
                        <li><strong>Sales by Period</strong> - Custom date range analysis</li>
                        <li><strong>Sales by Product</strong> - Best and worst sellers</li>
                        <li><strong>Sales by Category</strong> - Category performance</li>
                        <li><strong>Sales by Payment Method</strong> - Cash, card, mobile breakdown</li>
                        <li><strong>Sales by Cashier</strong> - Staff performance</li>
                    </ul>

                    <h3>Inventory Reports</h3>
                    <ul>
                        <li><strong>Stock Levels</strong> - Current inventory status</li>
                        <li><strong>Low Stock</strong> - Items below minimum threshold</li>
                        <li><strong>Stock Movement</strong> - In/out history</li>
                        <li><strong>Valuation</strong> - Inventory value at cost</li>
                    </ul>

                    <h3>Exporting Reports</h3>
                    <p>All reports can be exported in multiple formats:</p>
                    <ul>
                        <li><strong>PDF</strong> - For printing and sharing</li>
                        <li><strong>Excel</strong> - For further analysis</li>
                        <li><strong>CSV</strong> - For importing to other systems</li>
                    </ul>
                </section>

                <!-- Administration -->
                <section id="administration">
                    <h2><i class="bi bi-gear me-2"></i>Administration</h2>
                    <p>System configuration and user management (Admin only).</p>

                    <h3>User Management</h3>
                    <ol class="steps-list">
                        <li>Go to <strong>Admin → Users</strong></li>
                        <li>Click <strong>Add User</strong></li>
                        <li>Enter name, email, username</li>
                        <li>Set temporary password</li>
                        <li>Assign role</li>
                        <li>Save - user can now log in</li>
                    </ol>

                    <h3>System Settings</h3>
                    <ul>
                        <li><strong>Business Info</strong> - Company name, address, logo</li>
                        <li><strong>Currency</strong> - Symbol, format, decimal places</li>
                        <li><strong>Tax Settings</strong> - Tax rates and rules</li>
                        <li><strong>Receipt Settings</strong> - Header, footer, format</li>
                        <li><strong>Module Settings</strong> - Enable/disable modules</li>
                    </ul>

                    <h3>Data Backup</h3>
                    <div class="info-box warning">
                        <p><strong>Important:</strong> Regular backups protect your business data. Schedule automatic backups or run manual backups before major changes.</p>
                    </div>
                    <ol class="steps-list">
                        <li>Go to <strong>Admin → Backup</strong></li>
                        <li>Click <strong>Create Backup</strong></li>
                        <li>Download backup file to secure location</li>
                        <li>Store backups off-site for disaster recovery</li>
                    </ol>
                </section>

                <!-- Keyboard Shortcuts -->
                <section id="shortcuts">
                    <h2><i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts</h2>
                    <p>Speed up your workflow with these keyboard shortcuts:</p>

                    <h3>POS Shortcuts</h3>
                    <table class="shortcut-table">
                        <thead>
                            <tr>
                                <th>Shortcut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>F1</code></td><td>Open help</td></tr>
                            <tr><td><code>F2</code></td><td>New sale</td></tr>
                            <tr><td><code>F3</code></td><td>Search products</td></tr>
                            <tr><td><code>F4</code></td><td>Search customers</td></tr>
                            <tr><td><code>F8</code></td><td>Hold order</td></tr>
                            <tr><td><code>F9</code></td><td>Recall held order</td></tr>
                            <tr><td><code>F12</code></td><td>Checkout / Pay</td></tr>
                            <tr><td><code>Esc</code></td><td>Cancel / Close dialog</td></tr>
                            <tr><td><code>Enter</code></td><td>Confirm action</td></tr>
                        </tbody>
                    </table>

                    <h3>Navigation Shortcuts</h3>
                    <table class="shortcut-table">
                        <thead>
                            <tr>
                                <th>Shortcut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>Alt + D</code></td><td>Go to Dashboard</td></tr>
                            <tr><td><code>Alt + P</code></td><td>Go to POS</td></tr>
                            <tr><td><code>Alt + I</code></td><td>Go to Inventory</td></tr>
                            <tr><td><code>Alt + R</code></td><td>Go to Reports</td></tr>
                            <tr><td><code>Alt + S</code></td><td>Go to Settings</td></tr>
                        </tbody>
                    </table>
                </section>

                <!-- Troubleshooting -->
                <section id="troubleshooting">
                    <h2><i class="bi bi-question-circle me-2"></i>Troubleshooting</h2>
                    <p>Common issues and how to resolve them.</p>

                    <h3>Login Issues</h3>
                    <h4>Can't log in</h4>
                    <ul>
                        <li>Check that Caps Lock is off</li>
                        <li>Verify username spelling</li>
                        <li>Contact admin to reset password</li>
                        <li>Clear browser cache and cookies</li>
                    </ul>

                    <h4>Session expired</h4>
                    <ul>
                        <li>Sessions expire after inactivity for security</li>
                        <li>Simply log in again to continue</li>
                        <li>Unsaved work may be lost - save frequently</li>
                    </ul>

                    <h3>POS Issues</h3>
                    <h4>Receipt not printing</h4>
                    <ul>
                        <li>Check printer is connected and powered on</li>
                        <li>Verify paper is loaded correctly</li>
                        <li>Check printer is set as default</li>
                        <li>Try printing a test page from system settings</li>
                    </ul>

                    <h4>Barcode scanner not working</h4>
                    <ul>
                        <li>Ensure scanner is connected via USB</li>
                        <li>Check scanner is in keyboard mode</li>
                        <li>Click in the search field before scanning</li>
                        <li>Try scanning a known barcode to test</li>
                    </ul>

                    <h3>Performance Issues</h3>
                    <h4>System running slow</h4>
                    <ul>
                        <li>Clear browser cache</li>
                        <li>Close unnecessary browser tabs</li>
                        <li>Check internet connection speed</li>
                        <li>Contact admin if issue persists</li>
                    </ul>

                    <h3>Getting Help</h3>
                    <div class="info-box">
                        <p><strong>Need more help?</strong> Contact your system administrator or reach out to Waesta Enterprises support for technical assistance.</p>
                    </div>
                </section>

                <!-- Version Info -->
                <section id="version">
                    <h2><i class="bi bi-info-square me-2"></i>Version Information</h2>
                    <p><strong>WAPOS</strong> - Unified Point of Sale System</p>
                    <p>Developed and maintained by <strong>Waesta Enterprises U Ltd</strong></p>
                    <p>Documentation last updated: <?= date('F Y') ?></p>
                </section>
            </main>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> <strong>Waesta Enterprises U Ltd</strong>. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight active section in navigation
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');

        function highlightNav() {
            let scrollPos = window.scrollY + 100;
            
            sections.forEach(section => {
                if (scrollPos >= section.offsetTop && scrollPos < section.offsetTop + section.offsetHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === '#' + section.id) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }

        window.addEventListener('scroll', highlightNav);
        highlightNav();

        // Smooth scroll for anchor links
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
