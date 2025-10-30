# 🌍 Currency Configuration Guide

**Date:** October 30, 2025  
**Feature:** Currency-neutral system with configurable currency settings  
**Status:** Complete ✅

---

## 🎯 Overview

WAPOS is now **currency-neutral** and can be configured to use any currency worldwide. No hardcoded currency symbols ($, €, £, KES, etc.) exist in the system.

---

## ⚙️ Configuration

### **Location:** `config.php`

All currency settings are centralized in the configuration file:

```php
// Currency Settings (Configure for your region)
define('CURRENCY_CODE', 'USD');        // ISO currency code
define('CURRENCY_SYMBOL', '$');        // Currency symbol to display
define('CURRENCY_POSITION', 'before'); // 'before' or 'after' the amount
define('DECIMAL_SEPARATOR', '.');      // Decimal separator
define('THOUSANDS_SEPARATOR', ',');    // Thousands separator
```

---

## 🌐 Currency Examples

### **United States Dollar (USD)**
```php
define('CURRENCY_CODE', 'USD');
define('CURRENCY_SYMBOL', '$');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```
**Display:** $1,234.56

---

### **Euro (EUR)**
```php
define('CURRENCY_CODE', 'EUR');
define('CURRENCY_SYMBOL', '€');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', ',');
define('THOUSANDS_SEPARATOR', '.');
```
**Display:** €1.234,56

---

### **British Pound (GBP)**
```php
define('CURRENCY_CODE', 'GBP');
define('CURRENCY_SYMBOL', '£');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```
**Display:** £1,234.56

---

### **Kenyan Shilling (KES)**
```php
define('CURRENCY_CODE', 'KES');
define('CURRENCY_SYMBOL', 'KSh');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```
**Display:** KSh 1,234.56

---

### **Japanese Yen (JPY)**
```php
define('CURRENCY_CODE', 'JPY');
define('CURRENCY_SYMBOL', '¥');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```
**Display:** ¥1,234.56

---

### **Indian Rupee (INR)**
```php
define('CURRENCY_CODE', 'INR');
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```
**Display:** ₹1,234.56

---

### **South African Rand (ZAR)**
```php
define('CURRENCY_CODE', 'ZAR');
define('CURRENCY_SYMBOL', 'R');
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```
**Display:** R 1,234.56

---

### **Swiss Franc (CHF)**
```php
define('CURRENCY_CODE', 'CHF');
define('CURRENCY_SYMBOL', 'CHF');
define('CURRENCY_POSITION', 'after');
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', '\'');
```
**Display:** 1'234.56 CHF

---

## 🛠️ Helper Functions

### **Location:** `includes/currency-helper.php`

The system provides currency formatting functions:

### **1. formatMoney($amount, $showSymbol = true)**
Formats amount with currency symbol and proper separators.

```php
echo formatMoney(1234.56);  // Output: $1,234.56 (or configured currency)
echo formatMoney(1234.56, false);  // Output: 1,234.56 (no symbol)
```

### **2. formatAmount($amount)**
Formats amount without currency symbol.

```php
echo formatAmount(1234.56);  // Output: 1,234.56
```

### **3. getCurrencySymbol()**
Returns the configured currency symbol.

```php
echo getCurrencySymbol();  // Output: $ (or configured symbol)
```

### **4. getCurrencyCode()**
Returns the ISO currency code.

```php
echo getCurrencyCode();  // Output: USD (or configured code)
```

### **5. parseMoney($moneyString)**
Parses formatted money string to float.

```php
$amount = parseMoney('$1,234.56');  // Returns: 1234.56
```

---

## 📝 Usage in Code

### **Old Way (Hardcoded - DON'T USE):**
```php
// ❌ Bad - hardcoded currency
echo "$" . number_format($amount, 2);
echo "KSh " . number_format($amount, 2);
```

### **New Way (Currency-Neutral - USE THIS):**
```php
// ✅ Good - uses configured currency
echo formatMoney($amount);
```

