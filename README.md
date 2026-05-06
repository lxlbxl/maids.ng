# Maids.ng - AI-Powered Domestic Staff Matching Platform

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-Proprietary-green.svg)]()

Maids.ng is a next-generation domestic staff matching platform that leverages AI to connect employers with verified domestic helpers in Nigeria. Built with Laravel 11, Inertia.js, and React, it features an intelligent matching engine, automated salary management, and comprehensive verification systems.

## 🚀 Features

### Core Platform
- **AI-Powered Matching**: Intelligent algorithm matches employers with suitable maids based on preferences, skills, and location
- **Verified Profiles**: NIN verification and background checks for all domestic staff
- **Secure Payments**: Integrated payment processing with escrow support
- **Real-time Notifications**: SMS, Email, and WhatsApp notifications via multiple providers
- **Role-based Access**: Admin, Employer, and Maid dashboards with appropriate permissions

### AI-Native Architecture
- **6 Specialized AI Agents**: Matching, Verification, Notification, Salary, Support, and Analytics agents
- **AI Matching Queue**: Asynchronous job processing for intelligent matching
- **Confidence Scoring**: AI-generated match scores with detailed reasoning
- **Automated Follow-ups**: AI-driven notification sequences and reminders

### Financial Management
- **Dual Wallet System**: Separate wallets for employers and maids
- **Escrow Protection**: Secure salary holding until work completion
- **Automated Salary Scheduling**: Recurring payment reminders and processing
- **Withdrawal Management**: Admin-controlled withdrawal approvals

### Verification & Trust
- **NIN Verification**: National Identity Number validation
- **Document Upload**: Support for certificates and credentials
- **Review System**: Rating and review mechanism for both parties
- **Guarantee Match**: Premium service with replacement guarantee

## 🛠️ Tech Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: React 18, Inertia.js, Tailwind CSS
- **Database**: MySQL/PostgreSQL with SQLite for testing
- **AI Integration**: OpenAI GPT-4, Anthropic Claude
- **SMS Providers**: Termii, Twilio, Africa's Talking
- **Payment**: Paystack, Flutterwave
- **Queue**: Laravel Queues with database driver
- **Testing**: PHPUnit, Pest

## 📋 Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ and NPM
- MySQL 8.0+ or PostgreSQL 14+
- Redis (optional, for caching and queues)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/lxlbxl/maids.ng.git
cd maids.ng
```

### 2. Install PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

For development:
```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
npm run build
```

### 4. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure:
- Database credentials
- Mail settings
- SMS provider credentials (Termii/Twilio)
- Payment gateway keys (Paystack/Flutterwave)
- AI provider keys (OpenAI/Anthropic)

### 5. Database Setup

```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder
```

### 6. Storage Linking

```bash
php artisan storage:link
```

### 7. Optimization (Production)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🔧 Configuration

### SMS Providers

Configure SMS providers in `config/sms.php`:

```php
'default' => env('SMS_PROVIDER', 'termii'),

'providers' => [
    'termii' => [
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID'),
    ],
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
    'africas_talking' => [
        'username' => env('AT_USERNAME'),
        'api_key' => env('AT_API_KEY'),
        'from' => env('AT_FROM'),
    ],
],
```

### AI Configuration

Set AI provider in settings via admin panel or database:

```php
Setting::set('ai_provider', 'openai');
Setting::set('openai_api_key', 'your-api-key');
Setting::set('openai_model', 'gpt-4');
```

### Payment Gateways

Configure in `.env`:

```env
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_WEBHOOK_SECRET=whsec_...

FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-...
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...
FLUTTERWAVE_ENCRYPTION_KEY=...
```

## 🧪 Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suites

```bash
# Feature tests
php artisan test --testsuite=Feature

# Unit tests
php artisan test --testsuite=Unit

# Specific test file
php artisan test tests/Feature/Api/AssignmentTest.php
```

### Test Coverage

The platform includes comprehensive tests for:
- **API Endpoints**: Assignment, Notification, Matching Queue, Wallet, Salary
- **Model Logic**: SalarySchedule calculations, Wallet transactions
- **Authentication**: Role-based access control
- **Payment Flows**: Webhook handling, transaction processing

## 📚 API Documentation

### Authentication

All API endpoints (except public ones) require Bearer token authentication:

```http
Authorization: Bearer {your-token}
```

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/health` | Health check |
| GET | `/api/v1/maids` | List maids |
| POST | `/api/v1/matching/find` | Find matches |
| POST | `/api/v1/auth/register` | Register user |
| POST | `/api/v1/auth/login` | Login user |

