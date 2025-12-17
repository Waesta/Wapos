# WAPOS Training Manual
## Waesta Point of Sale System - Complete Operational Guide

**Version:** 3.0  
**Last Updated:** December 17, 2025

---

# Table of Contents

1. [Getting Started](#1-getting-started)
2. [Retail POS Operations](#2-retail-pos-operations)
3. [Restaurant POS Operations](#3-restaurant-pos-operations)
4. [Bar POS Operations](#4-bar-pos-operations)
5. [Delivery & Dispatch Operations](#5-delivery--dispatch-operations)
6. [Inventory Management](#6-inventory-management)
7. [Customer Management](#7-customer-management)
8. [Housekeeping Operations](#8-housekeeping-operations)
9. [Room Management](#9-room-management)
10. [Events & Banquet Management](#10-events--banquet-management)
11. [Security Management](#11-security-management)
12. [HR & Employee Management](#12-hr--employee-management)
13. [Reports & Analytics](#13-reports--analytics)
14. [Settings & Configuration](#14-settings--configuration)
15. [Troubleshooting](#15-troubleshooting)

---

# 1. Getting Started

## 1.1 Logging In

1. Open your browser and go to your WAPOS URL
2. Enter your **Username** and **Password**
3. Click **Login**
4. You'll be directed to your dashboard based on your role

## 1.2 User Roles & Permissions

| Role | Access Level | Primary Functions |
|------|-------------|-------------------|
| **Super Admin** | Full system access | All settings, users, modules |
| **Developer** | Technical access | Module management, integrations |
| **Admin** | High-level management | Operations, reports, user management |
| **Manager** | Day-to-day operations | Sales, inventory, staff, reports |
| **Cashier** | POS operations | Retail sales, customers, basic reports |
| **Waiter** | Order taking | Restaurant orders, table management |
| **Bartender** | Bar operations | Bar POS, portion management, recipes |
| **Rider** | Delivery operations | Delivery portal, GPS tracking, status updates |
| **Accountant** | Financial access | Reports, accounting, expenses |
| **Housekeeper** | Room cleaning | Task management, status updates |
| **Maintenance** | Work orders | Issue tracking, asset management |
| **Front Desk** | Guest services | Room bookings, check-in/out, folios |
| **Inventory Manager** | Stock control | Products, stock, procurement |
| **Banquet Coordinator** | Events management | Venue bookings, event services, payments |
| **Security Manager** | Security operations | Personnel, shifts, patrols, incidents |
| **Security Staff** | Guard duties | Shift check-in, patrols, visitor logs |
| **HR Manager** | Human resources | Employee records, payroll, performance |
| **HR Staff** | HR operations | Leave requests, training records |

## 1.3 Navigation

- **Sidebar Menu**: Click the hamburger icon (☰) to expand/collapse
- **Quick Actions**: Common tasks accessible from dashboard
- **Search**: Find products, customers, or orders quickly
- **Notifications**: Bell icon shows alerts and messages

## 1.4 Dashboard Overview

Your dashboard shows:
- **Quick Stats**: Revenue, orders, tasks
- **Recent Activity**: Latest transactions
- **Alerts**: Low stock, pending tasks, at-risk deliveries
- **Quick Links**: Frequently used functions

---

# 2. Retail POS Operations

## 2.1 Making a Sale

### Step-by-Step Process:

1. **Start New Sale**
   - Click **New Sale** or press `F2`
   - Cart is cleared and ready

2. **Add Products**
   - **Scan Barcode**: Use barcode scanner
   - **Search**: Type product name or SKU
   - **Browse**: Click category, then product
   - **Quick Add**: Click product card

3. **Adjust Quantities**
   - Click **+** or **-** buttons
   - Or type quantity directly

4. **Apply Discounts** (if authorized)
   - Click **Discount** button
   - Enter percentage or fixed amount
   - Select reason
   - Click **Apply**

5. **Select Customer** (optional)
   - Click **Customer** button
   - Search by name or phone
   - Select customer
   - Loyalty points auto-apply

6. **Checkout**
   - Click **Checkout** button
   - Review total amount
   - Proceed to payment

7. **Process Payment**
   - Select payment method:
     - **Cash**: Enter amount received, system shows change
     - **M-Pesa**: Enter phone, click Pay (STK Push)
     - **Card**: Process through terminal
     - **Split**: Use multiple methods
   - Click **Complete Payment**

8. **Receipt**
   - Auto-prints to thermal printer
   - Or click **Print Receipt**
   - Email option available

## 2.2 Payment Methods

### Cash Payment
1. Select **Cash**
2. Enter amount received
3. System calculates change
4. Click **Complete**
5. Open cash drawer, give change

### M-Pesa Payment
1. Select **M-Pesa**
2. Enter customer phone (0712345678 or 254712345678)
3. Click **Send STK Push**
4. Customer receives prompt on phone
5. Customer enters M-Pesa PIN
6. Wait for confirmation (auto-updates)
7. Complete sale

### Split Payment
1. Click **Split Payment**
2. Enter amount for first method
3. Select method and process
4. Remaining balance shows
5. Select second method
6. Complete remaining amount

## 2.3 Held Orders

**Pause a sale for later:**

1. Click **Hold Order**
2. Enter reference name (e.g., "John - Table 5")
3. Cart is saved and cleared
4. Retrieve from **Held Orders** panel
5. Click to restore cart
6. Continue sale

## 2.4 Register Management

### Opening Register
1. Go to **POS** page
2. Click **Open Register**
3. Enter opening cash amount
4. Click **Confirm**
5. Register is now active

### Closing Register
1. Click **Close Register**
2. Enter actual cash count
3. System shows expected amount
4. Variance is calculated
5. If variance > threshold, manager approval required
6. Click **Close**
7. Print Z Report

### Cash Drawer Operations
- **Open Drawer**: Press cash drawer button or `Ctrl+D`
- **No Sale**: Open drawer without transaction
- **Cash In/Out**: Record cash added or removed

---

# 3. Restaurant POS Operations

## 3.1 Table Management

1. Go to **Restaurant** module
2. View floor plan with table status:
   - 🟢 **Green**: Available
   - 🟡 **Yellow**: Reserved
   - 🔴 **Red**: Occupied
   - ⚪ **Gray**: Cleaning
3. Click table to view/manage orders

## 3.2 Taking Orders

### Dine-In Orders:

1. **Select Table**
   - Click table on floor plan
   - Or use table dropdown

2. **Start Order**
   - Click **New Order**
   - Select order type: **Dine-in**
   - Enter guest count

3. **Add Items**
   - Browse menu by category
   - Click item to add
   - For items with modifiers:
     - Select size (Small/Medium/Large)
     - Choose options (Extra cheese, No onions)
     - Add special instructions
   - Click **Add to Order**

4. **Review Order**
   - Items appear in order panel
   - Adjust quantities if needed
   - Add more items

5. **Send to Kitchen**
   - Click **Send to Kitchen**
   - Order prints to kitchen (KOT)
   - Kitchen Display System shows order

6. **Process Payment**
   - When customer ready to pay
   - Click **Pay**
   - Select payment method
   - Complete transaction
   - Table status changes to Available

### Takeaway Orders:

1. Click **New Order**
2. Select **Takeaway**
3. Enter customer name and phone
4. Add items
5. Send to kitchen
6. Process payment
7. Mark as **Ready for Pickup**

### Delivery Orders:

See [Section 5: Delivery & Dispatch Operations](#5-delivery--dispatch-operations)

## 3.3 Order Modifiers

**Adding Modifiers:**
1. Click item with modifiers
2. Modal appears with options
3. Select modifiers (checkboxes)
4. Enter special instructions
5. Click **Add to Order**

**Common Modifiers:**
- Extra cheese
- No onions
- Spicy
- Well done
- On the side

## 3.4 Bill Splitting

**Split by Items:**
1. Open order
2. Click **Split Bill**
3. Select **By Items**
4. Assign items to different bills
5. Process each payment separately

**Split Equally:**
1. Click **Split Bill**
2. Select **Equal Split**
3. Enter number of ways (e.g., 2, 3, 4)
4. System divides total equally
5. Process each payment

## 3.5 Kitchen Display System (KDS)

**For Kitchen Staff:**

1. Orders appear automatically when sent
2. Color-coded by age:
   - 🟢 **Green**: New (0-5 min)
   - 🟡 **Yellow**: In progress (5-15 min)
   - 🔴 **Red**: Urgent (>15 min)
3. Click **Start** when beginning preparation
4. Click **Ready** when complete
5. Click **Bump** to remove from screen

**Order Details Show:**
- Order number (KOT-YYMMDD-XXXX)
- Table number
- Items with quantities
- Modifiers highlighted
- Special instructions in bold
- Time elapsed

---

# 4. Bar POS Operations

## 4.1 Opening a New Tab

1. Click **New Tab** button
2. Select tab type:
   - **Name**: Enter guest name
   - **Card**: Enter last 4 digits for pre-auth
   - **Room**: Select hotel room
3. Select **Bar Station**
4. Enter **Guest Count**
5. Select **Waiter** (if enabled)
6. Click **Open Tab**

## 4.2 Adding Portioned Items

**For Spirits, Wine, Cocktails:**

1. Select open tab
2. Click portioned product (shows "BAR" badge)
3. **Portion Selection Modal** appears
4. Choose portion size:
   - **Tots**: 25ml, 35ml, 50ml
   - **Shots**: 30ml, 44ml, 60ml
   - **Glasses**: 125ml, 175ml, 250ml
5. Adjust quantity (+/-)
6. Add special instructions (optional)
7. Click **Add to Tab**
8. Item shows with portion size

## 4.3 Cocktail Recipes

**Viewing Recipe:**
1. Click cocktail item
2. Recipe shows ingredients
3. Portions auto-calculated
4. Cost breakdown visible

**Making Cocktail:**
1. Add cocktail to tab
2. Kitchen/Bar receives order
3. Recipe shows required ingredients
4. Pour logging tracks usage

## 4.4 Processing Payment

1. Select tab to close
2. Click **Pay** button
3. Choose payment method
4. Add **Tip** if applicable
5. Click **Complete Payment**
6. Print receipt when prompted

## 4.5 Tab Management

### Transfer Tab
1. Click **⋮** → **Transfer Tab**
2. Select waiter to transfer to
3. Enter reason (optional)
4. Click **Transfer**

### Void Tab
1. Click **⋮** → **Void Tab**
2. Enter void reason
3. Confirm (manager approval may be required)

### Print Options
- **Print Bill (Customer)**: For customer review
- **Print Tab (Kitchen)**: Internal reference
- **Print Invoice (A4)**: Formal invoice

---

# 5. Delivery & Dispatch Operations

## 5.1 Creating Delivery Orders

### From POS:
1. Add items to cart
2. Click **Delivery** type
3. Enter customer details:
   - Name
   - Phone number
   - Delivery address (Google autocomplete)
4. System calculates delivery fee
5. Complete payment
6. Order sent to delivery queue

### From Restaurant:
1. Create order as usual
2. Select **Delivery** type
3. Enter customer and address
4. Send to kitchen
5. Process payment
6. Order appears in delivery queue

## 5.2 Delivery Pricing

### Automatic Pricing (Default)
- Uses Google Routes API
- Real-time traffic data
- Distance and duration-based
- Cached for 30 minutes
- Auto-fallback if API fails

### Manual Pricing Mode
- **Zero API costs**
- Staff enters fee manually
- Based on your pricing guide
- Configured in Settings

**To Use Manual Pricing:**
1. Admin enables in **Settings → Delivery & Logistics**
2. Toggle **Enable Manual Pricing Mode**
3. Set custom instructions
4. When creating delivery, enter fee manually

## 5.3 Intelligent Dispatch

### Auto-Assign Optimal Rider

**Automatic Selection:**
1. Go to **Enhanced Delivery Tracking**
2. Find pending delivery
3. Click **⚡ Auto-Assign** button
4. System analyzes:
   - Traffic conditions
   - Rider distance
   - Current workload
   - GPS availability
5. Selects best rider instantly
6. Rider receives notification

**Selection Criteria:**
- **Primary**: Shortest duration (traffic-aware)
- **Secondary**: Available capacity
- **Tertiary**: Distance from pickup

### Rider Suggestions

**View Options Before Assigning:**

1. Click **👥 Rider Suggestions** button
2. Modal shows:
   - **Recommended Rider** (top choice)
     - Name, phone, vehicle
     - Duration and distance
     - Current capacity (e.g., 2/3 deliveries)
     - GPS status
     - Selection score
   - **Alternative Riders** (2-3 options)
     - Same details as above
     - Comparison metrics
3. Review options
4. Click **Assign This Rider** on preferred option
5. Or close modal and use auto-assign

### Manual Assignment

1. Go to **Delivery → Dispatch**
2. View pending deliveries
3. Click **Assign Rider**
4. Select from dropdown
5. Click **Assign**
6. Rider receives notification

## 5.4 Enhanced Tracking Dashboard

**Access:** Delivery → Enhanced Tracking

**Features:**
- **Google Maps** with real-time rider locations
- **Active Deliveries List** with status
- **Auto-Assign Buttons** for pending orders
- **Rider Suggestions** modal
- **Performance Charts**
- **SLA Monitoring** (at-risk alerts)

**Using the Dashboard:**

1. **View Map**
   - Rider markers show current location
   - Click marker for rider info
   - Routes shown as polylines
   - Auto-refreshes every 30 seconds

2. **Monitor Deliveries**
   - List shows all active deliveries
   - Status badges (Pending/Assigned/In-Transit)
   - At-risk deliveries highlighted in red
   - Click delivery for details

3. **Quick Actions**
   - **Track**: View on map
   - **Contact Customer**: Call customer
   - **Auto-Assign**: Assign optimal rider
   - **Suggestions**: View rider options

## 5.5 Rider Portal (For Riders)

**Accessing Portal:**
1. Go to rider login page
2. Enter rider credentials
3. Dashboard shows assigned deliveries

**Updating Delivery Status:**

1. **Accept Delivery**
   - Click **Accept** on assigned delivery
   - Status changes to Assigned

2. **Mark Picked Up**
   - Arrive at pickup location
   - Click **Picked Up**
   - Status changes to Picked Up

3. **Start Delivery**
   - Click **In Transit**
   - GPS tracking activates
   - Customer can track

4. **Complete Delivery**
   - Arrive at customer location
   - Click **Delivered**
   - Enter delivery notes (optional)
   - Take photo proof (optional)
   - Confirm completion

5. **Report Issues**
   - If customer not available
   - Click **Failed**
   - Select reason
   - Add notes

**GPS Tracking:**
- Toggle GPS on/off
- Location updates every 30 seconds
- Visible on tracking dashboard
- Battery-efficient

## 5.6 Delivery Status Flow

```
Pending → Assigned → Picked Up → In Transit → Delivered
         ↓
      Failed (with reason)
```

**Status Meanings:**
- **Pending**: Awaiting rider assignment
- **Assigned**: Rider assigned, not picked up yet
- **Picked Up**: Rider has the order
- **In Transit**: On the way to customer
- **Delivered**: Successfully completed
- **Failed**: Could not complete (customer unavailable, wrong address, etc.)

## 5.7 SLA Monitoring

**System Alerts:**
- **Pending too long**: Not assigned within time limit
- **Assigned too long**: Not picked up within limit
- **In-transit too long**: Delivery taking too long
- **At-risk deliveries**: Highlighted in red on dashboard

**Responding to Alerts:**
1. Check delivery details
2. Contact rider if needed
3. Reassign if necessary
4. Update customer

## 5.8 Rider Management

**Adding Riders:**
1. Go to **Delivery → Riders**
2. Click **Add Rider**
3. Enter details:
   - Name, phone, email
   - Vehicle type (bike/car/motorcycle)
   - Vehicle number, make, color
   - License number
   - **Max concurrent deliveries** (default: 3)
4. Upload plate photo
5. Set **Active** status
6. Save

**Managing Riders:**
- **Edit**: Update rider details
- **Deactivate**: Temporarily disable rider
- **View Performance**: See delivery stats
- **Reset Password**: For portal access

---

# 6. Inventory Management

## 6.1 Adding Products

1. Go to **Inventory → Products**
2. Click **Add Product**
3. Enter details:
   - Product name
   - SKU/Barcode
   - Category
   - Cost price
   - Retail price
   - Tax rate
   - Unit of measure
4. Upload image (optional)
5. Set reorder level
6. Enable **Track Inventory**
7. Save

## 6.2 Stock Management

### Viewing Stock Levels
1. Go to **Inventory → Stock Levels**
2. View current stock
3. Filter by:
   - Category
   - Location
   - Status (In Stock/Low/Out)

### Stock Adjustments
1. Click product
2. Click **Adjust Stock**
3. Select adjustment type:
   - **Increase**: Add stock
   - **Decrease**: Remove stock
4. Enter quantity
5. Select reason:
   - Damage
   - Theft
   - Expired
   - Found
   - Correction
6. Add notes
7. Save

### Stock Transfers
1. Go to **Inventory → Transfers**
2. Click **New Transfer**
3. Select **From Location**
4. Select **To Location**
5. Add products and quantities
6. Submit transfer
7. Receiving location confirms receipt

## 6.3 Goods Received Notes (GRN)

**Recording Stock Arrivals:**

1. Go to **Inventory → Goods Received**
2. Click **New GRN**
3. Select supplier
4. Add products:
   - Select product
   - Enter quantity received
   - Enter cost price
   - Add batch/lot number (optional)
5. Upload supplier invoice (optional)
6. Submit GRN
7. Stock levels update automatically

## 6.4 Bar Inventory

### Portioned Products
1. Add product as usual
2. Enable **Is Portioned**
3. Select bottle size (350ml - 3L)
4. Set default portion (25ml, 35ml, etc.)
5. Configure portions:
   - Add portion sizes
   - Set prices per portion
6. Save

### Open Bottle Tracking
1. Go to **Bar Management → Open Bottles**
2. Click **Open Bottle**
3. Select product
4. Enter bottle number
5. System tracks remaining ml
6. Log pours automatically

### Variance Reports
1. Go to **Bar Management → Variance**
2. Select date range
3. View expected vs actual usage
4. Identify high-wastage products
5. Export report

---

# 7. Customer Management

## 7.1 Adding Customers

1. Go to **Customers**
2. Click **Add Customer**
3. Enter details:
   - Name
   - Phone
   - Email
   - Address
4. Set loyalty tier (optional)
5. Save

## 7.2 Loyalty Points

### Earning Points
- Automatic on purchases
- Configurable rate (e.g., 1 point per 100 currency)
- Shows on receipt

### Redeeming Points
1. During checkout, select customer
2. System shows available points
3. Click **Redeem Points**
4. Enter points to redeem
5. Discount applied automatically

### Managing Loyalty Program
1. Go to **Settings → Customers**
2. Configure:
   - Points earning rate
   - Redemption rate
   - Minimum redemption
   - Expiration rules
3. Save settings

## 7.3 Customer Communications

### Sending Notifications
1. Go to **Settings → Notifications**
2. Click **Send Notification**
3. Select recipients:
   - All customers
   - Specific segment
   - Individual customer
4. Choose channel:
   - WhatsApp
   - SMS
   - Email
5. Compose message
6. Send or schedule

### Notification Billing
1. Go to **Settings → Notification Billing**
2. View usage:
   - WhatsApp messages sent
   - SMS sent
   - Email sent
3. Track costs
4. Export reports

---

# 8. Housekeeping Operations

## 8.1 Room Status Board

**Viewing Status:**
1. Go to **Housekeeping**
2. View room grid with colors:
   - 🟢 **Clean**: Ready for guests
   - 🔴 **Dirty**: Needs cleaning
   - 🟡 **In Progress**: Being cleaned
   - 🔵 **Inspected**: Verified clean
   - ⚪ **Out of Order**: Not available

## 8.2 Cleaning Tasks

**For Housekeepers:**

1. **View Assignments**
   - Login to system
   - See assigned rooms

2. **Start Cleaning**
   - Click room
   - Click **Start Cleaning**
   - Status changes to In Progress

3. **Complete Checklist**
   - Check off tasks:
     - Make bed
     - Clean bathroom
     - Vacuum floor
     - Restock amenities
     - Empty trash

4. **Mark Complete**
   - Click **Complete**
   - Status changes to Clean

5. **Report Issues**
   - If maintenance needed
   - Click **Report Issue**
   - Select issue type
   - Add notes
   - Submit

## 8.3 Inventory Management

**Tracking Supplies:**

1. Go to **Housekeeping → Inventory**
2. View sections:
   - Linen (sheets, towels, pillowcases)
   - Cleaning supplies
   - Room amenities
   - Minibar items
3. Adjust stock as needed
4. Request replenishment

**Linen Tracking:**
- **Clean**: Available for use
- **In Use**: On beds
- **Dirty**: Needs washing
- **Washing**: In laundry
- **Damaged**: Needs replacement

---

# 9. Room Management

## 9.1 Room Bookings

**Creating Reservation:**

1. Go to **Rooms → Reservations**
2. Click **New Reservation**
3. Enter guest details
4. Select dates (check-in/check-out)
5. Choose room type
6. Enter number of guests
7. Add special requests
8. Calculate total
9. Take deposit (optional)
10. Confirm booking

## 9.2 Check-In Process

1. Find reservation
2. Click **Check In**
3. Verify guest ID
4. Assign room number
5. Collect payment or authorize card
6. Generate room key
7. Print welcome letter
8. Complete check-in

## 9.3 Guest Folio

**Managing Charges:**

1. Open guest folio
2. Add charges:
   - Room rate (auto-posted)
   - Restaurant charges
   - Minibar
   - Services
3. Process payments
4. View balance

## 9.4 Check-Out Process

1. Find guest folio
2. Click **Check Out**
3. Review all charges
4. Process final payment
5. Print invoice
6. Return deposit (if applicable)
7. Complete check-out
8. Room status → Dirty

---

# 10. Reports & Analytics

## 10.1 Sales Reports

**Daily Sales Summary:**
1. Go to **Reports → Sales**
2. Select date
3. View:
   - Total revenue
   - Number of transactions
   - Average transaction value
   - Payment method breakdown
4. Export to PDF/Excel

**Sales by Category:**
1. Select date range
2. Choose **Category Report**
3. View sales per category
4. Identify top performers

## 10.2 Inventory Reports

**Stock Valuation:**
1. Go to **Reports → Inventory**
2. Select **Stock Valuation**
3. View total inventory value
4. Export for accounting

**Reorder Report:**
1. Select **Reorder Report**
2. View products below reorder level
3. Generate purchase orders

## 10.3 Financial Reports

**Profit & Loss:**
1. Go to **Accounting → Reports**
2. Select **P&L Statement**
3. Choose date range
4. View:
   - Revenue
   - Expenses
   - Net profit
5. Export

**Expense Breakdown:**
1. Select **Expense Report**
2. View by category
3. Compare periods
4. Identify cost-saving opportunities

---

# 11. Settings & Configuration

## 11.1 Business Profile

1. Go to **Settings → Business**
2. Update:
   - Business name
   - Address
   - Phone, email
   - Tax ID
   - Logo
3. Save

## 11.2 Delivery Settings

**Configuring Pricing Mode:**

1. Go to **Settings → Delivery & Logistics**
2. Scroll to **Intelligent Dispatch & Pricing**
3. Toggle **Enable Manual Pricing Mode**:
   - **OFF**: Automatic pricing (Google API)
   - **ON**: Manual pricing (zero API costs)
4. If manual mode enabled:
   - Enter **Manual Pricing Instructions**
   - Example: "0-5km: 200, 5-10km: 350, 10+km: 500"
5. Save settings

**Google Maps API:**
1. Enter API keys:
   - Places API Key
   - Routes API Key
2. Configure restrictions
3. Test connection

## 11.3 Receipt Customization

1. Go to **Settings → Receipt**
2. Customize:
   - Header text
   - Footer text
   - Logo
   - QR code
   - Font size
3. Preview
4. Save

## 11.4 Tax Configuration

1. Go to **Settings → Tax**
2. Set tax rate (e.g., 16%)
3. Choose tax type:
   - Inclusive
   - Exclusive
4. Save

---

# 12. Troubleshooting

## 12.1 Common Issues

### Payment Failed
**Problem**: M-Pesa payment not completing  
**Solution**:
1. Check customer phone number format
2. Verify customer has sufficient balance
3. Ensure customer entered correct PIN
4. Wait 30 seconds for timeout
5. Retry payment

### Printer Not Working
**Problem**: Receipt not printing  
**Solution**:
1. Check printer power and connection
2. Verify paper loaded correctly
3. Check printer settings in browser
4. Test print from printer settings
5. Restart printer if needed

### Stock Not Updating
**Problem**: Stock levels not changing after sale  
**Solution**:
1. Verify **Track Inventory** enabled for product
2. Check product has stock quantity
3. Refresh page
4. Contact admin if persists

### GPS Not Working
**Problem**: Rider location not showing  
**Solution**:
1. Rider must enable GPS in portal
2. Check browser location permissions
3. Ensure rider has internet connection
4. Refresh tracking dashboard

## 12.2 Getting Help

**Support Channels:**
- **In-App Help**: Click ? icon
- **User Manual**: `/docs/USER_MANUAL.md`
- **Admin Guide**: `/docs/ADMIN_GUIDE.md`
- **Training Videos**: Available in Help section

**Reporting Bugs:**
1. Note exact steps to reproduce
2. Take screenshot if possible
3. Contact system administrator
4. Provide error message if shown

---

# Quick Reference Cards

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `F2` | New Sale |
| `F3` | Search Products |
| `F4` | Search Customers |
| `F8` | Hold Order |
| `F9` | Retrieve Held Order |
| `F12` | Checkout |
| `Ctrl+D` | Open Cash Drawer |
| `Esc` | Cancel/Close |

## Payment Method Codes

| Code | Method |
|------|--------|
| CASH | Cash |
| MPESA | M-Pesa |
| CARD | Credit/Debit Card |
| AIRTEL | Airtel Money |
| MTN | MTN Mobile Money |
| BANK | Bank Transfer |
| ROOM | Room Charge |

## Status Color Codes

| Color | Meaning |
|-------|---------|
| 🟢 Green | Available/Active/Success |
| 🟡 Yellow | In Progress/Warning |
| 🔴 Red | Occupied/Urgent/Error |
| 🔵 Blue | Completed/Verified |
| ⚪ Gray | Inactive/Unavailable |

---

**End of Training Manual**

*For additional support, refer to the complete User Manual or contact your system administrator.*
