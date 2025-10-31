# 📊 Accounting Module Setup Required

**Status:** ⚠️ NOT INSTALLED  
**Error:** `Table 'wapos.journal_lines' doesn't exist`

---

## 🚀 Quick Fix (2 Minutes)

### **Option 1: Use the Web Installer (Recommended)**

1. **Go to:**
   ```
   http://localhost/wapos/install-accounting.php
   ```

2. **Click:** "Install Accounting Module" button

3. **Done!** All tables will be created automatically

---

### **Option 2: Run SQL Manually**

1. **Open phpMyAdmin:**
   ```
   http://localhost/phpmyadmin
   ```

2. **Select database:** `wapos`

3. **Click:** SQL tab

4. **Copy and paste** the contents of:
   ```
   database/fix-accounting-module.sql
   ```

5. **Click:** Go

---

## 📋 What Gets Installed

### **Database Tables:**
1. ✅ `accounts` - Chart of accounts
2. ✅ `journal_entries` - Journal entries
3. ✅ `journal_lines` - Debit/credit lines
4. ✅ `expense_categories` - Expense categories
5. ✅ `account_reconciliations` - Bank reconciliation

### **Seeded Data:**
- ✅ Complete chart of accounts (Assets, Liabilities, Equity, Revenue, Expenses)
- ✅ 10 default expense categories
- ✅ Proper account codes (1000-6999)

---

## 🎯 Features Enabled After Installation

### **Financial Reports:**
- ✅ Profit & Loss Statement
- ✅ Balance Sheet
- ✅ Trial Balance
- ✅ General Ledger
- ✅ Sales Tax Report

### **Accounting Features:**
- ✅ Double-entry bookkeeping
- ✅ Journal entries
- ✅ Account reconciliation
- ✅ Expense tracking
- ✅ Revenue tracking

---

## ⚠️ Current Status

**Without Installation:**
- ❌ Profit & Loss report will error
- ❌ Balance Sheet will error
- ❌ Accounting page may have issues
- ✅ All other features work fine

**After Installation:**
- ✅ All accounting features work
- ✅ Financial reports available
- ✅ Complete accounting module

---

## 🔧 Troubleshooting

### **Error: "Table already exists"**
**Solution:** This is normal. The installer skips existing tables.

### **Error: "Access denied"**
**Solution:** Make sure you're logged in as admin.

### **Error: "SQL file not found"**
**Solution:** Make sure `database/fix-accounting-module.sql` exists.

---

## 📊 Chart of Accounts Structure

### **Assets (1000-1999)**
- 1000 - Cash
- 1100 - Bank Account
- 1200 - Accounts Receivable
- 1300 - Inventory
- 1400 - Prepaid Expenses
- 1500 - Fixed Assets

### **Liabilities (2000-2999)**
- 2000 - Accounts Payable
- 2100 - Sales Tax Payable
- 2200 - Accrued Expenses
- 2300 - Short-term Loans

### **Equity (3000-3999)**
- 3000 - Owner's Equity
- 3100 - Retained Earnings
- 3200 - Drawings

### **Revenue (4000-4999)**
- 4000 - Sales Revenue
- 4100 - Service Revenue
- 4200 - Other Income

### **Expenses (5000-6999)**
- 5000 - Cost of Goods Sold
- 6000 - Operating Expenses
- 6100 - Salaries and Wages
- 6200 - Rent Expense
- 6300 - Utilities Expense
- 6400 - Marketing Expense
- 6500 - Supplies Expense
- 6600 - Maintenance Expense
- 6700 - Insurance Expense
- 6800 - Professional Fees
- 6900 - Depreciation Expense

---

## ✅ Installation Steps (Detailed)

### **Step 1: Access Installer**
```
http://localhost/wapos/install-accounting.php
```

### **Step 2: Review Status**
- Check which tables are missing
- Review what will be installed

### **Step 3: Install**
- Click "Install Accounting Module"
- Wait for confirmation

### **Step 4: Verify**
- All tables should show "✅ Installed"
- Click "Go to Accounting"

### **Step 5: Test**
- Try Profit & Loss report
- Try Balance Sheet
- Create a test journal entry

---

## 🎯 Quick Links After Installation

### **Accounting:**
```
http://localhost/wapos/accounting.php
```

### **Profit & Loss:**
```
http://localhost/wapos/reports/profit-and-loss.php
```

### **Balance Sheet:**
```
http://localhost/wapos/reports/balance-sheet.php
```

---

## 💡 Why Is This Separate?

The accounting module is **optional** and requires additional database tables. 

**Benefits of separate installation:**
- ✅ Faster initial setup
- ✅ Only install what you need
- ✅ Smaller database for non-accounting users
- ✅ Can be added anytime

---

## 📝 Summary

**Problem:** Accounting tables missing  
**Solution:** Run installer  
**Time:** 2 minutes  
**Difficulty:** Easy (one click)  
**Impact:** Enables full accounting features  

---

## 🚀 Install Now!

**Go to:**
```
http://localhost/wapos/install-accounting.php
```

**Click:** "Install Accounting Module"

**That's it!** 🎉

---

**The accounting module is optional but highly recommended for complete financial management!** 📊✅
