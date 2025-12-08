# WAPOS Deployment Guide

This guide covers deploying WAPOS on **cPanel/Shared Hosting** and **Cloud Platforms**.

> 📚 **For a comprehensive deployment manual with detailed instructions, see:**  
> **[docs/DEPLOYMENT_MANUAL.md](docs/DEPLOYMENT_MANUAL.md)**

---

## Quick Start

### Default Login Credentials
```
URL: https://yourdomain.com
Username: superadmin
Password: admin123
⚠️ CHANGE IMMEDIATELY AFTER FIRST LOGIN!
```

---

## System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4+ | 8.1+ |
| MySQL/MariaDB | 5.7+ | 8.0+ |
| Memory | 128MB | 256MB |
| Storage | 100MB | 500MB+ |

### Required PHP Extensions
- `pdo` & `pdo_mysql`
- `json`
- `mbstring`
- `curl`
- `openssl`
- `gd` (for image processing)

---

## Option 1: cPanel Deployment

### Step 1: Prepare Files

```bash
# On your local machine, create deployment package
cd wapos
rm -rf vendor/  # Remove dev dependencies
composer install --no-dev --optimize-autoloader
zip -r wapos-deploy.zip . -x ".git/*" -x "tests/*" -x "*.md" -x ".env*"
```

### Step 2: Upload to cPanel

1. Login to cPanel
2. Go to **File Manager** → `public_html`
3. Create folder: `wapos` (or your preferred name)
4. Upload `wapos-deploy.zip`
5. Extract the zip file
6. Delete the zip file

### Step 3: Create Database

1. Go to **MySQL Databases**
2. Create new database: `yourusername_wapos`
3. Create new user: `yourusername_waposuser`
4. Add user to database with **ALL PRIVILEGES**
5. Go to **phpMyAdmin**
6. Select your database
7. Import `database/schema.sql`
8. Import `database/permissions-schema.sql`

### Step 4: Configure Application

1. Copy `config.production.php` to `config.php`
2. Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourusername_wapos');
define('DB_USER', 'yourusername_waposuser');
define('DB_PASS', 'your_strong_password');
define('APP_URL', 'https://yourdomain.com/wapos');
```

### Step 5: Set Permissions

In cPanel File Manager, set permissions:
- `logs/` → 755
- `cache/` → 755
- `uploads/` → 755
- `storage/` → 755
- `config.php` → 640

### Step 6: Configure .htaccess

The included `.htaccess` should work. If you get 500 errors, check:
- `mod_rewrite` is enabled
- `AllowOverride All` is set

### Step 7: SSL Certificate

1. Go to **SSL/TLS** in cPanel
2. Install Let's Encrypt certificate (free)
3. Force HTTPS redirect

### Step 8: Test

Visit `https://yourdomain.com/wapos/login.php`

Default admin: `admin` / `admin123` (change immediately!)

---

## Option 2: Cloud Deployment

### Supported Platforms

| Platform | Compatibility | Notes |
|----------|---------------|-------|
| AWS EC2 | ✅ Full | Use Amazon Linux 2 or Ubuntu |
| DigitalOcean | ✅ Full | Use LAMP droplet |
| Google Cloud | ✅ Full | Use Compute Engine |
| Azure | ✅ Full | Use App Service or VM |
| Heroku | ⚠️ Partial | Needs ClearDB for MySQL |
| Railway | ✅ Full | Easy deployment |
| Render | ✅ Full | Free tier available |

### AWS EC2 Deployment

#### 1. Launch Instance
```bash
# Use Amazon Linux 2 or Ubuntu 22.04
# Instance type: t2.micro (free tier) or t2.small
```

#### 2. Install LAMP Stack
```bash
# Amazon Linux 2
sudo yum update -y
sudo amazon-linux-extras install php8.1 -y
sudo yum install httpd mariadb-server php-mysqlnd php-mbstring php-json php-curl php-gd -y

# Ubuntu 22.04
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-mbstring php-json php-curl php-gd -y
```

#### 3. Configure Apache
```bash
sudo systemctl start httpd
sudo systemctl enable httpd

# Enable mod_rewrite
sudo a2enmod rewrite  # Ubuntu
```