---

## 🔍 Where Currency is Used

Currency formatting is used throughout the system:

### **Dashboards:**
- ✅ `dashboards/accountant-dashboard.php`
- ✅ `dashboards/cashier-dashboard.php`
- ✅ `dashboards/waiter-dashboard.php`
- ✅ `dashboards/manager-dashboard.php`
- ✅ `dashboards/admin-dashboard.php`

### **Core Modules:**
- ✅ `pos.php` - Point of Sale
- ✅ `restaurant.php` - Restaurant orders
- ✅ `sales.php` - Sales history
- ✅ `accounting.php` - Accounting module
- ✅ `reports.php` - Financial reports
- ✅ `inventory.php` - Inventory management

### **Receipts & Invoices:**
- ✅ `print-receipt.php`
- ✅ `print-customer-receipt.php`
- ✅ `print-customer-invoice.php`
- ✅ `digital-receipt.php`
- ✅ `room-invoice.php`

---

## 🌍 Regional Settings

### **Decimal Separators:**
- **Period (.)** - Used in: US, UK, China, Japan, India
- **Comma (,)** - Used in: Most of Europe, Latin America

### **Thousands Separators:**
- **Comma (,)** - Used in: US, UK, China, Japan
- **Period (.)** - Used in: Most of Europe, Latin America
- **Space ( )** - Used in: France, Sweden, Finland
- **Apostrophe (')** - Used in: Switzerland

### **Currency Position:**
- **Before** - Most currencies: $100, £100, €100
- **After** - Some currencies: 100 CHF, 100 SEK

---

## 🚀 How to Change Currency

### **Step 1: Edit config.php**
```php
// Open: c:\xampp\htdocs\wapos\config.php
// Find the Currency Settings section
// Update the values
```

### **Step 2: Set Your Currency**
```php
define('CURRENCY_CODE', 'EUR');     // Your currency code
define('CURRENCY_SYMBOL', '€');     // Your currency symbol
define('CURRENCY_POSITION', 'before');
define('DECIMAL_SEPARATOR', ',');
define('THOUSANDS_SEPARATOR', '.');
```

### **Step 3: Save and Refresh**
- Save the file
- Refresh your browser
- Currency changes apply immediately!

---

## ✅ Benefits

### **1. Global Compatibility** 🌍
- Works in any country
- Supports any currency
- No code changes needed

### **2. Easy Configuration** ⚙️
- Single configuration file
- Change once, applies everywhere
- No technical knowledge required

### **3. Professional Display** 💼
- Proper formatting for each region
- Correct decimal/thousands separators
- Currency symbol positioning

### **4. Maintainability** 🔧
- Centralized configuration
- Easy to update
- No hardcoded values

---

## 📊 Testing Different Currencies

To test different currencies:

1. **Backup your config.php**
2. **Change currency settings**
3. **Refresh any page**
4. **Verify formatting**
5. **Restore or keep new settings**

---

## 🔒 Database Considerations

### **Important:**
- Currency values are stored as **DECIMAL** in database
- No currency symbols stored in database
- Only numeric values stored
- Currency formatting applied on display only

### **Database Schema:**
```sql
-- Amounts stored as decimal
total_amount DECIMAL(10,2)
paid_amount DECIMAL(10,2)
price DECIMAL(10,2)
```

---

## 📝 Summary

**Currency Configuration:** ✅ Centralized in config.php  
**Helper Functions:** ✅ Available in currency-helper.php  
**System-Wide:** ✅ All files updated  
**Global Ready:** ✅ Works with any currency  
**Easy to Change:** ✅ Edit one file  

---

## 🎉 Result

Your WAPOS system is now **100% currency-neutral** and can be deployed anywhere in the world with just a simple configuration change!

**No more hardcoded $, €, £, or KES symbols!** 🌍✨

---

**Configuration File:** `config.php`  
**Helper Functions:** `includes/currency-helper.php`  
**Status:** Production-ready for global use ✅
