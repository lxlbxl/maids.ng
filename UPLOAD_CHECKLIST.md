# Maids.ng - File Upload Checklist for Shared Hosting

## 📁 What to Upload (Complete List)

You need to upload **ALL** of these files/folders to your shared hosting `public_html` directory:

### Required Folders (Upload Everything Inside)

| Folder | Destination | Description |
|--------|-------------|-------------|
| `app/` | `public_html/app/` | PHP application code (Controllers, Models, etc.) |
| `bootstrap/` | `public_html/bootstrap/` | Laravel bootstrap files |
| `config/` | `public_html/config/` | Configuration files |
| `database/` | `public_html/database/` | Migrations and seeders |
| `docs/` | `public_html/docs/` | Documentation (API docs, guides) |
| `public/` | `public_html/public/` | Web assets (CSS, JS, images) |
| `resources/` | `public_html/resources/` | Views, React components, CSS |
| `routes/` | `public_html/routes/` | Route definitions |
| `storage/` | `public_html/storage/` | Logs, cache, uploads (empty initially) |
| `vendor/` | `public_html/vendor/` | PHP dependencies (Composer packages) |

### Required Files (Upload to Root)

| File | Destination | Description |
|------|-------------|-------------|
| `.env.example` | `public_html/.env.example` | Environment template |
| `.htaccess` | `public_html/.htaccess` | Apache rewrite rules |
| `artisan` | `public_html/artisan` | Laravel CLI tool |
| `composer.json` | `public_html/composer.json` | PHP dependencies list |
| `composer.lock` | `public_html/composer.lock` | Locked dependency versions |
| `install.php` | `public_html/install.php` | Installation wizard |
| `package.json` | `public_html/package.json` | Node dependencies (reference) |
| `README.md` | `public_html/README.md` | Project readme |
| `DEPLOYMENT_GUIDE.md` | `public_html/DEPLOYMENT_GUIDE.md` | Full deployment guide |
| `README-DEPLOYMENT.md` | `public_html/README-DEPLOYMENT.md` | Quick deployment summary |
| `vite.config.js` | `public_html/vite.config.js` | Build config |
| `tailwind.config.js` | `public_html/tailwind.config.js` | CSS config |
| `postcss.config.js` | `public_html/postcss.config.js` | PostCSS config |
| `phpunit.xml` | `public_html/phpunit.xml` | Testing config |

### Important: DO NOT Upload These

❌ `node_modules/` - Not needed (already built)
❌ `.git/` - Git history (not needed on server)
❌ `tests/` - Test files (optional)
❌ `.env` - Will be created by installer

---

## 📂 Directory Structure After Upload

```
public_html/
├── app/
│   ├── Http/
│   ├── Models/
│   ├── Services/
│   └── ...
├── bootstrap/
│   ├── app.php
│   ├── cache/
│   └── providers.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   └── ...
├── database/
│   ├── database.sqlite
│   ├── migrations/
│   └── seeders/
├── docs/
│   └── api/
│       ├── README.md
│       ├── openapi.yaml
│       └── AGENTIC_GUIDE.md
├── public/
│   ├── build/              ← Pre-built assets (from npm run build)
│   ├── favicon.png
│   ├── index.php
│   ├── maids-logo.png
│   ├── robots.txt
│   └── ...
├── resources/
│   ├── css/
│   ├── js/
│   │   ├── Components/
│   │   ├── Layouts/
│   │   └── Pages/
│   └── views/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
├── storage/
│   ├── app/
│   ├── framework/
│   └── logs/
├── vendor/                 ← Composer dependencies
├── .env.example
├── .htaccess
├── artisan
├── composer.json
├── composer.lock
├── install.php             ← Installation wizard
├── DEPLOYMENT_GUIDE.md
├── README-DEPLOYMENT.md
└── ...
```

---

## ✅ Step-by-Step Upload Process

### Method 1: cPanel File Manager (Recommended for Beginners)

1. **Log in to cPanel**
2. **Open File Manager**
3. **Navigate to `public_html/`**
4. **Click "Upload" button**
5. **Select ALL folders and files** from your local project
6. **Wait for upload to complete** (may take 10-30 minutes)
7. **Verify all files uploaded**

### Method 2: FTP (FileZilla) - Faster for Large Files

1. **Connect to your hosting via FTP**
   - Host: `ftp.yourdomain.com` or IP address
   - Username: Your cPanel username
   - Password: Your cPanel password
   - Port: 21

2. **Navigate to `public_html/` on server**

3. **Upload folders one by one**:
   ```
   Local → Server
   app/ → public_html/app/
   bootstrap/ → public_html/bootstrap/
   config/ → public_html/config/
   database/ → public_html/database/
   docs/ → public_html/docs/
   public/ → public_html/public/
   resources/ → public_html/resources/
   routes/ → public_html/routes/
   storage/ → public_html/storage/
   vendor/ → public_html/vendor/
   ```

4. **Upload individual files** to `public_html/`:
   - `.env.example`
   - `.htaccess`
   - `artisan`
   - `composer.json`
   - `composer.lock`
   - `install.php`
   - `package.json`
   - `README.md`
   - `DEPLOYMENT_GUIDE.md`
   - `README-DEPLOYMENT.md`
   - `vite.config.js`
   - `tailwind.config.js`
   - `postcss.config.js`
   - `phpunit.xml`

---

## 🔍 Verification Checklist

After uploading, verify these exist:

### Critical Files (Must Exist)
- [ ] `public_html/install.php` - Installation wizard
- [ ] `public_html/public/index.php` - Entry point
- [ ] `public_html/public/build/` - Built assets
- [ ] `public_html/vendor/autoload.php` - Composer autoloader
- [ ] `public_html/bootstrap/app.php` - Bootstrap

### Critical Folders (Must Exist)
- [ ] `public_html/app/Http/Controllers/` - Controllers
- [ ] `public_html/app/Models/` - Models
- [ ] `public_html/config/` - Config files
- [ ] `public_html/routes/` - Routes
- [ ] `public_html/resources/js/` - React components
- [ ] `public_html/vendor/` - Dependencies

---

## 🚀 Quick Verification Command

If you have SSH access, run:

```bash
cd ~/public_html
ls -la
```

You should see:
- app/
- bootstrap/
- config/
- database/
- docs/
- public/
- resources/
- routes/
- storage/
- vendor/
- install.php
- .env.example
- ...and other files

---

## ⚠️ Common Mistakes to Avoid

1. ❌ **Don't upload only the `public/build/` folder** - You need ALL files
2. ❌ **Don't forget hidden files** - `.htaccess`, `.env.example`
3. ❌ **Don't skip the `vendor/` folder** - Contains all PHP dependencies
4. ❌ **Don't upload `node_modules/`** - Not needed (already built)
5. ❌ **Don't create nested public_html** - Upload TO public_html, not INTO it

---

## 📦 Alternative: Create ZIP File

If your hosting supports ZIP extraction:

1. **Create ZIP locally** with all required files/folders
2. **Upload ZIP to `public_html/`**
3. **Extract via cPanel File Manager**
4. **Delete ZIP file** after extraction

---

## ✅ Next Steps After Upload

1. **Create MySQL database** in cPanel
2. **Go to** `https://yourdomain.com/install.php`
3. **Follow installation wizard**
4. **Delete install.php** when done

---

**Questions?** See `DEPLOYMENT_GUIDE.md` for detailed troubleshooting.
