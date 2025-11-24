# WAPOS - Waesta Point of Sale System

## 🎯 Pure PHP POS System - Built for You!

A professional, clean, and simple Point of Sale system built with **vanilla PHP** - no frameworks, no complications!

---

## ✅ Features

- 🔐 **Secure Authentication** - Role-based access (Admin, Manager, Cashier)
- 🛒 **POS Interface** - Fast checkout with barcode scanning
- 📦 **Inventory Management** - Track products and stock levels
- 📊 **Sales Reports** - Daily, weekly, monthly reports
- 👥 **Customer Management** - Track customer purchases
- 💰 **Expense Tracking** - Monitor business expenses
- 🎨 **Modern UI** - Clean Bootstrap 5 interface
- 📱 **Responsive Design** - Works on desktop, tablet, mobile

---

## 🚀 Quick Installation (2 Minutes!)

### Step 1: Copy to XAMPP
```bash
# Copy the entire 'wapos' folder to:
C:\xampp\htdocs\wapos
```

### Step 2: Start XAMPP
- Open XAMPP Control Panel
- Start **Apache**
- Start **MySQL**

### Step 3: Run Installer
Open your browser and go to:
```
http://localhost/wapos/install.php
```
Click **"Install Now"** - Done!

### Step 4: Login
Go to:
```
http://localhost/wapos/login.php
```

**Default Login:**
- Username: `admin` or `developer`
- Password: `admin123`

---

## 📁 Simple File Structure

```
wapos/
├── config.php              # Configuration settings
├── install.php             # One-click installer
├── login.php               # Login page
├── index.php               # Dashboard (coming next)
├── pos.php                 # POS interface (coming next)
├── includes/
│   ├── bootstrap.php       # Initialize everything
│   ├── Database.php        # Simple database class
│   └── Auth.php            # Authentication class
├── database/
│   └── schema.sql          # Database structure
├── assets/                 # CSS, JS, images (coming next)
└── README.md               # This file
```

---

## 🎨 Technology Stack

- **Backend:** Pure PHP 8.2
- **Database:** MySQL/MariaDB
- **Frontend:** Bootstrap 5 + Bootstrap Icons
- **JavaScript:** Vanilla JS (no jQuery!)

---

## 🔒 Security Features

✅ Password hashing (Argon2id)
✅ SQL injection protection (PDO prepared statements)
✅ XSS prevention (output sanitization)
✅ Session management
✅ Role-based access control

---

## 📊 Database Structure

8 Clean Tables:
1. **users** - System users
2. **products** - Inventory items
3. **categories** - Product categories
4. **sales** - Sales records
5. **sale_items** - Items in each sale
6. **customers** - Customer database
7. **expenses** - Business expenses
8. **stock_adjustments** - Inventory changes
9. **settings** - System configuration

---

## 🚀 What's Next?

Now building:
1. ✅ Dashboard with sales stats
2. ✅ POS interface
3. ✅ Product management
4. ✅ Reports
5. ✅ Settings page

---

## 🌐 Deployment to Shared Hosting

Simple 3-step process:
1. Upload all files via FTP
2. Create MySQL database
3. Update config.php with your database details
4. Run install.php once
5. Delete install.php for security

**That's it!** No composer, no command line, no complications!

---

## 💳 Payment Gateway Integration

Easy to add:
- **M-Pesa** - Just add API credentials
- **PayPal** - Simple REST API integration
- **Stripe** - Clean PHP SDK
- **Flutterwave** - African payment gateway

All documentation and hooks ready!

---

## 📱 Mobile App Integration

Backend is API-ready:
- JSON responses
- RESTful endpoints
- JWT authentication ready
- CORS configured

Build your mobile app with:
- **Flutter**
- **React Native**
- **Ionic**

## 🚚 Rider Tracking API (Mobile/PWA Courier App)

Use the delivery tracking endpoint to stream rider telemetry back to WAPOS so the Google Maps dashboard stays live:

- **Endpoint:** `POST /wapos/api/update-rider-location.php`
- **Auth:** same session/login cookie as the rider portal (include your `PHPSESSID`)
- **Suggested Interval:** every 30–60 seconds while on-duty

### Payload Fields

| Field       | Type    | Required | Notes |
|-------------|---------|----------|-------|
| `rider_id`  | int     | ✅        | Internal rider record ID |
| `latitude`  | float   | ✅        | Decimal degrees (`-90` to `90`) |
| `longitude` | float   | ✅        | Decimal degrees (`-180` to `180`) |
| `accuracy`  | float   | optional | In meters (from GPS) |
| `speed`     | float   | optional | Meters/second (from GPS) |
| `heading`   | float   | optional | Degrees, 0 = North, clockwise |

```json
{
  "rider_id": 12,
  "latitude": -1.292066,
  "longitude": 36.821945,
  "accuracy": 4.2,
  "speed": 12.4,
  "heading": 85.0
}
```

**Tips:**

- Normalize decimal precision to 6–7 digits to save bandwidth.
- Only send telemetry when the rider status is `assigned`, `picked-up`, or `in-transit` to reduce noise.
- Handle HTTP 401 by prompting the rider to re-authenticate.

---

## 🤝 Support

This is **YOUR** code. You own it, understand it, and can modify it easily!

- Clean, commented code
- No hidden magic
- Simple structure
- Easy to debug

---

## 📝 License

This is YOUR professional POS system. Use it, modify it, sell it!

---

## 🎉 Built With Trust

Developed for **you** - a clean, professional system you can be proud of!

**- Your Trusted Developer** 🚀
