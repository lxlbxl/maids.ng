# Maids.ng - Deployment Package

## 📦 What's Included

This package contains everything needed to deploy Maids.ng on shared hosting:

- ✅ Complete application files (PHP, JS, CSS)
- ✅ Pre-built frontend assets (npm run build completed)
- ✅ Installation wizard (`install.php`)
- ✅ API documentation
- ✅ Deployment guide

## 🚀 Quick Deploy (3 Steps)

### Step 1: Upload Files
Upload all files to your shared hosting `public_html` directory.

### Step 2: Create Database
Create a MySQL database in your hosting control panel (cPanel).

### Step 3: Run Installer
Go to `https://yourdomain.com/install.php` and follow the wizard.

**Done!** Delete `install.php` after installation.

---

## 📋 System Requirements

- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.3+
- mod_rewrite enabled
- PHP Extensions: PDO, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath

---

## 📖 Documentation

- **Deployment Guide**: `DEPLOYMENT_GUIDE.md` - Complete step-by-step instructions
- **API Documentation**: `docs/api/README.md` - API reference
- **Agentic Guide**: `docs/api/AGENTIC_GUIDE.md` - AI integration guide

---

## 🔧 Post-Installation

1. **Delete** `install.php` for security
2. **Configure** payment gateways (Paystack/Flutterwave) in admin settings
3. **Set up** email SMTP in admin settings
4. **Enable** SSL/HTTPS

---

## 🆘 Support

- Check `DEPLOYMENT_GUIDE.md` for troubleshooting
- Review error logs in `storage/logs/`
- Contact hosting provider for server issues

---

**Ready to deploy!** 🎉
