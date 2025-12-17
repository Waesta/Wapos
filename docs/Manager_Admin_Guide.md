# Manager & Admin Guide
## WAPOS - System Administration & Management

---

# Table of Contents

1. [Dashboard Overview](#1-dashboard-overview)
2. [User Management](#2-user-management)
3. [Product & Menu Management](#3-product--menu-management)
4. [Inventory Control](#4-inventory-control)
5. [Reports & Analytics](#5-reports--analytics)
6. [System Settings](#6-system-settings)
7. [Troubleshooting](#7-troubleshooting)

---

# 1. Dashboard Overview

## Manager Dashboard

Your dashboard shows:
- **Today's Sales**: Real-time sales total
- **Open Tabs**: Current active orders
- **Low Stock Alerts**: Items needing reorder
- **Staff Performance**: Sales by waiter/cashier
- **Quick Actions**: Common management tasks

## Key Metrics

| Metric | What It Shows |
|--------|---------------|
| Gross Sales | Total before discounts |
| Net Sales | After discounts and voids |
| Average Check | Average transaction value |
| Covers | Number of customers served |
| Tips | Total tips collected |

---

# 2. User Management

## Adding a New User

1. Go to **Settings** → **Users**
2. Click **[+ Add User]**
3. Fill in details:
   - **Full Name**: Employee's name
   - **Username**: Login username (unique)
   - **Password**: Temporary password
   - **Email**: For notifications
   - **Phone**: Contact number
   - **Role**: Select appropriate role
4. Click **[Save]**

## User Roles Explained

| Role | Capabilities |
|------|-------------|
| **Super Admin** | Full system access, all settings, can delete data |
| **Admin** | Management functions, reports, most settings |
| **Manager** | Day-to-day operations, staff management, reports |
| **Cashier** | POS operations, payments, basic reports |
| **Waiter** | Order taking, tab management |
| **Bartender** | Bar POS, drink preparation, portion tracking |

## Editing User Permissions

1. Go to **Settings** → **Users**
2. Click on the user
3. Click **[Edit Permissions]**
4. Toggle permissions on/off:
   - View Reports
   - Apply Discounts
   - Void Items
   - Process Refunds
   - Access Settings
5. Click **[Save]**

## Deactivating a User

1. Go to **Settings** → **Users**
2. Find the user
3. Click **[Deactivate]**
4. User can no longer log in but history is preserved

---

# 3. Product & Menu Management

## Adding a Product

1. Go to **Products** → **Add New**
2. Fill in details:
   - **Name**: Product name
   - **SKU**: Unique code (auto-generated if blank)
   - **Category**: Select category
   - **Price**: Selling price
   - **Cost**: Purchase cost (for margin calculation)
   - **Tax**: Tax category
3. Upload **Image** (optional)
4. Click **[Save]**

## Setting Up Portioned Products (Spirits/Wine)

1. Create the product as normal
2. Check **"Is Portioned"**
3. Enter **Bottle Size** (ml)
4. Go to **Portions** tab
5. Add portions:

| Portion Type | Size | Price |
|--------------|------|-------|
| Tot (Small) | 25ml | 150 |
| Tot (Standard) | 35ml | 200 |
| Tot (Large) | 50ml | 280 |
| Shot | 30ml | 180 |
| Glass | 175ml | 500 |

6. Set **Default Portion**
7. Click **[Save]**

## Managing Categories

1. Go to **Products** → **Categories**
2. Click **[+ Add Category]**
3. Enter name and select icon
4. Set display order
5. Click **[Save]**

## Price Updates

### Individual Product:
1. Go to **Products**
2. Find and click the product
3. Update price
4. Click **[Save]**

### Bulk Price Update:
1. Go to **Products** → **Bulk Actions**
2. Select products or category
3. Choose action:
   - Increase by %
   - Decrease by %
   - Set new price
4. Preview changes
5. Click **[Apply]**

---

# 4. Inventory Control

## Stock Levels

1. Go to **Inventory** → **Stock Levels**
2. View current stock for all products
3. Filter by:
   - Category
   - Location
   - Low stock only
4. Export to Excel for analysis

## Receiving Stock (GRN)

1. Go to **Inventory** → **Goods Received**
2. Select **Purchase Order** or create manual GRN
3. Enter received quantities
4. Note any discrepancies:
   - Short delivery
   - Damaged items
   - Wrong items
5. Click **[Receive Stock]**
6. Print GRN for records

## Stock Adjustments

1. Go to **Inventory** → **Adjustments**
2. Click **[+ New Adjustment]**
3. Select product
4. Enter adjustment:
   - Positive (+) for found stock
   - Negative (-) for missing stock
5. Select reason:
   - Damage
   - Theft
   - Expiry
   - Count correction
   - Spillage
6. Add notes
7. Submit

## Bar Variance Report

1. Go to **Reports** → **Bar Variance**
2. Select date range
3. Review:
   - **Expected Usage**: Based on sales
   - **Actual Usage**: Based on stock counts
   - **Variance**: Difference (should be minimal)
4. Investigate high-variance items
5. Take corrective action

---

# 5. Reports & Analytics

## Daily Reports

### Sales Summary
- Total sales by payment method
- Discounts applied
- Voids and refunds
- Tax collected

### Cash Up Report
- Opening float
- Cash sales
- Cash payments received
- Expected cash
- Actual cash count
- Variance

### Waiter Performance
- Sales by waiter
- Number of covers
- Average check value
- Tips earned
- Voids/discounts applied

## Running Reports

1. Go to **Reports**
2. Select report type
3. Set parameters:
   - Date range
   - Location
   - Staff member
   - Category
4. Click **[Generate]**
5. View on screen or export

## Exporting Reports

- **PDF**: For printing/sharing
- **Excel**: For further analysis
- **Email**: Send directly to recipients

## Scheduled Reports

1. Go to **Reports** → **Scheduled**
2. Click **[+ New Schedule]**
3. Select report
4. Set frequency (daily/weekly/monthly)
5. Add email recipients
6. Click **[Save]**

---

# 6. System Settings

## Business Information

**Settings** → **Business Information**

- Business name
- Address
- Phone, Email
- Logo upload
- Tax/VAT number

## Receipt Settings

**Settings** → **Receipt Settings**

- Header text
- Footer text
- Show/hide: logo, address, tax breakdown
- Paper size (80mm thermal / A4)

## Waiter Assignment Mode

**Settings** → **Restaurant & Bar**

| Mode | Description |
|------|-------------|
| **Self-Service** | Each waiter uses own login, tabs auto-assigned |
| **Cashier Assigns** | Cashier selects waiter when opening tab |
| **Flexible** | Waiter dropdown available but optional |

Options:
- ☑️ Require waiter selection on tabs
- ☑️ Show waiter filter on tabs list
- ☑️ Enable commission tracking
- Commission rate: ____%

## Tax Configuration

**Settings** → **Tax Settings**

1. Add tax rates (e.g., VAT 16%)
2. Assign to categories
3. Set inclusive/exclusive
4. Configure tax reports

## Payment Methods

**Settings** → **Payment Methods**

Enable/disable:
- Cash
- Card
- M-Pesa
- Room Charge
- Credit Account

Configure:
- M-Pesa integration
- Card terminal settings

---

# 7. Troubleshooting

## Common Issues

### Staff Can't Log In
1. Verify username is correct
2. Reset password if needed
3. Check if account is active
4. Verify role has login permission

### Products Not Showing
1. Check product is active
2. Verify category is active
3. Check location assignment
4. Clear browser cache

### Reports Not Loading
1. Check date range is valid
2. Reduce date range if too large
3. Check internet connection
4. Try different browser

### Printer Issues
1. Check printer power and connection
2. Verify paper supply
3. Check printer is set as default
4. Restart printer
5. Test print from system

### Payment Processing Errors
1. Check internet connection
2. Verify payment gateway settings
3. For M-Pesa: check API credentials
4. Contact payment provider if persistent

## System Maintenance

### Daily
- Review void/discount reports
- Check for unusual activity
- Verify cash up completed

### Weekly
- Review staff performance
- Check inventory levels
- Run variance reports
- Backup verification

### Monthly
- Full inventory count
- Review pricing
- Staff performance reviews
- System updates check

---

# Emergency Procedures

## System Down
1. Switch to manual backup (paper tickets)
2. Record all transactions manually
3. Contact IT support
4. Enter manual transactions when system restored

## Security Breach
1. Change all passwords immediately
2. Review access logs
3. Contact IT security
4. Document incident

## Data Loss
1. Contact IT immediately
2. Check backup status
3. Do not attempt to fix yourself
4. Document what was lost

---

# Contact Information

- **IT Support**: _______________
- **System Admin**: _______________
- **Emergency**: _______________

---

*This guide is for managers and administrators. Keep confidential.*
