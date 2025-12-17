# WAPOS User Manual

Complete guide to using the WAPOS business management system.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [User Roles](#user-roles)
4. [Point of Sale (POS)](#point-of-sale)
5. [Restaurant Module](#restaurant-module)
6. [Inventory Management](#inventory-management)
7. [Delivery Management](#delivery-management)
8. [Housekeeping](#housekeeping)
9. [Maintenance](#maintenance)
10. [Accounting](#accounting)
11. [Payment Gateways](#payment-gateways)
12. [Reports](#reports)
13. [Administration](#administration)
14. [Keyboard Shortcuts](#keyboard-shortcuts)
15. [Troubleshooting](#troubleshooting)

---

## Introduction

**WAPOS** (Waesta Point of Sale) is a comprehensive business management system designed for retail, restaurant, and hospitality operations.

### Key Features

- **Point of Sale** - Fast checkout with barcode scanning
- **Restaurant Management** - Tables, kitchen display, reservations
- **Inventory Control** - Stock tracking and supplier management
- **Delivery Management** - Order dispatch and GPS tracking
- **Housekeeping** - Room status and task management
- **Maintenance** - Work orders and asset management
- **Accounting** - Financial reporting and tax compliance
- **Payment Gateways** - M-Pesa, Airtel, MTN, Cards

### System Requirements

- Modern web browser (Chrome, Firefox, Safari, Edge)
- Internet connection
- Screen resolution: 1024x768 minimum (1920x1080 recommended)

---

## Getting Started

### Logging In

1. Open your web browser
2. Navigate to your WAPOS URL (e.g., `https://yourdomain.com/wapos`)
3. Enter your username and password
4. Click **Sign In**

### First-Time Login

- Change your password immediately after first login
- Update your profile information
- Familiarize yourself with the dashboard

### Dashboard Overview

After logging in, you'll see a dashboard tailored to your role:

- **Quick Stats** - Key metrics (sales, orders, tasks)
- **Recent Activity** - Latest transactions
- **Navigation Menu** - Access to all modules
- **Notifications** - Alerts and messages

---

## User Roles

WAPOS uses role-based access control. Each role has specific permissions.

| Role | Access Level | Primary Functions |
|------|--------------|-------------------|
| **Super Admin** | Full | All system functions, settings, users |
| **Admin** | High | Operations, reports, user management |
| **Manager** | Medium | Sales, inventory, staff, reports |
| **Cashier** | Limited | POS, customers, basic reports |
| **Waiter** | Limited | Order taking, table management |
| **Accountant** | Specialized | Financial reports, accounting |
| **Rider** | Limited | Deliveries, status updates |
| **Housekeeper** | Limited | Room cleaning, task updates |

---

## Point of Sale

The POS module handles all sales transactions.

### Making a Sale

1. **Add Items** - Click products or scan barcodes
2. **Adjust Quantities** - Use +/- buttons
3. **Apply Discounts** - If applicable
4. **Select Customer** - For loyalty points (optional)
5. **Checkout** - Click to proceed to payment
6. **Payment** - Select method and complete
7. **Receipt** - Print or email receipt

### Payment Methods

| Method | Description |
|--------|-------------|
| Cash | Enter amount received, system calculates change |
| Card | Process through card terminal |
| M-Pesa | STK Push or manual reference |
| Airtel Money | Mobile money payment |
| MTN MoMo | Mobile money payment |
| Bank Transfer | Record transfer reference |
| Split | Combine multiple methods |

### Held Orders

Pause a transaction for later:

1. Click **Hold Order**
2. Add reference name
3. Cart is cleared
4. Retrieve from **Held Orders** panel

### Voids & Refunds

> **Note:** Requires manager approval and is logged for audit.

- **Void Item** - Remove item before completing sale
- **Void Transaction** - Cancel entire sale
- **Refund** - Process return after completion

### Register Reports

| Report | Purpose |
|--------|---------|
| X Report | Mid-shift summary |
| Y Report | Detailed breakdown |
| Z Report | End-of-day closing |

---

## Restaurant Module

Complete restaurant management system.

### Table Management

- **Floor Plan** - Visual layout of tables
- **Status Colors**:
  - 🟢 Green - Available
  - 🔴 Red - Occupied
  - 🟡 Yellow - Reserved
  - ⚪ Gray - Cleaning

### Taking Orders

1. Select table from floor plan
2. Choose order type (Dine-in/Takeout/Delivery)
3. Add menu items with modifiers
4. Add special instructions
5. Send to Kitchen Display

### Kitchen Display System (KDS)

- Orders appear automatically
- Color-coded by age (green → yellow → red)
- Mark items as "Preparing" or "Ready"
- Bump completed orders

### Reservations

1. Go to **Restaurant → Reservations**
2. Click **New Reservation**
3. Enter guest details
4. Select date, time, party size
5. Assign table (optional)
6. Save

---

## Inventory Management

Track stock levels and manage products.

### Products

- **Add Product** - Name, SKU, barcode, price, category
- **Categories** - Organize products
- **Variants** - Size, color variations
- **Images** - Upload product photos
- **Track Inventory** - Enable stock tracking

### Stock Management

1. Go to **Inventory → Stock Levels**
2. View current stock
3. Filter by category or status
4. Click product for history

### Goods Received Notes (GRN)

Record incoming stock:

1. Go to **Inventory → Goods Received**
2. Click **New GRN**
3. Select supplier
4. Add products and quantities
5. Submit - stock updates automatically

### Stock Alerts

Set minimum stock levels. Alerts appear when stock falls below threshold.

---

## Delivery Management

Manage delivery orders and track riders with intelligent dispatch.

### Creating Delivery Orders

1. Create order in POS or Restaurant
2. Select **Delivery** type
3. Enter customer address (Google Maps autocomplete)
4. System calculates delivery fee:
   - **Automatic Mode**: Google Routes API with traffic data
   - **Manual Mode**: Enter fee based on your pricing guide
5. Complete payment
6. Order appears in Delivery queue

### Delivery Pricing Modes

#### Automatic Pricing (Default)
- Uses Google Routes API for real-time calculation
- Considers traffic conditions
- Distance and duration-based
- Cached for 30 minutes to reduce API costs
- Automatic fallback to Haversine distance if API fails

#### Manual Pricing Mode
- **Zero API costs** - No Google Maps charges
- Staff enters delivery fee manually
- Based on your custom pricing guide
- Configurable in **Settings → Delivery & Logistics**
- Ideal for fixed-rate or zone-based pricing

**To Enable Manual Pricing:**
1. Go to **Settings → Delivery & Logistics**
2. Toggle **Enable Manual Pricing Mode**
3. Enter custom instructions for staff
4. Save settings

### Intelligent Dispatch

#### Auto-Assign Optimal Rider

The system can automatically select the best rider based on:
- **Traffic-aware routing** - Real-time traffic conditions
- **Distance** - Closest available rider
- **Capacity** - Current workload (max deliveries per rider)
- **Availability** - Active riders with GPS location

**How to Auto-Assign:**
1. Go to **Enhanced Delivery Tracking**
2. Find pending delivery
3. Click **⚡ Auto-Assign** button
4. System selects optimal rider instantly
5. Rider receives notification

#### Rider Suggestions

View top rider options before assigning:

1. Click **👥 Rider Suggestions** button
2. See recommended rider with:
   - Duration and distance
   - Current capacity (e.g., 2/3 deliveries)
   - GPS status
   - Selection score
3. View alternative riders
4. Choose rider or let system auto-assign

**Selection Criteria:**
- Primary: Shortest duration (traffic-aware)
- Secondary: Available capacity
- Tertiary: Distance from pickup

### Manual Dispatching

1. Go to **Delivery → Dispatch**
2. View pending deliveries
3. Click **Assign Rider**
4. Select from available riders
5. Rider receives notification

### Enhanced Tracking Dashboard

**Access:** Delivery → Enhanced Tracking

**Features:**
- Real-time Google Maps visualization
- Active deliveries list with status
- Rider location markers
- Route polylines
- Auto-assign buttons for pending orders
- Rider suggestions modal
- Performance charts
- SLA monitoring (at-risk alerts)

### Live Tracking

- View all riders on map in real-time
- See estimated arrival times (ETA)
- Monitor status updates
- Contact rider directly (phone)
- Contact customer (phone)
- Route visualization
- GPS accuracy indicators

### Rider Management

**Add/Edit Riders:**
1. Go to **Delivery → Riders**
2. Click **Add Rider**
3. Enter details:
   - Name, phone, email
   - Vehicle type (bike/car/motorcycle)
   - Vehicle number, make, color
   - License number
   - Max concurrent deliveries (default: 3)
4. Upload plate photo
5. Set active/inactive status

**Rider Portal:**
- Dedicated login for riders
- View assigned deliveries
- Update delivery status
- Toggle GPS tracking
- View delivery history
- Mobile-optimized interface

### Status Flow

```
Pending → Assigned → Picked Up → In Transit → Delivered
         ↓
      Failed (with reason)
```

### SLA Monitoring

System tracks delivery times and alerts for:
- **Pending too long** - Not assigned within limit
- **Assigned too long** - Not picked up within limit
- **In-transit too long** - Delivery taking too long
- **At-risk deliveries** - Highlighted in red

### Delivery Analytics

**Performance Metrics:**
- Total deliveries (daily/weekly/monthly)
- Average delivery time
- Success rate
- Failed deliveries
- Rider performance comparison
- Peak delivery hours
- Distance traveled
- Delivery fee revenue

**Dispatch Analytics:**
- Auto-assign success rate
- Average selection score
- Candidates evaluated per delivery
- Route calculation success rate
- Traffic impact on duration

---

## Housekeeping

Room cleaning and task management.

### Room Status Board

| Status | Color | Meaning |
|--------|-------|---------|
| Clean | 🟢 Green | Ready for guests |
| Dirty | 🔴 Red | Needs cleaning |
| In Progress | 🟡 Yellow | Being cleaned |
| Inspected | 🔵 Blue | Verified clean |
| Out of Order | ⚪ Gray | Not available |

### Assigning Tasks

1. Select room(s)
2. Click **Assign Task**
3. Select housekeeper
4. Set priority
5. Add instructions

### For Housekeeping Staff

1. View assigned rooms
2. Start cleaning (status → In Progress)
3. Complete checklist
4. Mark complete
5. Report issues

---

## Maintenance

Work order and asset management.

### Submitting Requests

1. Go to **Maintenance → New Request**
2. Select location
3. Choose category
4. Describe problem
5. Set priority
6. Submit

### Work Order Status

```
Submitted → Assigned → In Progress → Completed → Verified
```

---

## Accounting

Financial management and reporting.

### Chart of Accounts

- **Assets** - Cash, inventory, equipment
- **Liabilities** - Payables, loans
- **Equity** - Owner's equity
- **Revenue** - Sales, income
- **Expenses** - Operating costs

### Financial Reports

| Report | Description |
|--------|-------------|
| Profit & Loss | Income and expenses |
| Balance Sheet | Assets, liabilities, equity |
| Cash Flow | Money in and out |
| Tax Report | Sales tax collected |

---

## Payment Gateways

WAPOS supports multiple payment methods.

### M-Pesa (Daraja API)

Direct Safaricom integration for Kenya.

#### STK Push

Sends payment prompt to customer's phone:

1. Customer provides Safaricom number
2. Click **Pay with M-Pesa**
3. Customer receives USSD prompt
4. Customer enters PIN
5. Payment confirmed automatically

#### Accepted Phone Formats

- `0712345678` - Local
- `712345678` - Without zero
- `254712345678` - With country code
- `+254712345678` - International

#### Paybill Payment

Customer-initiated:

1. Customer opens M-Pesa
2. Lipa na M-Pesa → Pay Bill
3. Enter Business Number
4. Enter Account Number
5. Enter amount and PIN

#### Till Payment

Customer-initiated:

1. Customer opens M-Pesa
2. Lipa na M-Pesa → Buy Goods
3. Enter Till Number
4. Enter amount and PIN

### Airtel Money

Available in Kenya, Uganda, Rwanda, Tanzania via Relworx.

### MTN Mobile Money

Available in Uganda and Rwanda via Relworx.

### Card Payments

Visa and Mastercard via Relworx or PesaPal.

### Payment Gateway Setup

> **Admin Only:** Go to **Settings → Payment Gateways**

#### M-Pesa Setup

1. Register at [developer.safaricom.co.ke](https://developer.safaricom.co.ke)
2. Create app, get Consumer Key/Secret
3. Request Passkey
4. Enter in WAPOS settings
5. Test in Sandbox
6. Go Live

#### Relworx Setup

1. Register at [relworx.com](https://relworx.com)
2. Get API credentials
3. Enter in WAPOS settings
4. Configure callback URL

#### PesaPal Setup

1. Register at [pesapal.com](https://www.pesapal.com)
2. Get Consumer Key/Secret
3. Configure IPN URL
4. Enter in WAPOS settings

---

## Reports

### Sales Reports

- Daily Sales
- Sales by Period
- Sales by Product
- Sales by Category
- Sales by Payment Method
- Sales by Cashier

### Inventory Reports

- Stock Levels
- Low Stock
- Stock Movement
- Valuation

### Export Formats

- PDF - For printing
- Excel - For analysis
- CSV - For import

---

## Administration

### User Management

1. Go to **Admin → Users**
2. Click **Add User**
3. Enter details
4. Assign role
5. Save

### System Settings

- Business Info
- Currency
- Tax Settings
- Receipt Settings
- Module Settings

### Data Backup

1. Go to **Admin → Backup**
2. Click **Create Backup**
3. Download to secure location

---

## Keyboard Shortcuts

### POS Shortcuts

| Key | Action |
|-----|--------|
| F1 | Help |
| F2 | New sale |
| F3 | Search products |
| F4 | Search customers |
| F8 | Hold order |
| F9 | Recall held |
| F12 | Checkout |
| Esc | Cancel |
| Enter | Confirm |

### Navigation

| Key | Action |
|-----|--------|
| Alt+D | Dashboard |
| Alt+P | POS |
| Alt+I | Inventory |
| Alt+R | Reports |
| Alt+S | Settings |

---

## Troubleshooting

### Login Issues

**Can't log in:**
- Check Caps Lock
- Verify username
- Contact admin for password reset
- Clear browser cache

**Session expired:**
- Log in again
- Sessions expire for security

### POS Issues

**Receipt not printing:**
- Check printer connection
- Verify paper loaded
- Check default printer
- Print test page

**Barcode scanner not working:**
- Check USB connection
- Ensure keyboard mode
- Click search field first

### Payment Issues

**STK Push not received:**
- Verify phone number
- Check M-Pesa balance
- Ensure network signal
- Wait 30 seconds, retry

**Payment not updating sale:**
- Check callback URL
- Verify server access
- Check payment logs

### Performance Issues

**System slow:**
- Clear browser cache
- Close extra tabs
- Check internet speed
- Contact admin

---

## Support

For technical assistance, contact:
- Your system administrator
- Waesta Enterprises support

---

*WAPOS User Manual - © 2024 Waesta Enterprises U Ltd*
