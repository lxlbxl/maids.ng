# Maids.ng - Shared Hosting Deployment Guide

## Quick Start (Automated Installation)

### Option 1: Using the Installation Wizard (Recommended)

1. **Download and extract** the application files to your local computer
2. **Upload all files** to your shared hosting public_html directory via FTP/cPanel File Manager
3. **Create a MySQL database** in your hosting control panel
4. **Navigate to** `https://yourdomain.com/install.php` in your browser
5. **Follow the wizard** steps to complete installation
6. **Delete install.php** after installation for security

That's it! The wizard handles everything automatically.

---

## Manual Installation (Step-by-Step)

### Prerequisites

Before starting, ensure your hosting meets these requirements:

- **PHP 8.2 or higher**
- **MySQL 5.7+** or **MariaDB 10.3+**
- **PDO PHP Extension**
- **OpenSSL PHP Extension**
- **Mbstring PHP Extension**
- **Tokenizer PHP Extension**
- **XML PHP Extension**
- **Ctype PHP Extension**
- **JSON PHP Extension**
- **BCMath PHP Extension**
- **mod_rewrite** enabled (for Apache)

### Step 1: Prepare Your Files

#### Option A: Download Pre-built Package

1. Download the latest release ZIP file
2. Extract it to your local computer
3. You should see these folders:
   ```
   maids-ng/
   ├── app/
   ├── bootstrap/
   ├── config/
   ├── database/
   ├── docs/
   ├── public/
   ├── resources/
   ├── routes/
   ├── storage/
   ├── vendor/
   ├── install.php
   └── ...
   ```

#### Option B: Build from Source (Developers)

If you have the source code:

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 2: Upload to Shared Hosting

#### Using cPanel File Manager:

