<?php
/**
 * WAPOS - User Manual
 * Comprehensive guide for using the system
 */
require_once 'includes/bootstrap.php';
$pageTitle = 'User Manual';
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-book me-2 text-primary"></i>User Manual</h2>
            <p class="text-muted mb-0">Complete guide for using WAPOS</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-primary no-print">
            <i class="bi bi-printer me-2"></i>Print Manual
        </button>
    </div>

    <div class="row">
        <!-- Table of Contents -->
        <div class="col-lg-3 no-print">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Contents</h6>
                </div>
                <div class="card-body p-0">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="#install-app">📱 Installing the App</a>
                        <a class="nav-link" href="#offline-mode">📴 Working Offline</a>
                        <a class="nav-link" href="#getting-started">🚀 Getting Started</a>
                        <a class="nav-link" href="#pos-sales">💰 Making Sales (POS)</a>
                        <a class="nav-link" href="#restaurant">🍽️ Restaurant Orders</a>
                        <a class="nav-link" href="#bar-pos">🍸 Bar & Beverage</a>
                        <a class="nav-link" href="#property">🏨 Property Management</a>
                        <a class="nav-link" href="#inventory">📦 Inventory Management</a>
                        <a class="nav-link" href="#time-clock">⏰ Time Clock</a>
                        <a class="nav-link" href="#qr-codes">📲 QR Codes</a>
                        <a class="nav-link" href="#reports">📊 Reports</a>
                        <a class="nav-link" href="#settings">⚙️ Settings</a>
                        <a class="nav-link" href="#troubleshooting">🔧 Troubleshooting</a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Manual Content -->
        <div class="col-lg-9">
            <!-- Section 1: Installing the App -->
            <section id="install-app" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-download me-2 text-primary"></i>Installing WAPOS on Your Device</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>WAPOS is a Progressive Web App (PWA)</strong> - You can install it on any device (Windows, Mac, Android, iPhone) without downloading from an app store. It works just like a native app!
                    </div>

                    <h5 class="mt-4"><i class="bi bi-windows me-2"></i>Installing on Windows (Chrome/Edge)</h5>
                    <div class="row align-items-center mb-4">
                        <div class="col-md-8">
                            <ol>
                                <li>Open WAPOS in <strong>Google Chrome</strong> or <strong>Microsoft Edge</strong></li>
                                <li>Look for the <strong>install icon</strong> (⊕) in the address bar (right side)</li>
                                <li>Click it and select <strong>"Install"</strong></li>
                                <li>WAPOS will now appear in your Start Menu and Desktop</li>
                            </ol>
                            <p class="text-muted small">
                                <i class="bi bi-lightbulb me-1"></i>
                                Alternatively, click the <strong>3-dot menu</strong> (⋮) → <strong>"Install WAPOS"</strong>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="bi bi-box-arrow-in-down text-primary" style="font-size: 3rem;"></i>
                                <p class="mb-0 mt-2 small">Look for this icon in address bar</p>
                            </div>
                        </div>
                    </div>

                    <h5><i class="bi bi-android2 me-2"></i>Installing on Android</h5>
                    <div class="row align-items-center mb-4">
                        <div class="col-md-8">
                            <ol>
                                <li>Open WAPOS in <strong>Chrome</strong> browser</li>
                                <li>Tap the <strong>3-dot menu</strong> (⋮) at top right</li>
                                <li>Tap <strong>"Add to Home screen"</strong> or <strong>"Install app"</strong></li>
                                <li>Tap <strong>"Install"</strong> to confirm</li>
                                <li>WAPOS icon will appear on your home screen</li>
                            </ol>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="bi bi-phone text-success" style="font-size: 3rem;"></i>
                                <p class="mb-0 mt-2 small">Works on any Android phone/tablet</p>
                            </div>
                        </div>
                    </div>

                    <h5><i class="bi bi-apple me-2"></i>Installing on iPhone/iPad</h5>
                    <div class="row align-items-center mb-4">
                        <div class="col-md-8">
                            <ol>
                                <li>Open WAPOS in <strong>Safari</strong> browser</li>
                                <li>Tap the <strong>Share button</strong> <i class="bi bi-box-arrow-up"></i> (bottom of screen)</li>
                                <li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>
                                <li>Tap <strong>"Add"</strong> in the top right</li>
                                <li>WAPOS icon will appear on your home screen</li>
                            </ol>
                            <div class="alert alert-warning small mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Important:</strong> On iOS, you must use Safari. Chrome on iPhone doesn't support PWA installation.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="bi bi-box-arrow-up text-secondary" style="font-size: 3rem;"></i>
                                <p class="mb-0 mt-2 small">Use Safari's Share button</p>
                            </div>
                        </div>
                    </div>

                    <h5><i class="bi bi-laptop me-2"></i>Installing on Mac</h5>
                    <ol>
                        <li>Open WAPOS in <strong>Google Chrome</strong> or <strong>Microsoft Edge</strong></li>
                        <li>Click the <strong>install icon</strong> in the address bar, or</li>
                        <li>Click <strong>Chrome menu</strong> (⋮) → <strong>"Install WAPOS..."</strong></li>
                        <li>Click <strong>"Install"</strong></li>
                        <li>WAPOS will appear in your Applications folder and Dock</li>
                    </ol>

                    <div class="alert alert-success mt-4">
                        <h6><i class="bi bi-check-circle me-2"></i>Benefits of Installing</h6>
                        <ul class="mb-0">
                            <li><strong>Quick Access</strong> - Launch from desktop/home screen like any app</li>
                            <li><strong>Works Offline</strong> - Continue working even without internet</li>
                            <li><strong>Full Screen</strong> - No browser address bar, more screen space</li>
                            <li><strong>Auto Updates</strong> - Always get the latest version automatically</li>
                            <li><strong>Notifications</strong> - Receive alerts even when app is closed</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Section 2: Working Offline -->
            <section id="offline-mode" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-wifi-off me-2 text-warning"></i>Working Offline</h4>
                </div>
                <div class="card-body">
                    <p>WAPOS is designed to work even when your internet connection is down. Here's how it works:</p>

                    <h5 class="mt-4">Understanding the Status Indicator</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <span class="badge bg-success mb-2"><i class="bi bi-wifi me-1"></i> Online</span>
                                <p class="mb-0 small">You're connected to the internet. All data syncs in real-time to the server.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <span class="badge bg-warning text-dark mb-2"><i class="bi bi-wifi-off me-1"></i> Offline</span>
                                <p class="mb-0 small">No internet connection. Data is saved locally and will sync when you're back online.</p>
                            </div>
                        </div>
                    </div>

                    <h5>What Works Offline?</h5>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr><th>Feature</th><th>Offline Support</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Making Sales (POS)</td><td><span class="badge bg-success">✓ Full</span></td><td>Sales saved locally, sync when online</td></tr>
                            <tr><td>Viewing Products</td><td><span class="badge bg-success">✓ Full</span></td><td>Product catalog cached</td></tr>
                            <tr><td>Restaurant Orders</td><td><span class="badge bg-success">✓ Full</span></td><td>Orders queue locally</td></tr>
                            <tr><td>Viewing Reports</td><td><span class="badge bg-warning">Partial</span></td><td>Previously viewed reports cached</td></tr>
                            <tr><td>Adding Products</td><td><span class="badge bg-danger">✗ No</span></td><td>Requires internet</td></tr>
                            <tr><td>User Management</td><td><span class="badge bg-danger">✗ No</span></td><td>Requires internet</td></tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4">How Offline Sales Work</h5>
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-light rounded p-3">
                                <i class="bi bi-cart-plus text-primary" style="font-size: 2.5rem;"></i>
                                <h6 class="mt-2">1. Make Sale</h6>
                                <p class="small mb-0">Process sales normally, even offline</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-light rounded p-3">
                                <i class="bi bi-hdd text-warning" style="font-size: 2.5rem;"></i>
                                <h6 class="mt-2">2. Saved Locally</h6>
                                <p class="small mb-0">Sale stored on your device</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-light rounded p-3">
                                <i class="bi bi-cloud-arrow-up text-success" style="font-size: 2.5rem;"></i>
                                <h6 class="mt-2">3. Auto Sync</h6>
                                <p class="small mb-0">Uploads when internet returns</p>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4">Viewing Pending Transactions</h5>
                    <p>To see transactions waiting to sync:</p>
                    <ol>
                        <li>Look at the <strong>Sync</strong> button in the top bar</li>
                        <li>A <span class="badge bg-danger">number</span> shows pending items</li>
                        <li>Click the <strong>cloud icon</strong> <i class="bi bi-cloud-arrow-up"></i> to view details</li>
                        <li>When online, click <strong>"Sync Now"</strong> to force sync</li>
                    </ol>

                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Tip:</strong> Pending transactions sync automatically within seconds of reconnecting. You don't need to do anything!
                    </div>
                </div>
            </section>

            <!-- Section 3: Getting Started -->
            <section id="getting-started" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-rocket-takeoff me-2 text-success"></i>Getting Started</h4>
                </div>
                <div class="card-body">
                    <h5>Logging In</h5>
                    <ol>
                        <li>Open WAPOS in your browser or installed app</li>
                        <li>Enter your <strong>Username</strong> and <strong>Password</strong></li>
                        <li>Click <strong>"Login"</strong></li>
                        <li>You'll be taken to your role-specific dashboard</li>
                    </ol>

                    <h5 class="mt-4">Understanding Your Dashboard</h5>
                    <p>Your dashboard shows information relevant to your role:</p>
                    <ul>
                        <li><strong>Admin/Manager:</strong> Sales overview, staff activity, inventory alerts, KPIs</li>
                        <li><strong>Cashier:</strong> Quick access to POS, today's sales summary</li>
                        <li><strong>Waiter:</strong> Active orders, table status, kitchen updates</li>
                        <li><strong>Bartender:</strong> Bar POS, open tabs, happy hour status, time clock</li>
                        <li><strong>Accountant:</strong> Financial summaries, P&L, balance sheet</li>
                        <li><strong>Front Desk:</strong> Room bookings, check-ins, guest folios</li>
                        <li><strong>Housekeeping:</strong> Room status, cleaning tasks, supplies</li>
                        <li><strong>Maintenance:</strong> Work orders, task assignments</li>
                    </ul>

                    <h5 class="mt-4">Navigation</h5>
                    <p>Use the <strong>sidebar menu</strong> (left side) to access different sections:</p>
                    <ul>
                        <li><i class="bi bi-speedometer2 me-2"></i><strong>Dashboard</strong> - Your home screen</li>
                        <li><i class="bi bi-cart me-2"></i><strong>Retail POS</strong> - Process sales</li>
                        <li><i class="bi bi-cup-straw me-2"></i><strong>Restaurant</strong> - Table orders</li>
                        <li><i class="bi bi-box-seam me-2"></i><strong>Products</strong> - Inventory</li>
                        <li><i class="bi bi-people me-2"></i><strong>Customers</strong> - Customer database</li>
                        <li><i class="bi bi-bar-chart me-2"></i><strong>Reports</strong> - Analytics</li>
                    </ul>
                </div>
            </section>

            <!-- Section 4: POS Sales -->
            <section id="pos-sales" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Making Sales (POS)</h4>
                </div>
                <div class="card-body">
                    <h5>Basic Sale Process</h5>
                    <ol>
                        <li>Go to <strong>Retail POS</strong> from the sidebar</li>
                        <li><strong>Add products</strong> by:
                            <ul>
                                <li>Scanning barcode (if you have a scanner)</li>
                                <li>Searching by name in the search box</li>
                                <li>Clicking product tiles</li>
                            </ul>
                        </li>
                        <li>Adjust <strong>quantity</strong> if needed (click +/- or type)</li>
                        <li>Apply <strong>discount</strong> if applicable</li>
                        <li>Click <strong>"Charge"</strong> or <strong>"Pay"</strong></li>
                        <li>Select <strong>payment method</strong> (Cash, Card, Mobile Money)</li>
                        <li>For cash: Enter amount tendered, system calculates change</li>
                        <li>Click <strong>"Complete Sale"</strong></li>
                        <li><strong>Print receipt</strong> or email to customer</li>
                    </ol>

                    <h5 class="mt-4">Payment Methods</h5>
                    <table class="table table-bordered">
                        <tr><td><i class="bi bi-cash me-2"></i><strong>Cash</strong></td><td>Enter amount received, get change calculated</td></tr>
                        <tr><td><i class="bi bi-credit-card me-2"></i><strong>Card</strong></td><td>Process via your card terminal</td></tr>
                        <tr><td><i class="bi bi-phone me-2"></i><strong>Mobile Money</strong></td><td>M-Pesa, Airtel Money, etc.</td></tr>
                        <tr><td><i class="bi bi-wallet2 me-2"></i><strong>Split Payment</strong></td><td>Combine multiple payment methods</td></tr>
                    </table>

                    <h5 class="mt-4">Holding Orders</h5>
                    <p>Need to pause a sale? (e.g., customer forgot wallet)</p>
                    <ol>
                        <li>Click <strong>"Hold"</strong> button</li>
                        <li>Enter a name/reference for the order</li>
                        <li>Start a new sale for other customers</li>
                        <li>Click <strong>"Held Orders"</strong> to recall later</li>
                    </ol>
                </div>
            </section>

            <!-- Section 5: Restaurant -->
            <section id="restaurant" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-cup-straw me-2 text-info"></i>Restaurant Orders</h4>
                </div>
                <div class="card-body">
                    <h5>Taking a Table Order</h5>
                    <ol>
                        <li>Go to <strong>Restaurant</strong> from sidebar</li>
                        <li>Click on a <strong>table</strong> (or create new order)</li>
                        <li>Select <strong>number of guests</strong></li>
                        <li>Add menu items to the order</li>
                        <li>Add <strong>modifiers</strong> if needed (e.g., "Medium Rare", "No onions")</li>
                        <li>Click <strong>"Send to Kitchen"</strong></li>
                        <li>Order appears on Kitchen Display System (KDS)</li>
                    </ol>

                    <h5 class="mt-4">Order Types</h5>
                    <ul>
                        <li><strong>Dine-In:</strong> Customer eating at a table</li>
                        <li><strong>Takeaway:</strong> Customer taking food to go</li>
                        <li><strong>Delivery:</strong> Food being delivered</li>
                        <li><strong>Room Service:</strong> For hotel guests</li>
                    </ul>

                    <h5 class="mt-4">Closing a Table</h5>
                    <ol>
                        <li>Click on the table with the order</li>
                        <li>Click <strong>"Generate Bill"</strong></li>
                        <li>Review the bill with customer</li>
                        <li>Process payment</li>
                        <li>Table becomes available again</li>
                    </ol>
                </div>
            </section>

            <!-- Section 6: Bar & Beverage -->
            <section id="bar-pos" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-cup-straw me-2 text-info"></i>Bar & Beverage</h4>
                </div>
                <div class="card-body">
                    <h5>Bar POS</h5>
                    <p>The dedicated Bar POS is designed for fast drink service:</p>
                    <ol>
                        <li>Go to <strong>Restaurant → Bar POS</strong> from sidebar</li>
                        <li>Select products by category (Spirits, Beer, Wine, Cocktails)</li>
                        <li>For portioned items (spirits), select the <strong>portion size</strong> (tot, shot, glass)</li>
                        <li>Add to cart and process payment</li>
                    </ol>

                    <h5 class="mt-4">Bar Tabs</h5>
                    <p>Allow customers to run a tab:</p>
                    <ol>
                        <li>Click <strong>"Open Tab"</strong> when starting an order</li>
                        <li>Enter customer name or room number</li>
                        <li>Add drinks throughout the session</li>
                        <li>Click <strong>"Close Tab"</strong> to settle the bill</li>
                    </ol>

                    <h5 class="mt-4">Happy Hour</h5>
                    <p>Configure automatic discounts during happy hour:</p>
                    <ol>
                        <li>Go to <strong>Settings → Happy Hour</strong></li>
                        <li>Set start and end times</li>
                        <li>Choose discount type (percentage or fixed)</li>
                        <li>Select which products or categories apply</li>
                        <li>Discounts apply automatically during configured times</li>
                    </ol>

                    <h5 class="mt-4">Open Bottle Tracking</h5>
                    <p>Track opened bottles for variance control:</p>
                    <ol>
                        <li>Go to <strong>Bar Management</strong></li>
                        <li>Click <strong>"Open Bottle"</strong> when opening new stock</li>
                        <li>System tracks remaining ml after each pour</li>
                        <li>View variance reports to identify wastage</li>
                    </ol>

                    <h5 class="mt-4">Bar KDS (Kitchen Display)</h5>
                    <p>View drink orders on a dedicated screen:</p>
                    <ul>
                        <li>Orders appear in real-time</li>
                        <li>Click to mark items as <strong>In Progress</strong> or <strong>Ready</strong></li>
                        <li>Color-coded by wait time</li>
                    </ul>
                </div>
            </section>

            <!-- Section 7: Property Management -->
            <section id="property" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-building me-2 text-purple"></i>Property Management</h4>
                </div>
                <div class="card-body">
                    <h5>Room Bookings</h5>
                    <ol>
                        <li>Go to <strong>Property → Rooms</strong></li>
                        <li>Click <strong>"New Booking"</strong></li>
                        <li>Select room, dates, and guest details</li>
                        <li>Confirm booking and send confirmation</li>
                    </ol>

                    <h5 class="mt-4">Guest Check-In/Out</h5>
                    <ol>
                        <li>Find the booking in the list</li>
                        <li>Click <strong>"Check In"</strong> to register arrival</li>
                        <li>At departure, click <strong>"Check Out"</strong></li>
                        <li>Review folio and process final payment</li>
                    </ol>

                    <h5 class="mt-4">Guest Self Check-In (QR Code)</h5>
                    <p>Guests can check in using their phone:</p>
                    <ol>
                        <li>Generate a check-in QR code from <strong>QR Generator</strong></li>
                        <li>Guest scans QR code with their phone</li>
                        <li>Guest enters booking number to verify</li>
                        <li>Check-in is recorded automatically</li>
                    </ol>

                    <h5 class="mt-4">Room Service</h5>
                    <ol>
                        <li>Guest calls or orders via digital menu</li>
                        <li>Create order linked to room number</li>
                        <li>Charges added to guest folio automatically</li>
                    </ol>

                    <h5 class="mt-4">Housekeeping Tasks</h5>
                    <ol>
                        <li>Go to <strong>Property → Housekeeping</strong></li>
                        <li>View room status board</li>
                        <li>Assign cleaning tasks to staff</li>
                        <li>Mark rooms as clean when complete</li>
                    </ol>
                </div>
            </section>

            <!-- Section 8: Inventory -->
            <section id="inventory" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-box-seam me-2 text-warning"></i>Inventory Management</h4>
                </div>
                <div class="card-body">
                    <h5>Viewing Products</h5>
                    <p>Go to <strong>Products</strong> to see all inventory items with:</p>
                    <ul>
                        <li>Current stock levels</li>
                        <li>Prices</li>
                        <li>Categories</li>
                        <li>Low stock alerts</li>
                    </ul>

                    <h5 class="mt-4">Adding New Products</h5>
                    <ol>
                        <li>Click <strong>"Add Product"</strong></li>
                        <li>Fill in product details (name, price, category)</li>
                        <li>Add barcode/SKU if applicable</li>
                        <li>Set initial stock quantity</li>
                        <li>Set low stock alert threshold</li>
                        <li>Click <strong>"Save"</strong></li>
                    </ol>

                    <h5 class="mt-4">Stock Adjustments</h5>
                    <p>To adjust stock (received goods, damaged items, etc.):</p>
                    <ol>
                        <li>Find the product</li>
                        <li>Click <strong>"Adjust Stock"</strong></li>
                        <li>Enter quantity change (+/-)</li>
                        <li>Select reason (Purchase, Damage, Transfer, etc.)</li>
                        <li>Add notes if needed</li>
                        <li>Click <strong>"Save"</strong></li>
                    </ol>
                </div>
            </section>

            <!-- Section 9: Time Clock -->
            <section id="time-clock" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-clock-history me-2 text-danger"></i>Time Clock</h4>
                </div>
                <div class="card-body">
                    <h5>Clocking In/Out</h5>
                    <p>Employees can track their work hours:</p>
                    <ol>
                        <li>Go to <strong>Time Clock</strong> from the sidebar</li>
                        <li>Enter your <strong>PIN</strong> (assigned by manager)</li>
                        <li>Click <strong>"Clock In"</strong> to start your shift</li>
                        <li>At the end of your shift, click <strong>"Clock Out"</strong></li>
                    </ol>

                    <h5 class="mt-4">Viewing Your Hours</h5>
                    <ul>
                        <li>See today's hours and current status</li>
                        <li>View weekly summary of hours worked</li>
                        <li>Check overtime hours if applicable</li>
                    </ul>

                    <h5 class="mt-4">Manager Functions</h5>
                    <p>Managers can:</p>
                    <ul>
                        <li>View all employee time entries</li>
                        <li>Edit or correct clock times</li>
                        <li>Generate timesheet reports</li>
                        <li>Approve overtime</li>
                        <li>Set employee PINs</li>
                    </ul>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Tip:</strong> The time clock can also be accessed from the Bartender Dashboard for quick clock in/out.
                    </div>
                </div>
            </section>

            <!-- Section 10: QR Codes -->
            <section id="qr-codes" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-qr-code me-2 text-success"></i>QR Codes</h4>
                </div>
                <div class="card-body">
                    <h5>QR Code Generator</h5>
                    <p>Generate QR codes for various purposes:</p>
                    <ol>
                        <li>Go to <strong>Restaurant → QR Generator</strong></li>
                        <li>Select the QR code type</li>
                        <li>Configure options</li>
                        <li>Click <strong>"Generate"</strong></li>
                        <li>Download or print the QR code</li>
                    </ol>

                    <h5 class="mt-4">Available QR Code Types</h5>
                    <table class="table table-bordered">
                        <tr><td><strong>Digital Menu</strong></td><td>Guests scan to view menu on their phone</td></tr>
                        <tr><td><strong>Table Order</strong></td><td>Guests can order directly from their table</td></tr>
                        <tr><td><strong>Guest Check-In</strong></td><td>Hotel guests self check-in with booking number</td></tr>
                        <tr><td><strong>Loyalty Card</strong></td><td>Customer scans to link loyalty account</td></tr>
                        <tr><td><strong>Feedback</strong></td><td>Customers leave reviews and ratings</td></tr>
                        <tr><td><strong>WiFi</strong></td><td>Auto-connect guests to WiFi network</td></tr>
                        <tr><td><strong>Receipt</strong></td><td>Digital receipt access</td></tr>
                        <tr><td><strong>Payment</strong></td><td>Mobile payment link</td></tr>
                    </table>

                    <h5 class="mt-4">Printing Table QR Codes</h5>
                    <ol>
                        <li>Go to <strong>Restaurant → Digital Menu QR</strong></li>
                        <li>Select tables to generate codes for</li>
                        <li>Print and place on tables</li>
                        <li>Guests scan to view menu and order</li>
                    </ol>

                    <h5 class="mt-4">Barcode Scanning</h5>
                    <p>The system supports barcode scanning for products:</p>
                    <ul>
                        <li><strong>USB Scanner:</strong> Plug in and scan directly into search field</li>
                        <li><strong>Camera Scanner:</strong> Use device camera to scan barcodes</li>
                        <li><strong>Manual Entry:</strong> Type barcode number if scanner unavailable</li>
                    </ul>
                </div>
            </section>

            <!-- Section 11: Reports -->
            <section id="reports" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Reports</h4>
                </div>
                <div class="card-body">
                    <h5>Available Reports</h5>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr><th>Report</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Sales Report</strong></td><td>Daily, weekly, monthly sales summaries</td></tr>
                            <tr><td><strong>Product Report</strong></td><td>Best sellers, slow movers, stock levels</td></tr>
                            <tr><td><strong>Staff Report</strong></td><td>Sales by employee, performance metrics</td></tr>
                            <tr><td><strong>Payment Report</strong></td><td>Breakdown by payment method</td></tr>
                            <tr><td><strong>Tax Report</strong></td><td>Tax collected, ready for filing</td></tr>
                            <tr><td><strong>Inventory Report</strong></td><td>Stock movements, valuations</td></tr>
                            <tr><td><strong>Bar Variance Report</strong></td><td>Expected vs actual pour usage</td></tr>
                            <tr><td><strong>Timesheet Report</strong></td><td>Employee hours and attendance</td></tr>
                            <tr><td><strong>Register Report</strong></td><td>X, Y, Z reports for cash reconciliation</td></tr>
                            <tr><td><strong>Location Analytics</strong></td><td>Multi-location performance comparison</td></tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4">Exporting Reports</h5>
                    <p>Most reports can be exported as:</p>
                    <ul>
                        <li><strong>PDF</strong> - For printing or sharing</li>
                        <li><strong>Excel</strong> - For further analysis</li>
                        <li><strong>CSV</strong> - For importing to other systems</li>
                    </ul>
                </div>
            </section>

            <!-- Section 8: Settings -->
            <section id="settings" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-gear me-2 text-secondary"></i>Settings</h4>
                </div>
                <div class="card-body">
                    <h5>Accessing Settings</h5>
                    <p>Click your <strong>username</strong> in the top right → <strong>Settings</strong></p>

                    <h5 class="mt-4">Common Settings</h5>
                    <ul>
                        <li><strong>Profile:</strong> Change your password, update contact info</li>
                        <li><strong>Receipt:</strong> Customize receipt header/footer</li>
                        <li><strong>Tax:</strong> Configure tax rates</li>
                        <li><strong>Currency:</strong> Set currency format</li>
                        <li><strong>Notifications:</strong> Email/SMS alert preferences</li>
                    </ul>
                </div>
            </section>

            <!-- Section 9: Troubleshooting -->
            <section id="troubleshooting" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="bi bi-wrench me-2 text-danger"></i>Troubleshooting</h4>
                </div>
                <div class="card-body">
                    <div class="accordion" id="troubleshootingAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#t1">
                                    App won't load / shows blank page
                                </button>
                            </h2>
                            <div id="t1" class="accordion-collapse collapse show" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Clear browser cache (Ctrl+Shift+Delete)</li>
                                        <li>Try a different browser</li>
                                        <li>Check your internet connection</li>
                                        <li>Try accessing in incognito/private mode</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t2">
                                    Sales not syncing
                                </button>
                            </h2>
                            <div id="t2" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Check if status shows "Online" (green badge)</li>
                                        <li>Click the <strong>Sync</strong> button manually</li>
                                        <li>Check the offline queue for errors</li>
                                        <li>Refresh the page and try again</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t3">
                                    Receipt not printing
                                </button>
                            </h2>
                            <div id="t3" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Check printer is connected and powered on</li>
                                        <li>Verify printer is set as default</li>
                                        <li>Try printing a test page from Windows</li>
                                        <li>Check printer has paper</li>
                                        <li>Restart the printer</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t4">
                                    Forgot password
                                </button>
                            </h2>
                            <div id="t4" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Click <strong>"Forgot Password"</strong> on login page</li>
                                        <li>Enter your email address</li>
                                        <li>Check email for reset link</li>
                                        <li>Or contact your administrator</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t5">
                                    Barcode scanner not working
                                </button>
                            </h2>
                            <div id="t5" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Ensure scanner is connected via USB</li>
                                        <li>Click in the search/barcode field first</li>
                                        <li>Scanner should be in "keyboard mode"</li>
                                        <li>Test scanner in Notepad to verify it works</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary mt-4">
                        <h6><i class="bi bi-headset me-2"></i>Need More Help?</h6>
                        <p class="mb-0">Contact support or use the <strong>User Feedback</strong> feature to report issues.</p>
                    </div>
                </div>
            </section>

        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .card { break-inside: avoid; margin-bottom: 20px !important; }
    .sidebar, .top-bar, footer { display: none !important; }
    body { padding: 0 !important; }
    .col-lg-9 { width: 100% !important; }
}
.nav-link { color: #475569; padding: 8px 16px; font-size: 14px; }
.nav-link:hover { background: #f1f5f9; color: #1e293b; }
</style>

<?php include 'includes/footer.php'; ?>
