# WAPOS Training Manual
## Waesta Point of Sale System - Operational Guide

---

# Table of Contents

1. [Getting Started](#1-getting-started)
2. [Bar POS Operations](#2-bar-pos-operations)
3. [Restaurant POS Operations](#3-restaurant-pos-operations)
4. [Retail POS Operations](#4-retail-pos-operations)
5. [Inventory Management](#5-inventory-management)
6. [Customer Management](#6-customer-management)
7. [Reports & Analytics](#7-reports--analytics)
8. [Settings & Configuration](#8-settings--configuration)
9. [Troubleshooting](#9-troubleshooting)

---

# 1. Getting Started

## 1.1 Logging In

1. Open your browser and go to your WAPOS URL
2. Enter your **Username** and **Password**
3. Click **Login**
4. You'll be directed to your dashboard based on your role

## 1.2 User Roles

| Role | Access Level |
|------|-------------|
| **Super Admin** | Full system access, all settings |
| **Admin** | Management functions, reports, settings |
| **Manager** | Day-to-day operations, limited settings |
| **Cashier** | POS operations, basic functions |
| **Waiter** | Order taking, tab management |
| **Bartender** | Bar POS, drink preparation |

## 1.3 Navigation

- **Sidebar Menu**: Click the hamburger icon (☰) to expand/collapse
- **Quick Actions**: Common tasks are accessible from the dashboard
- **Search**: Use the search bar to find products, customers, or orders

---

# 2. Bar POS Operations

## 2.1 Opening a New Tab

1. Click **"New Tab"** button
2. Select tab type:
   - **Name**: Enter guest name
   - **Card**: Enter last 4 digits for pre-auth
   - **Room**: Select hotel room (if integrated)
3. Select **Bar Station**
4. Enter **Guest Count**
5. *(If enabled)* Select **Waiter** from dropdown
6. Click **"Open Tab"**

## 2.2 Adding Items to a Tab

1. Select an open tab from the left panel
2. Browse products by category or use search
3. Click on a product to add it
4. For **portioned items** (spirits, wine):
   - Select portion size (tot, shot, glass)
   - Adjust quantity if needed
   - Click **"Add to Tab"**
5. Item appears in the current order

## 2.3 Processing Payment

1. Select the tab to close
2. Click **"Pay"** button
3. Choose payment method:
   - **Cash**: Enter amount received, system calculates change
   - **Card**: Process card payment
   - **M-Pesa**: Enter customer phone number
   - **Room Charge**: Select room to charge
   - **Split**: Divide between multiple methods
4. Add **Tip** if applicable
5. Click **"Complete Payment"**
6. System prompts: **"Print receipt?"** - Click Yes/No

## 2.4 Printing Documents

Click the **⋮** menu on any tab to access print options:

| Option | Use Case |
|--------|----------|
| **Print Bill (Customer)** | Give to customer to review before paying |
| **Print Tab (Kitchen)** | Internal reference / kitchen copy |
| **Print Invoice (A4)** | Formal invoice for corporate customers |

### Workflow: Customer Asks for Bill
1. Click **⋮** → **Print Bill (Customer)**
2. Bring printed bill to customer's table
3. Customer reviews items and total
4. Customer pays (cash/card/mobile)
5. Process payment in POS
6. Print receipt when prompted
7. Tab is closed automatically

## 2.5 Tab Management

### Transfer Tab
1. Click **⋮** → **Transfer Tab**
2. Select the waiter to transfer to
3. Enter reason (optional)
4. Click **Transfer**

### Void Tab
1. Click **⋮** → **Void Tab**
2. Enter void reason
3. Confirm void (requires manager approval if configured)

### Add Tip
1. Click **"Add Tip"** button
2. Enter tip amount
3. Click **Save**

### Add Discount
1. Click **"Discount"** button
2. Enter discount amount or percentage
3. Select reason
4. Click **Apply**

## 2.6 Filtering Tabs by Waiter

*(If enabled in Settings)*

1. Use the **waiter dropdown** above the tabs list
2. Select a waiter to see only their tabs
3. Select "All Waiters" to see all tabs

---

# 3. Restaurant POS Operations

## 3.1 Table Management

1. Go to **Restaurant** → **Table Management**
2. View floor plan with table status:
   - 🟢 **Green**: Available
   - 🟡 **Yellow**: Reserved
   - 🔴 **Red**: Occupied
3. Click a table to view/manage orders

## 3.2 Taking Orders

1. Select a table or click **"New Order"**
2. Add items from the menu
3. For special requests:
   - Click the item
   - Add **Special Instructions**
   - Click **Save**
4. Click **"Send to Kitchen"** to print KOT

## 3.3 Kitchen Order Ticket (KOT)

- KOT prints automatically when order is sent
- Shows: Order #, Table #, Items, Special Instructions
- Kitchen marks items as prepared
- Waiter is notified when ready

## 3.4 Split Billing

1. Click **"Split Bill"** on the order
2. Choose split method:
   - **Equal Split**: Divide total equally
   - **By Item**: Assign items to each person
   - **Custom Amount**: Enter specific amounts
3. Process each split payment separately

---

# 4. Retail POS Operations

## 4.1 Quick Sale

1. Go to **Retail POS**
2. Scan barcode or search for product
3. Adjust quantity if needed
4. Click **"Pay"**
5. Select payment method
6. Complete transaction

## 4.2 Customer Lookup

1. Click **"Customer"** button
2. Search by name, phone, or email
3. Select customer to link to sale
4. Customer earns loyalty points (if enabled)

## 4.3 Returns & Refunds

1. Go to **Sales** → **Sales History**
2. Find the original sale
3. Click **"Return"**
4. Select items to return
5. Choose refund method
6. Process refund

---

# 5. Inventory Management

## 5.1 Stock Check

1. Go to **Inventory** → **Stock Levels**
2. View current stock for all products
3. Filter by category or location
4. Low stock items highlighted in red

## 5.2 Receiving Stock (GRN)

1. Go to **Inventory** → **Goods Received**
2. Select **Purchase Order** (if exists) or create manual GRN
3. Enter received quantities
4. Note any discrepancies
5. Click **"Receive Stock"**
6. Print GRN for records

## 5.3 Stock Adjustments

1. Go to **Inventory** → **Adjustments**
2. Click **"New Adjustment"**
3. Select product and location
4. Enter adjustment quantity (+/-)
5. Select reason (damage, theft, count correction)
6. Add notes
7. Submit for approval (if required)

## 5.4 Bar Variance Report

1. Go to **Reports** → **Bar Variance**
2. Select date range
3. View:
   - Expected usage vs actual
   - Wastage/spillage
   - High-variance products
4. Investigate discrepancies

---

# 6. Customer Management

## 6.1 Adding a Customer

1. Go to **Customers** → **Add New**
2. Enter details:
   - Name (required)
   - Phone
   - Email
   - Address
3. Click **Save**

## 6.2 Customer History

1. Go to **Customers**
2. Search and select customer
3. View:
   - Purchase history
   - Total spent
   - Loyalty points
   - Outstanding balance

## 6.3 Loyalty Program

1. Customers earn points on purchases
2. Points can be redeemed for discounts
3. Configure in **Settings** → **Loyalty & Rewards**

---

# 7. Reports & Analytics

## 7.1 Daily Reports

| Report | Description |
|--------|-------------|
| **Sales Summary** | Total sales, payment methods, tax |
| **Cash Up** | Cash drawer reconciliation |
| **Waiter Performance** | Sales by waiter, tips earned |
| **Product Sales** | Best/worst sellers |

## 7.2 Running a Report

1. Go to **Reports**
2. Select report type
3. Set date range
4. Apply filters (location, category, etc.)
5. Click **"Generate"**
6. Export to PDF/Excel if needed

## 7.3 End of Day (EOD)

1. Go to **Reports** → **End of Day**
2. Review:
   - Total sales
   - Payment breakdown
   - Voids and refunds
   - Cash variance
3. Enter actual cash count
4. Note any discrepancies
5. Click **"Close Day"**

---

# 8. Settings & Configuration

## 8.1 Business Settings

**Settings** → **Business Information**
- Business name, address, phone
- Logo upload
- Tax/VAT number

## 8.2 Receipt Settings

**Settings** → **Receipt Settings**
- Header text
- Footer text
- Show/hide elements
- Paper size

## 8.3 Waiter Assignment Mode

**Settings** → **Restaurant & Bar**

| Mode | Description |
|------|-------------|
| **Self-Service** | Each waiter logs in with own account |
| **Cashier Assigns** | Cashier selects waiter when creating tab |
| **Flexible (Both)** | Waiter dropdown available but optional |

Additional options:
- ☑️ Require waiter selection on tabs
- ☑️ Show waiter filter on tabs list
- ☑️ Enable waiter commission tracking

## 8.4 User Management

**Settings** → **Users**
1. Click **"Add User"**
2. Enter details and assign role
3. Set permissions
4. Click **Save**

---

# 9. Troubleshooting

## 9.1 Common Issues

### "Offline" Status Showing
- Check internet connection
- Refresh the page
- Clear browser cache

### Receipt Not Printing
- Check printer is connected and powered on
- Verify printer is set as default
- Check paper supply
- Try printing a test page

### Payment Failed
- Check internet connection
- For M-Pesa: verify phone number format
- For card: check card reader connection
- Try alternative payment method

### Tab Not Closing
- Ensure all items are accounted for
- Check for pending BOTs
- Verify payment amount matches total

## 9.2 Getting Help

- Contact your system administrator
- Check the online help documentation
- Report bugs to support

---

# Quick Reference Cards

## Bar POS - Daily Workflow

```
START OF SHIFT
1. Log in with your credentials
2. Check cash drawer float
3. Review any open tabs from previous shift

DURING SERVICE
1. Open tabs for new customers
2. Add items as ordered
3. Send BOTs to bar station
4. Print bills when requested
5. Process payments
6. Print receipts

END OF SHIFT
1. Close all your tabs
2. Run waiter report
3. Cash up drawer
4. Log out
```

## Print Document Quick Guide

| Document | When to Use | Size |
|----------|-------------|------|
| **Bill** | Customer wants to see total before paying | 80mm |
| **Tab** | Internal reference, kitchen copy | 80mm |
| **Receipt** | After payment is complete | 80mm |
| **Invoice** | Corporate customer, formal record | A4 |

## Payment Methods

| Method | Process |
|--------|---------|
| **Cash** | Enter amount received → Give change |
| **Card** | Swipe/tap card → Wait for approval |
| **M-Pesa** | Enter phone → Customer confirms on phone |
| **Room Charge** | Select room → Charge to folio |
| **Split** | Divide total → Process each separately |

---

*WAPOS Training Manual v1.0*
*Last Updated: December 2024*