### Protected Endpoints

#### Employer
- `GET /api/v1/employer/dashboard` - Dashboard data
- `GET /api/v1/employer/assignments` - View assignments
- `POST /api/v1/assignments/{id}/accept` - Accept assignment
- `GET /api/v1/wallets` - View wallet

#### Maid
- `GET /api/v1/maid/dashboard` - Dashboard data
- `GET /api/v1/maid/assignments` - View assignments
- `GET /api/v1/maid/earnings` - View earnings

#### Admin
- `GET /api/v1/admin/dashboard` - Admin dashboard
- `GET /api/v1/admin/users` - List users
- `GET /api/v1/admin/assignments` - All assignments
- `GET /api/v1/admin/matching/queue` - Matching queue status

See `docs/api/` for complete API documentation.

## 🚀 Deployment

### Shared Hosting Deployment

1. **Upload files** via FTP/SFTP or Git
2. **Set document root** to `public/` directory
3. **Create database** and import schema
4. **Configure `.env`** with production credentials:
   - Set `QUEUE_CONNECTION=database`
5. **Run deployment** (admin only):
   ```
   GET /deploy-all?token={DEPLOY_SECRET}
   ```

### 📋 Background Tasks & Cron Jobs (Shared Hosting)

For the platform to function correctly on shared hosting, you must set up the following Cron Jobs in your control panel (e.g., cPanel):

| Frequency | Command | Purpose |
|-----------|---------|---------|
| Every Minute | `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1` | Runs the task scheduler (Reminders, AI Matching Queue) |
| Every Minute | `* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty >> /dev/null 2>&1` | Processes background jobs (SMS, Emails, Matching) |

> [!IMPORTANT]
> Replace `/path/to/project` with the actual absolute path to your application on the server.


### VPS/Cloud Deployment

1. **Server Requirements**:
   - Ubuntu 22.04 LTS
   - Nginx or Apache
   - PHP 8.2 with FPM
   - MySQL 8.0+
   - Supervisor (for queues)

2. **Nginx Configuration**:
   ```nginx
   server {
       listen 80;
       server_name maids.ng;
       root /var/www/maids.ng/public;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

3. **Queue Worker** (Supervisor):
   ```ini
   [program:maids-queue]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/maids.ng/artisan queue:work --sleep=3 --tries=3
   autostart=true
   autorestart=true
   user=www-data
   numprocs=2
   ```

4. **Cron Jobs**:
   ```bash
   * * * * * cd /var/www/maids.ng && php artisan schedule:run >> /dev/null 2>&1
   ```

## 🔐 Security

### Deploy Routes Protection

Deploy routes (`/deploy-all`, `/deploy-fix-db`) are protected with:
- Authentication required
- Admin role verification
- Deploy secret token validation

### Security Best Practices

- All passwords hashed with Bcrypt
- CSRF protection on all forms
- SQL injection prevention via Eloquent
- XSS protection via React escaping
- Rate limiting on API endpoints
- Webhook signature verification

## 📝 Environment Variables

### Required

```env
APP_NAME="Maids.ng"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://maids.ng

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maids_ng
DB_USERNAME=root
DB_PASSWORD=secret

DEPLOY_SECRET=your-secure-deploy-secret
```

### Optional (Features)

```env
# AI
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

# SMS
TERMII_API_KEY=...
TWILIO_SID=...

# Payments
PAYSTACK_SECRET_KEY=sk_...
FLUTTERWAVE_SECRET_KEY=FLWSECK_...

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation for API changes
- Use type hints and return types
- Document complex business logic

## 📄 License

This software is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.

## 🆘 Support

For support, email support@maids.ng or create an issue in the repository.

## 🙏 Acknowledgments

- Laravel Framework
- React.js Community
- Spatie Packages (Permission, Laravel Settings)
- All contributors and testers

---

**Maids.ng** - Connecting Homes with Trusted Help
