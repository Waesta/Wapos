<?php
/**
 * WAPOS - Sample Test Data & Testing Guide
 * Printable document for system testing before market release
 */
require_once 'includes/bootstrap.php';
$pageTitle = 'Sample Test Data & Testing Guide - WAPOS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @media print { .no-print { display: none !important; } .page-break { page-break-before: always; } body { font-size: 11pt; } }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8fafc; color: #0f172a; line-height: 1.6; }
        .page-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 32px 0; margin-bottom: 32px; }
        .container { max-width: 900px; }
        .section-title { background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0; margin-bottom: 0; }
        .section-content { background: #fff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; padding: 20px; margin-bottom: 24px; }
        .data-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: left; }
        .data-table th { background: #f1f5f9; font-weight: 600; }
        .test-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .test-card h4 { margin: 0 0 12px; color: #1e293b; border-bottom: 2px solid #2563eb; padding-bottom: 8px; }
        .checklist { list-style: none; padding: 0; margin: 0; }
        .checklist li { padding: 8px 0; border-bottom: 1px dashed #e2e8f0; display: flex; align-items: flex-start; gap: 10px; }
        .checkbox { width: 18px; height: 18px; border: 2px solid #64748b; border-radius: 3px; flex-shrink: 0; }
        .highlight { background: #fef3c7; padding: 2px 6px; border-radius: 4px; font-weight: 500; }
        .badge-role { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-admin { background: #dc2626; color: #fff; }
        .badge-manager { background: #2563eb; color: #fff; }
        .badge-cashier { background: #16a34a; color: #fff; }
    </style>
</head>
<body>
    <header class="page-header no-print">
        <div class="container">
            <a href="<?= APP_URL ?>" class="text-white text-decoration-none mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Back</a>
            <h1><i class="bi bi-clipboard-check"></i> WAPOS Test Data & Testing Guide</h1>
            <p>Sample data and test scenarios for system validation</p>
            <button onclick="window.print()" class="btn btn-light mt-2"><i class="bi bi-printer"></i> Print Guide</button>
        </div>
    </header>

    <div class="container pb-5">
        <div class="d-none d-print-block text-center mb-4">
            <h1>WAPOS - Sample Test Data & Testing Guide</h1>
            <p>System Validation Document | <?= date('F Y') ?></p><hr>
        </div>

        <!-- Section 1: Test Users -->
        <section id="users">
            <h2 class="section-title"><i class="bi bi-people me-2"></i>1. Test User Accounts</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Username</th><th>Password</th><th>Role</th><th>Full Name</th></tr></thead>
                    <tbody>
                        <tr><td><code>superadmin</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role" style="background:#000;color:#fff;">Developer</span></td><td>Super Administrator</td></tr>
                        <tr><td><code>admin_kampala</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role badge-admin">Admin</span></td><td>John Mukasa</td></tr>
                        <tr><td><code>manager_kampala</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role badge-manager">Manager</span></td><td>Peter Okello</td></tr>
                        <tr><td><code>cashier1</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role badge-cashier">Cashier</span></td><td>Sarah Nambi</td></tr>
                        <tr><td><code>waiter1</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role" style="background:#ca8a04;color:#fff;">Waiter</span></td><td>Moses Ssemakula</td></tr>
                        <tr><td><code>accountant</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role" style="background:#0891b2;color:#fff;">Accountant</span></td><td>Robert Kato</td></tr>
                        <tr><td><code>rider1</code></td><td><code>Thepurpose@2025</code></td><td><span class="badge-role" style="background:#7c3aed;color:#fff;">Rider</span></td><td>Emmanuel Mugisha</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 2: Customers -->
        <section id="customers">
            <h2 class="section-title"><i class="bi bi-person-vcard me-2"></i>2. Sample Customers</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>ID</th></tr></thead>
                    <tbody>
                        <tr><td>David Kamau</td><td><code>0712345678</code></td><td>david.kamau@email.com</td><td>123 Kenyatta Ave, Nairobi</td><td>12345678</td></tr>
                        <tr><td>Jane Wanjiku</td><td><code>0723456789</code></td><td>jane.wanjiku@email.com</td><td>45 Moi Road, Westlands</td><td>23456789</td></tr>
                        <tr><td>Peter Ochieng</td><td><code>0734567890</code></td><td>peter.ochieng@email.com</td><td>78 Oginga Odinga St, Kisumu</td><td>34567890</td></tr>
                        <tr><td>Mary Akinyi</td><td><code>0745678901</code></td><td>mary.akinyi@email.com</td><td>22 Uhuru Highway, Nakuru</td><td>45678901</td></tr>
                        <tr><td>John Smith</td><td><code>+1234567890</code></td><td>john.smith@email.com</td><td>International Guest</td><td>AB1234567</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 3: Products -->
        <section id="products" class="page-break">
            <h2 class="section-title"><i class="bi bi-box-seam me-2"></i>3. Sample Products & Menu</h2>
            <div class="section-content">
                <h4>Restaurant Menu Items</h4>
                <table class="data-table">
                    <thead><tr><th>Category</th><th>Product</th><th>Price (KES)</th><th>SKU</th></tr></thead>
                    <tbody>
                        <tr><td>Breakfast</td><td>English Breakfast</td><td>850</td><td>BRK001</td></tr>
                        <tr><td>Breakfast</td><td>Pancakes</td><td>550</td><td>BRK002</td></tr>
                        <tr><td>Main Course</td><td>Grilled Chicken</td><td>1,200</td><td>MAIN001</td></tr>
                        <tr><td>Main Course</td><td>Beef Steak</td><td>1,800</td><td>MAIN002</td></tr>
                        <tr><td>Main Course</td><td>Fish & Chips</td><td>950</td><td>MAIN003</td></tr>
                        <tr><td>Beverages</td><td>Fresh Juice</td><td>250</td><td>BEV001</td></tr>
                        <tr><td>Beverages</td><td>Soft Drink</td><td>150</td><td>BEV002</td></tr>
                        <tr><td>Beverages</td><td>Coffee/Tea</td><td>200</td><td>BEV003</td></tr>
                        <tr><td>Desserts</td><td>Chocolate Cake</td><td>450</td><td>DES001</td></tr>
                    </tbody>
                </table>

                <h4 class="mt-4">Retail Products (with Barcodes)</h4>
                <table class="data-table">
                    <thead><tr><th>Category</th><th>Product</th><th>Price (KES)</th><th>SKU/Barcode</th><th>Stock</th></tr></thead>
                    <tbody>
                        <tr><td>Electronics</td><td>USB Cable Type-C</td><td>500</td><td>6901234567890</td><td>25</td></tr>
                        <tr><td>Electronics</td><td>Phone Charger</td><td>800</td><td>6901234567891</td><td>30</td></tr>
                        <tr><td>Electronics</td><td>Earphones</td><td>350</td><td>6901234567892</td><td>40</td></tr>
                        <tr><td>Electronics</td><td>Power Bank 10000mAh</td><td>2,500</td><td>6901234567893</td><td>15</td></tr>
                        <tr><td>Toiletries</td><td>Toothbrush</td><td>150</td><td>6902345678901</td><td>50</td></tr>
                        <tr><td>Toiletries</td><td>Toothpaste 100ml</td><td>250</td><td>6902345678902</td><td>45</td></tr>
                        <tr><td>Toiletries</td><td>Shampoo 200ml</td><td>450</td><td>6902345678903</td><td>35</td></tr>
                        <tr><td>Toiletries</td><td>Body Lotion 250ml</td><td>550</td><td>6902345678904</td><td>30</td></tr>
                        <tr><td>Toiletries</td><td>Soap Bar</td><td>80</td><td>6902345678905</td><td>100</td></tr>
                        <tr><td>Snacks</td><td>Potato Chips 100g</td><td>120</td><td>6903456789012</td><td>60</td></tr>
                        <tr><td>Snacks</td><td>Chocolate Bar</td><td>150</td><td>6903456789013</td><td>55</td></tr>
                        <tr><td>Snacks</td><td>Biscuits Pack</td><td>100</td><td>6903456789014</td><td>70</td></tr>
                        <tr><td>Snacks</td><td>Peanuts 50g</td><td>80</td><td>6903456789015</td><td>80</td></tr>
                        <tr><td>Beverages</td><td>Mineral Water 500ml</td><td>50</td><td>6904567890123</td><td>200</td></tr>
                        <tr><td>Beverages</td><td>Soda 500ml</td><td>80</td><td>6904567890124</td><td>150</td></tr>
                        <tr><td>Beverages</td><td>Energy Drink</td><td>180</td><td>6904567890125</td><td>40</td></tr>
                        <tr><td>Beverages</td><td>Juice Box 250ml</td><td>100</td><td>6904567890126</td><td>90</td></tr>
                        <tr><td>Stationery</td><td>Notebook A5</td><td>120</td><td>6905678901234</td><td>45</td></tr>
                        <tr><td>Stationery</td><td>Pen (Blue)</td><td>30</td><td>6905678901235</td><td>100</td></tr>
                        <tr><td>Stationery</td><td>Pencil</td><td>20</td><td>6905678901236</td><td>120</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 4: Rooms -->
        <section id="rooms">
            <h2 class="section-title"><i class="bi bi-door-open me-2"></i>4. Sample Rooms</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Room Type</th><th>Rate/Night</th><th>Max Guests</th><th>Room Numbers</th></tr></thead>
                    <tbody>
                        <tr><td>Standard Single</td><td>KES 3,500</td><td>1</td><td>101, 102, 103</td></tr>
                        <tr><td>Standard Double</td><td>KES 5,000</td><td>2</td><td>104, 105, 201, 202, 203</td></tr>
                        <tr><td>Deluxe Room</td><td>KES 7,500</td><td>2</td><td>204, 205, 301, 302</td></tr>
                        <tr><td>Executive Suite</td><td>KES 12,000</td><td>3</td><td>303</td></tr>
                        <tr><td>Family Room</td><td>KES 9,000</td><td>4</td><td>304, 305</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 4B: Registers/Tills -->
        <section id="registers">
            <h2 class="section-title"><i class="bi bi-cash-stack me-2"></i>4B. Sample Registers/Tills</h2>
            <div class="section-content">
                <p class="text-muted">For businesses with multiple checkout points (supermarkets, bars, restaurants with multiple counters)</p>
                
                <h4>Supermarket Setup</h4>
                <table class="data-table">
                    <thead><tr><th>Register #</th><th>Name</th><th>Type</th><th>Location</th><th>Default Float</th></tr></thead>
                    <tbody>
                        <tr><td><code>REG-01</code></td><td>Checkout 1</td><td>Retail</td><td>Main Store</td><td>KES 5,000</td></tr>
                        <tr><td><code>REG-02</code></td><td>Checkout 2</td><td>Retail</td><td>Main Store</td><td>KES 5,000</td></tr>
                        <tr><td><code>REG-03</code></td><td>Checkout 3</td><td>Retail</td><td>Main Store</td><td>KES 5,000</td></tr>
                        <tr><td><code>REG-04</code></td><td>Express Lane</td><td>Retail</td><td>Main Store</td><td>KES 3,000</td></tr>
                        <tr><td><code>SVC-01</code></td><td>Customer Service</td><td>Service</td><td>Main Store</td><td>KES 10,000</td></tr>
                    </tbody>
                </table>

                <h4 class="mt-4">Restaurant/Bar Setup</h4>
                <table class="data-table">
                    <thead><tr><th>Register #</th><th>Name</th><th>Type</th><th>Location</th><th>Default Float</th></tr></thead>
                    <tbody>
                        <tr><td><code>REST-01</code></td><td>Main Restaurant</td><td>Restaurant</td><td>Ground Floor</td><td>KES 5,000</td></tr>
                        <tr><td><code>BAR-01</code></td><td>Main Bar</td><td>Bar</td><td>Ground Floor</td><td>KES 8,000</td></tr>
                        <tr><td><code>BAR-02</code></td><td>Pool Bar</td><td>Bar</td><td>Pool Area</td><td>KES 5,000</td></tr>
                        <tr><td><code>BAR-03</code></td><td>Rooftop Lounge</td><td>Bar</td><td>Rooftop</td><td>KES 8,000</td></tr>
                        <tr><td><code>ROOM-01</code></td><td>Room Service</td><td>Service</td><td>Kitchen</td><td>KES 0</td></tr>
                    </tbody>
                </table>

                <h4 class="mt-4">Hotel Front Desk Setup</h4>
                <table class="data-table">
                    <thead><tr><th>Register #</th><th>Name</th><th>Type</th><th>Location</th><th>Default Float</th></tr></thead>
                    <tbody>
                        <tr><td><code>FD-01</code></td><td>Reception 1</td><td>POS</td><td>Lobby</td><td>KES 20,000</td></tr>
                        <tr><td><code>FD-02</code></td><td>Reception 2</td><td>POS</td><td>Lobby</td><td>KES 20,000</td></tr>
                        <tr><td><code>GIFT-01</code></td><td>Gift Shop</td><td>Retail</td><td>Lobby</td><td>KES 5,000</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 4C: Register Session Tests -->
        <section id="register-tests">
            <h2 class="section-title"><i class="bi bi-clock-history me-2"></i>4C. Register Session Test Scenarios</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 4C.1: Open Register Session</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Login as <code>cashier1</code></li>
                        <li><span class="checkbox"></span> Go to Registers page</li>
                        <li><span class="checkbox"></span> Select "Checkout 1" (REG-01)</li>
                        <li><span class="checkbox"></span> Click "Open Session"</li>
                        <li><span class="checkbox"></span> Count cash drawer: <span class="highlight">KES 5,000</span></li>
                        <li><span class="checkbox"></span> Enter opening balance</li>
                        <li><span class="checkbox"></span> Verify redirected to POS</li>
                        <li><span class="checkbox"></span> Verify register shows "In Use"</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 4C.2: Multiple Cashiers Same Location</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Cashier 1 opens REG-01</li>
                        <li><span class="checkbox"></span> Cashier 2 (different browser) opens REG-02</li>
                        <li><span class="checkbox"></span> Both process sales simultaneously</li>
                        <li><span class="checkbox"></span> Verify sales tracked to correct register</li>
                        <li><span class="checkbox"></span> Verify separate session totals</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 4C.3: Cash In/Out During Session</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> With open session, click "Cash Movement"</li>
                        <li><span class="checkbox"></span> Record Cash Out: KES 2,000 (reason: "Bank deposit pickup")</li>
                        <li><span class="checkbox"></span> Verify register balance reduced</li>
                        <li><span class="checkbox"></span> Record Cash In: KES 500 (reason: "Change float")</li>
                        <li><span class="checkbox"></span> Verify movements logged</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 4C.4: Close Register Session</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> After several sales, click "Close Session"</li>
                        <li><span class="checkbox"></span> View expected balance calculation</li>
                        <li><span class="checkbox"></span> Count physical cash in drawer</li>
                        <li><span class="checkbox"></span> Enter counted amount</li>
                        <li><span class="checkbox"></span> Verify variance calculated (over/short)</li>
                        <li><span class="checkbox"></span> Add closing notes if variance exists</li>
                        <li><span class="checkbox"></span> Complete close</li>
                        <li><span class="checkbox"></span> Print Z-Report</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 4C.5: Register Session Report</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Go to Reports → Register Sessions</li>
                        <li><span class="checkbox"></span> Filter by date range</li>
                        <li><span class="checkbox"></span> Filter by register</li>
                        <li><span class="checkbox"></span> View session details:
                            <ul>
                                <li>Opening/Closing balance</li>
                                <li>Cash/Card/Mobile sales breakdown</li>
                                <li>Transaction count</li>
                                <li>Variance</li>
                            </ul>
                        </li>
                        <li><span class="checkbox"></span> Export report</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 4C.6: Bar vs Restaurant Separation</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Open BAR-01 session</li>
                        <li><span class="checkbox"></span> Open REST-01 session (different user)</li>
                        <li><span class="checkbox"></span> Process bar orders on BAR-01</li>
                        <li><span class="checkbox"></span> Process restaurant orders on REST-01</li>
                        <li><span class="checkbox"></span> Verify reports show separate totals</li>
                        <li><span class="checkbox"></span> Verify combined location report</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 5: Retail POS Tests -->
        <section id="retail-pos-tests" class="page-break">
            <h2 class="section-title"><i class="bi bi-shop me-2"></i>5. Retail POS Test Scenarios</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 5.1: Barcode Scanning Sale</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Login as <code>cashier1</code></li>
                        <li><span class="checkbox"></span> Go to Retail POS</li>
                        <li><span class="checkbox"></span> Scan barcode: <code>6901234567890</code> (USB Cable - KES 500)</li>
                        <li><span class="checkbox"></span> Scan barcode: <code>6903456789012</code> (Potato Chips - KES 120)</li>
                        <li><span class="checkbox"></span> Scan barcode: <code>6904567890123</code> (Water - KES 50)</li>
                        <li><span class="checkbox"></span> Verify total: <span class="highlight">KES 670</span></li>
                        <li><span class="checkbox"></span> Pay with Cash, tendered: KES 700</li>
                        <li><span class="checkbox"></span> Verify change: KES 30</li>
                        <li><span class="checkbox"></span> Print receipt</li>
                        <li><span class="checkbox"></span> Verify stock reduced by 1 each</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.2: Manual Product Search</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Type "Earphones" in search box</li>
                        <li><span class="checkbox"></span> Select Earphones from results (KES 350)</li>
                        <li><span class="checkbox"></span> Change quantity to 2</li>
                        <li><span class="checkbox"></span> Add "Power Bank" (KES 2,500)</li>
                        <li><span class="checkbox"></span> Verify total: <span class="highlight">KES 3,200</span></li>
                        <li><span class="checkbox"></span> Complete sale</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.3: Multiple Items Same Barcode</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Scan <code>6902345678905</code> (Soap Bar - KES 80) 5 times</li>
                        <li><span class="checkbox"></span> Verify quantity shows 5</li>
                        <li><span class="checkbox"></span> Verify subtotal: <span class="highlight">KES 400</span></li>
                        <li><span class="checkbox"></span> Complete sale</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.4: Retail Sale with Discount</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add Power Bank (KES 2,500)</li>
                        <li><span class="checkbox"></span> Add Phone Charger (KES 800)</li>
                        <li><span class="checkbox"></span> Subtotal: KES 3,300</li>
                        <li><span class="checkbox"></span> Apply 15% discount</li>
                        <li><span class="checkbox"></span> Verify discount: KES 495</li>
                        <li><span class="checkbox"></span> Verify total: <span class="highlight">KES 2,805</span></li>
                        <li><span class="checkbox"></span> Complete sale</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.5: M-Pesa Payment (Retail)</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add items totaling KES 1,500</li>
                        <li><span class="checkbox"></span> Select M-Pesa payment</li>
                        <li><span class="checkbox"></span> Enter phone: <code>0712345678</code></li>
                        <li><span class="checkbox"></span> Send STK Push</li>
                        <li><span class="checkbox"></span> Verify payment received</li>
                        <li><span class="checkbox"></span> Print receipt with M-Pesa reference</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.6: Low Stock Alert</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Sell Power Bank until stock reaches 5</li>
                        <li><span class="checkbox"></span> Verify low stock warning appears</li>
                        <li><span class="checkbox"></span> Check Inventory → Low Stock report</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.7: Hold & Recall (Retail)</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add 3 retail items to cart</li>
                        <li><span class="checkbox"></span> Click "Hold Order"</li>
                        <li><span class="checkbox"></span> Enter name: "Jane Customer"</li>
                        <li><span class="checkbox"></span> Process another sale</li>
                        <li><span class="checkbox"></span> Click "Held Orders"</li>
                        <li><span class="checkbox"></span> Recall Jane's order</li>
                        <li><span class="checkbox"></span> Complete payment</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 5.8: Void Sale (Manager)</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Login as <code>manager1</code></li>
                        <li><span class="checkbox"></span> Go to Sales History</li>
                        <li><span class="checkbox"></span> Find recent retail sale</li>
                        <li><span class="checkbox"></span> Click Void</li>
                        <li><span class="checkbox"></span> Select reason: "Customer returned items"</li>
                        <li><span class="checkbox"></span> Confirm void</li>
                        <li><span class="checkbox"></span> Verify stock restored</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 6: Restaurant POS Tests -->
        <section id="pos-tests">
            <h2 class="section-title"><i class="bi bi-cart-check me-2"></i>6. Restaurant POS Test Scenarios</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 6.1: Basic Cash Sale</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Login as <code>cashier1</code></li>
                        <li><span class="checkbox"></span> Add 2x English Breakfast (KES 1,700)</li>
                        <li><span class="checkbox"></span> Add 2x Coffee (KES 400)</li>
                        <li><span class="checkbox"></span> Verify total: <span class="highlight">KES 2,100</span></li>
                        <li><span class="checkbox"></span> Pay with Cash, tendered: KES 2,500</li>
                        <li><span class="checkbox"></span> Verify change: KES 400</li>
                        <li><span class="checkbox"></span> Print receipt</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 6.2: Sale with 10% Discount</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add 1x Beef Steak (KES 1,800)</li>
                        <li><span class="checkbox"></span> Apply 10% discount</li>
                        <li><span class="checkbox"></span> Verify total: <span class="highlight">KES 1,620</span></li>
                        <li><span class="checkbox"></span> Complete sale</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 6.3: Hold & Recall Order</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add items to cart</li>
                        <li><span class="checkbox"></span> Hold order for "David Kamau"</li>
                        <li><span class="checkbox"></span> Start new sale, complete it</li>
                        <li><span class="checkbox"></span> Recall David's held order</li>
                        <li><span class="checkbox"></span> Complete held order</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 6: Restaurant Tests -->
        <section id="restaurant-tests">
            <h2 class="section-title"><i class="bi bi-cup-straw me-2"></i>7. Restaurant Test Scenarios</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 7.1: Dine-In Table Order</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Login as <code>waiter1</code></li>
                        <li><span class="checkbox"></span> Select Table 5, set 4 guests</li>
                        <li><span class="checkbox"></span> Add: 2x Grilled Chicken, 1x Fish & Chips, 4x Soft Drink</li>
                        <li><span class="checkbox"></span> Send to Kitchen</li>
                        <li><span class="checkbox"></span> Verify in KDS, mark Ready</li>
                        <li><span class="checkbox"></span> Generate bill: <span class="highlight">KES 3,950</span></li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 7.2: Order with Modifiers (Kitchen Notes)</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add 1x Beef Steak</li>
                        <li><span class="checkbox"></span> Click on item to add modifiers</li>
                        <li><span class="checkbox"></span> Select cooking: <span class="highlight">Medium Rare</span></li>
                        <li><span class="checkbox"></span> Add note: "No onions, extra sauce"</li>
                        <li><span class="checkbox"></span> Add 1x Grilled Chicken</li>
                        <li><span class="checkbox"></span> Add modifier: <span class="highlight">Extra Spicy</span></li>
                        <li><span class="checkbox"></span> Send to Kitchen</li>
                        <li><span class="checkbox"></span> Verify modifiers appear in KDS</li>
                        <li><span class="checkbox"></span> Verify printed kitchen ticket shows modifiers</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 7.3: Multiple Modifiers on Single Item</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Add 1x English Breakfast</li>
                        <li><span class="checkbox"></span> Add modifiers:
                            <ul>
                                <li>Eggs: <span class="highlight">Scrambled</span></li>
                                <li>Toast: <span class="highlight">Brown Bread</span></li>
                                <li>Bacon: <span class="highlight">Extra Crispy</span></li>
                            </ul>
                        </li>
                        <li><span class="checkbox"></span> Add special instruction: "Serve eggs separately"</li>
                        <li><span class="checkbox"></span> Send to Kitchen</li>
                        <li><span class="checkbox"></span> Verify all modifiers in KDS</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 7.4: Takeout Order</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create Takeout order</li>
                        <li><span class="checkbox"></span> Customer: Jane Wanjiku</li>
                        <li><span class="checkbox"></span> Add items, send to kitchen</li>
                        <li><span class="checkbox"></span> Mark ready, process payment</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 7.5: Kitchen Display System (KDS)</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Open KDS on kitchen screen</li>
                        <li><span class="checkbox"></span> Verify orders appear with:
                            <ul>
                                <li>Table number / Order type</li>
                                <li>Items with quantities</li>
                                <li>Modifiers highlighted</li>
                                <li>Special notes visible</li>
                                <li>Time since order placed</li>
                            </ul>
                        </li>
                        <li><span class="checkbox"></span> Mark individual items as "Preparing"</li>
                        <li><span class="checkbox"></span> Mark items as "Ready"</li>
                        <li><span class="checkbox"></span> Verify waiter notified</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 8: Sample Modifiers -->
        <section id="modifiers">
            <h2 class="section-title"><i class="bi bi-sliders me-2"></i>8. Sample Modifiers for Testing</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Product</th><th>Modifier Group</th><th>Options</th><th>Extra Cost</th></tr></thead>
                    <tbody>
                        <tr><td rowspan="3">Beef Steak</td><td>Cooking Level</td><td>Rare, Medium Rare, Medium, Medium Well, Well Done</td><td>KES 0</td></tr>
                        <tr><td>Sauce</td><td>Pepper Sauce, Mushroom Sauce, Garlic Butter, None</td><td>KES 100</td></tr>
                        <tr><td>Side</td><td>Fries, Mashed Potato, Vegetables, Rice</td><td>KES 0</td></tr>
                        <tr><td rowspan="2">Grilled Chicken</td><td>Spice Level</td><td>Mild, Medium, Spicy, Extra Spicy</td><td>KES 0</td></tr>
                        <tr><td>Extras</td><td>Extra Sauce (+50), Extra Portion (+300)</td><td>Varies</td></tr>
                        <tr><td rowspan="3">English Breakfast</td><td>Eggs</td><td>Fried, Scrambled, Poached, Boiled</td><td>KES 0</td></tr>
                        <tr><td>Toast</td><td>White Bread, Brown Bread, No Toast</td><td>KES 0</td></tr>
                        <tr><td>Bacon</td><td>Regular, Extra Crispy, No Bacon</td><td>KES 0</td></tr>
                        <tr><td rowspan="2">Coffee/Tea</td><td>Type</td><td>Black, With Milk, Latte, Cappuccino</td><td>+KES 50 for specialty</td></tr>
                        <tr><td>Sugar</td><td>No Sugar, 1 Spoon, 2 Spoons</td><td>KES 0</td></tr>
                        <tr><td>Fresh Juice</td><td>Flavor</td><td>Orange, Mango, Passion, Mixed</td><td>KES 0</td></tr>
                        <tr><td>Any Item</td><td>Special Notes</td><td>Free text: allergies, preferences</td><td>KES 0</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 9: Loyalty Points Tests -->
        <section id="loyalty-tests" class="page-break">
            <h2 class="section-title"><i class="bi bi-award me-2"></i>9. Loyalty Points Test Scenarios</h2>
            <div class="section-content">
                <h4>Loyalty Program Settings (for testing)</h4>
                <table class="data-table">
                    <thead><tr><th>Setting</th><th>Value</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>Points per KES spent</td><td>1 point per KES 100</td><td>Customer earns 1 point for every KES 100 spent</td></tr>
                        <tr><td>Points value</td><td>1 point = KES 1</td><td>Each point is worth KES 1 when redeemed</td></tr>
                        <tr><td>Minimum redemption</td><td>100 points</td><td>Must have at least 100 points to redeem</td></tr>
                        <tr><td>Expiry</td><td>12 months</td><td>Points expire after 12 months of inactivity</td></tr>
                    </tbody>
                </table>

                <div class="test-card">
                    <h4>Test 9.1: Customer Registration with Loyalty</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Go to Customers</li>
                        <li><span class="checkbox"></span> Add new customer: David Kamau</li>
                        <li><span class="checkbox"></span> Phone: <code>0712345678</code></li>
                        <li><span class="checkbox"></span> Enable loyalty program</li>
                        <li><span class="checkbox"></span> Verify loyalty card number generated</li>
                        <li><span class="checkbox"></span> Verify starting balance: <span class="highlight">0 points</span></li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 9.2: Earning Points on Purchase</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create sale for David Kamau</li>
                        <li><span class="checkbox"></span> Add items totaling: <span class="highlight">KES 2,500</span></li>
                        <li><span class="checkbox"></span> Complete payment</li>
                        <li><span class="checkbox"></span> Verify points earned: <span class="highlight">25 points</span></li>
                        <li><span class="checkbox"></span> Check receipt shows points earned</li>
                        <li><span class="checkbox"></span> Verify customer balance: 25 points</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 9.3: Accumulating Points</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Make 2nd purchase: KES 5,000 → +50 points</li>
                        <li><span class="checkbox"></span> Make 3rd purchase: KES 3,000 → +30 points</li>
                        <li><span class="checkbox"></span> Verify total balance: <span class="highlight">105 points</span></li>
                        <li><span class="checkbox"></span> Check customer profile shows transaction history</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 9.4: Redeeming Points</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create sale: KES 1,500</li>
                        <li><span class="checkbox"></span> Select customer: David Kamau</li>
                        <li><span class="checkbox"></span> Click "Redeem Points"</li>
                        <li><span class="checkbox"></span> Redeem 100 points (KES 100 discount)</li>
                        <li><span class="checkbox"></span> Verify new total: <span class="highlight">KES 1,400</span></li>
                        <li><span class="checkbox"></span> Complete payment</li>
                        <li><span class="checkbox"></span> Verify points deducted: 100</li>
                        <li><span class="checkbox"></span> Verify new points earned: 14 (on KES 1,400)</li>
                        <li><span class="checkbox"></span> Final balance: <span class="highlight">19 points</span> (105 - 100 + 14)</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 9.5: Points on Voided Sale</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create sale for loyalty customer: KES 2,000</li>
                        <li><span class="checkbox"></span> Complete sale (earns 20 points)</li>
                        <li><span class="checkbox"></span> Note customer balance</li>
                        <li><span class="checkbox"></span> Void the sale</li>
                        <li><span class="checkbox"></span> Verify points reversed (deducted 20 points)</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 9.6: Loyalty Report</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Go to Reports → Loyalty</li>
                        <li><span class="checkbox"></span> View points earned report</li>
                        <li><span class="checkbox"></span> View points redeemed report</li>
                        <li><span class="checkbox"></span> View top loyalty customers</li>
                        <li><span class="checkbox"></span> Export report to PDF</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 9.7: Customer Lookup by Phone</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> At POS, enter phone: <code>0712345678</code></li>
                        <li><span class="checkbox"></span> Verify customer auto-selected</li>
                        <li><span class="checkbox"></span> Verify points balance displayed</li>
                        <li><span class="checkbox"></span> Complete sale with loyalty applied</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 10: Promotions Tests -->
        <section id="promotions-tests">
            <h2 class="section-title"><i class="bi bi-percent me-2"></i>10. Promotions Test Scenarios</h2>
            <div class="section-content">
                <h4>Sample Promotions to Create</h4>
                <table class="data-table">
                    <thead><tr><th>Promo Name</th><th>Type</th><th>Value</th><th>Conditions</th></tr></thead>
                    <tbody>
                        <tr><td>WELCOME10</td><td>Percentage</td><td>10% off</td><td>First-time customers only</td></tr>
                        <tr><td>LUNCH20</td><td>Percentage</td><td>20% off</td><td>11AM-2PM, Main Course only</td></tr>
                        <tr><td>FLAT500</td><td>Fixed Amount</td><td>KES 500 off</td><td>Orders above KES 5,000</td></tr>
                        <tr><td>BOGO</td><td>Buy One Get One</td><td>Free item</td><td>Buy 2 Soft Drinks, get 1 free</td></tr>
                        <tr><td>WEEKEND15</td><td>Percentage</td><td>15% off</td><td>Saturday & Sunday only</td></tr>
                    </tbody>
                </table>

                <div class="test-card">
                    <h4>Test 10.1: Apply Promo Code</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create sale: KES 3,000</li>
                        <li><span class="checkbox"></span> Click "Apply Promo"</li>
                        <li><span class="checkbox"></span> Enter code: <code>WELCOME10</code></li>
                        <li><span class="checkbox"></span> Verify 10% discount applied: KES 300</li>
                        <li><span class="checkbox"></span> Verify total: <span class="highlight">KES 2,700</span></li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 10.2: Minimum Order Promo</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create sale: KES 4,000</li>
                        <li><span class="checkbox"></span> Try code: <code>FLAT500</code></li>
                        <li><span class="checkbox"></span> Verify rejected (minimum KES 5,000)</li>
                        <li><span class="checkbox"></span> Add items to reach KES 5,500</li>
                        <li><span class="checkbox"></span> Apply <code>FLAT500</code> again</li>
                        <li><span class="checkbox"></span> Verify KES 500 discount applied</li>
                        <li><span class="checkbox"></span> Total: <span class="highlight">KES 5,000</span></li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 10.3: Time-Based Promo</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> During lunch hours (11AM-2PM)</li>
                        <li><span class="checkbox"></span> Add Main Course items</li>
                        <li><span class="checkbox"></span> Apply <code>LUNCH20</code></li>
                        <li><span class="checkbox"></span> Verify 20% discount on main course only</li>
                        <li><span class="checkbox"></span> Try outside lunch hours - verify rejected</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 11: Booking Tests -->
        <section id="booking-tests" class="page-break">
            <h2 class="section-title"><i class="bi bi-calendar-check me-2"></i>11. Room Booking Tests</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 7.1: Walk-In Booking & Check-In</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Login as <code>frontdesk</code></li>
                        <li><span class="checkbox"></span> New Booking: David Kamau, Deluxe Room</li>
                        <li><span class="checkbox"></span> Check-in: Today, Check-out: Tomorrow</li>
                        <li><span class="checkbox"></span> Collect deposit: KES 5,000</li>
                        <li><span class="checkbox"></span> Check-in guest</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 7.2: Room Folio Charge</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Find checked-in guest</li>
                        <li><span class="checkbox"></span> Add Room Service charge: KES 1,050</li>
                        <li><span class="checkbox"></span> Verify folio balance updated</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 7.3: Check-Out</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Review folio balance</li>
                        <li><span class="checkbox"></span> Process final payment</li>
                        <li><span class="checkbox"></span> Complete check-out</li>
                        <li><span class="checkbox"></span> Verify room status: Dirty</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 8: WhatsApp Tests -->
        <section id="whatsapp-tests">
            <h2 class="section-title"><i class="bi bi-whatsapp me-2" style="color:#25D366;"></i>8. WhatsApp Tests</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 8.1: Order via WhatsApp</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Send: <code>MENU</code></li>
                        <li><span class="checkbox"></span> Select category, add items</li>
                        <li><span class="checkbox"></span> Send: <code>CHECKOUT</code></li>
                        <li><span class="checkbox"></span> Choose Pickup, enter name</li>
                        <li><span class="checkbox"></span> Confirm order</li>
                        <li><span class="checkbox"></span> Verify order in system</li>
                    </ul>
                </div>
                <div class="test-card">
                    <h4>Test 8.2: Staff Inbox</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Go to WhatsApp Inbox</li>
                        <li><span class="checkbox"></span> View conversations</li>
                        <li><span class="checkbox"></span> Send manual reply</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 9: Delivery Tests -->
        <section id="delivery-tests" class="page-break">
            <h2 class="section-title"><i class="bi bi-truck me-2"></i>9. Delivery Tests</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 9.1: Delivery Order Flow</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create delivery order for Mary Akinyi</li>
                        <li><span class="checkbox"></span> Address: 22 Uhuru Highway</li>
                        <li><span class="checkbox"></span> Assign rider: Peter Rider</li>
                        <li><span class="checkbox"></span> Login as <code>rider1</code></li>
                        <li><span class="checkbox"></span> Mark: Picked Up → In Transit → Delivered</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Section 10: Payment Tests -->
        <section id="payment-tests">
            <h2 class="section-title"><i class="bi bi-credit-card me-2"></i>10. Payment Tests</h2>
            <div class="section-content">
                <div class="test-card">
                    <h4>Test 10.1: M-Pesa STK Push</h4>
                    <ul class="checklist">
                        <li><span class="checkbox"></span> Create sale: KES 500</li>
                        <li><span class="checkbox"></span> Select M-Pesa, enter phone</li>
                        <li><span class="checkbox"></span> Send STK Push</li>
                        <li><span class="checkbox"></span> Verify payment confirmation</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Final Checklist -->
        <section class="page-break">
            <h2 class="section-title"><i class="bi bi-check2-all me-2"></i>Final System Checklist</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>✓</th><th>Module</th><th>Status</th><th>Tested By</th><th>Date</th></tr></thead>
                    <tbody>
                        <tr><td><span class="checkbox"></span></td><td>User Authentication</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Point of Sale</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Restaurant & KDS</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Inventory</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Room Bookings</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Delivery</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>WhatsApp</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Payments</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Housekeeping</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Maintenance</td><td></td><td></td><td></td></tr>
                        <tr><td><span class="checkbox"></span></td><td>Reports</td><td></td><td></td><td></td></tr>
                    </tbody>
                </table>
                <div class="mt-4 p-3 border rounded">
                    <p><strong>Approved By:</strong> _________________________ <strong>Date:</strong> _____________</p>
                    <p><strong>Signature:</strong> _________________________</p>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