1. Log in to your cPanel
2. Open **File Manager**
3. Navigate to `public_html/` (or your domain's document root)
4. Click **Upload** and select all files
5. Wait for upload to complete

#### Using FTP (FileZilla):

1. Connect to your hosting via FTP
2. Navigate to `public_html/`
3. Upload all files and folders
4. Ensure hidden files (`.env`, `.htaccess`) are uploaded

### Step 3: Create Database

#### Using cPanel:

1. Log in to cPanel
2. Go to **MySQL Database Wizard**
3. Create a new database (e.g., `maidsng_db`)
4. Create a database user (e.g., `maidsng_user`)
5. Set a strong password
6. Add user to database with **ALL PRIVILEGES**
7. Note down:
   - Database name
   - Database username
   - Database password
   - Database host (usually `localhost`)

#### Using phpMyAdmin:

1. Open phpMyAdmin from cPanel
2. Click **New** to create a database
3. Enter database name and click **Create**
4. Go to **User accounts** tab
5. Click **Add user account**
6. Fill in username and password
7. Check **Create database with same name and grant all privileges**
8. Click **Go**

### Step 4: Configure Environment

1. In File Manager, find `.env.example`
2. **Copy** it and rename to `.env`
3. **Edit** the `.env` file with your details:

```env
APP_NAME="Maids.ng"
APP_ENV=production
APP_KEY=  # Will be generated in Step 6
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Mail settings (configure after installation)
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourhost.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Maids.ng"

# Payment gateways (configure after installation)
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_PAYMENT_URL=https://api.paystack.co

FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-...
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...
FLUTTERWAVE_ENCRYPTION_KEY=...
```

### Step 5: Set File Permissions

In cPanel File Manager or via SSH:

```bash
# Set directory permissions
chmod 755 storage/
chmod 755 storage/app/
chmod 755 storage/framework/
chmod 755 storage/logs/
chmod 755 bootstrap/cache/

# Set file permissions
chmod 644 .env
chmod 644 public/.htaccess
```

### Step 6: Run Installation Commands

If you have SSH access:

```bash
cd ~/public_html

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --force

# Create symbolic link for storage
php artisan storage:link

# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you **don't have SSH access**, use the **install.php wizard** instead.

### Step 7: Configure .htaccess

Ensure your `public/.htaccess` file contains:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# PHP Settings
<IfModule mod_php.c>
    php_value upload_max_filesize 64M
    php_value post_max_size 64M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 256M
</IfModule>
```

### Step 8: Point Domain to public Directory

#### Option A: Use public_html (Simplest)

Move all files from `public/` to `public_html/` and update `index.php`:

```php
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
```

#### Option B: Use Subdomain (Recommended)

1. Create a subdomain (e.g., `app.yourdomain.com`)
2. Point it to the `public/` folder
3. Keep main domain for landing page

#### Option C: Use .htaccess Rewrite

Add to root `.htaccess`:

```apache
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
```

---

## Post-Installation Setup

### 1. Create Admin User

If not created during installation:

```bash
php artisan tinker

>>> $user = new App\Models\User();
>>> $user->name = 'Admin';
>>> $user->email = 'admin@yourdomain.com';
>>> $user->password = bcrypt('your-password');
>>> $user->save();
>>> $user->assignRole('admin');
```

Or use the registration form and manually assign admin role in database.

### 2. Configure Payment Gateways

1. **Paystack**:
   - Sign up at https://paystack.com
   - Get your test/live keys
   - Add to `.env` file
   - Configure webhook URL: `https://yourdomain.com/webhooks/paystack`

2. **Flutterwave**:
   - Sign up at https://flutterwave.com
   - Get your API keys
   - Add to `.env` file
   - Configure webhook URL: `https://yourdomain.com/webhooks/flutterwave`

### 3. Configure Email

In Admin Panel → Settings → Email:

- **SMTP Host**: Your mail server (e.g., smtp.gmail.com)
- **SMTP Port**: 587 (TLS) or 465 (SSL)
- **Username**: Your email address
- **Password**: Your email password or app password
- **Encryption**: TLS or SSL

### 4. Set Up Cron Jobs (Optional)

For automated tasks, add this cron job in cPanel:

```bash
* * * * * cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1
```

### 5. SSL Certificate

Ensure your site uses HTTPS:

1. In cPanel, go to **SSL/TLS**
2. Install **Let's Encrypt** certificate (free)
3. Force HTTPS by adding to `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Troubleshooting

### 500 Internal Server Error

1. Check `.env` file exists and is readable
2. Check file permissions (755 for directories, 644 for files)
3. Check PHP version (must be 8.2+)
4. Check error logs in `storage/logs/`

### Database Connection Error

1. Verify database credentials in `.env`
2. Check database host (often `localhost` but could be different)
3. Ensure database user has proper privileges
4. Check if database exists

### 404 Not Found

1. Ensure `.htaccess` file exists in public directory
2. Check mod_rewrite is enabled
3. Verify Apache configuration allows .htaccess overrides

### File Upload Errors

1. Check `upload_max_filesize` in PHP settings
2. Check `post_max_size` in PHP settings
3. Ensure `storage/app` is writable

### Permission Denied Errors

```bash
# Fix permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chmod 644 .env
```

---

## Security Checklist

After installation, ensure:

- [ ] Delete `install.php` file
- [ ] Change default admin password
- [ ] Set strong database password
- [ ] Enable HTTPS/SSL
- [ ] Set proper file permissions
- [ ] Hide `.env` file from web access
- [ ] Configure firewall rules (if available)
- [ ] Set up regular backups
- [ ] Enable 2FA for admin accounts

---

## Backup Instructions

### Manual Backup

1. **Database**: Export via phpMyAdmin or:
   ```bash
   mysqldump -u username -p database_name > backup.sql
   ```

2. **Files**: Download all files via FTP or File Manager

3. **Storage**: Backup `storage/app/` directory

### Automated Backup (cPanel)

1. Go to **Backup Wizard**
2. Set up scheduled backups
3. Include database and home directory

---

## Support

If you encounter issues:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review error logs in `storage/logs/`
3. Contact your hosting provider for server-related issues
4. Visit our documentation: https://docs.maids.ng

---

## Quick Reference Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check application status
php artisan about

# List all routes
php artisan route:list

# Database operations
php artisan migrate:status
php artisan migrate:rollback
php artisan db:seed
```

---

**Congratulations!** Your Maids.ng application is now ready to use. 🎉