#### 4. Deploy Application
```bash
cd /var/www/html
sudo git clone https://github.com/your-repo/wapos.git
cd wapos
sudo composer install --no-dev
sudo chown -R apache:apache .  # Amazon Linux
sudo chown -R www-data:www-data .  # Ubuntu
sudo chmod -R 755 .
sudo chmod -R 777 logs cache uploads storage
```

#### 5. Configure Database
```bash
sudo mysql_secure_installation
mysql -u root -p

CREATE DATABASE wapos;
CREATE USER 'wapos_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON wapos.* TO 'wapos_user'@'localhost';
FLUSH PRIVILEGES;

# Import schema
mysql -u wapos_user -p wapos < database/schema.sql
mysql -u wapos_user -p wapos < database/permissions-schema.sql
```

#### 6. Configure Application
```bash
cp config.production.php config.php
nano config.php  # Update database credentials and APP_URL
```

#### 7. SSL with Certbot
```bash
sudo yum install certbot python3-certbot-apache -y  # Amazon Linux
sudo apt install certbot python3-certbot-apache -y  # Ubuntu
sudo certbot --apache -d yourdomain.com
```

---

### DigitalOcean One-Click Deployment

1. Create Droplet → **LAMP on Ubuntu**
2. SSH into droplet
3. Follow AWS steps 4-7 above

---

### Docker Deployment

```dockerfile
# Dockerfile
FROM php:8.1-apache

RUN docker-php-ext-install pdo pdo_mysql mbstring
RUN a2enmod rewrite

COPY . /var/www/html/
COPY .htaccess /var/www/html/

RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80
```

```yaml
# docker-compose.yml
version: '3.8'
services:
  web:
    build: .
    ports:
      - "80:80"
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=wapos
      - DB_USER=wapos
      - DB_PASS=password
    volumes:
      - ./logs:/var/www/html/logs
      - ./uploads:/var/www/html/uploads

  db:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - MYSQL_DATABASE=wapos
      - MYSQL_USER=wapos
      - MYSQL_PASSWORD=password
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql

volumes:
  mysql_data:
```

---

## Post-Deployment Checklist

### Security
- [ ] Change default admin password
- [ ] Enable HTTPS/SSL
- [ ] Set `display_errors = 0` in config
- [ ] Verify `.htaccess` blocks sensitive files
- [ ] Set secure file permissions

### Performance
- [ ] Enable OPcache in PHP
- [ ] Configure MySQL query cache
- [ ] Set up CDN for static assets (optional)

### Monitoring
- [ ] Set up error log monitoring
- [ ] Configure backup schedule
- [ ] Set up uptime monitoring

### Testing
- [ ] Test login functionality
- [ ] Test POS transactions
- [ ] Test all modules
- [ ] Verify API endpoints work

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| 500 Internal Server Error | Check `.htaccess`, enable `mod_rewrite` |
| Database connection failed | Verify credentials in `config.php` |
| Session not working | Check `session.save_path` permissions |
| File upload fails | Check `uploads/` permissions (755) |
| Blank page | Enable error display temporarily |

### Debug Mode

Temporarily enable in `config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Check Logs

```bash
# Apache logs
tail -f /var/log/apache2/error.log  # Ubuntu
tail -f /var/log/httpd/error_log    # Amazon Linux

# Application logs
tail -f logs/php_errors.log
```

---

## Scaling Considerations

### For High Traffic

1. **Database**: Use Amazon RDS or managed MySQL
2. **Sessions**: Use Redis for session storage
3. **Caching**: Implement Redis/Memcached
4. **Load Balancing**: Use AWS ALB or Nginx
5. **CDN**: CloudFront or Cloudflare for static assets

### Recommended Architecture (Production)

```
[CloudFlare CDN]
       ↓
[Load Balancer]
    ↓     ↓
[Web 1] [Web 2]
    ↓     ↓
[Redis Cache]
       ↓
[MySQL Primary → Replica]
```

---

## Support

For deployment assistance, check:
- `SYSTEM_AUDIT_REPORT.md` - System health status
- `logs/php_errors.log` - Application errors
- Database status via `status.php` (admin only)

---

*WAPOS is production-ready for cPanel, cloud, and containerized deployments.*
