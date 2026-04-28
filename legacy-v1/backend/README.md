# Maids.ng Backend

PHP backend API and Admin Panel for Maids.ng - a platform connecting households with domestic workers in Nigeria.

## Requirements

- PHP 8.1+
- Composer
- SQLite (development) or MySQL (production)

## Quick Start

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Edit .env with your credentials
# - Flutterwave keys
# - Paystack keys
# - QoreID token
# - N8N webhook URL

# Run migrations
composer migrate

# Seed sample data
composer seed

# Start development server
composer start
```

Server runs at: http://localhost:8000

## Default Credentials

After running `composer seed`:

| User Type | Credentials |
|-----------|-------------|
| **Admin** | admin@maids.ng / admin123 |
| **Employer** | 08011111111 / 1234 |
| **Helpers** | 0801234500X / 1234 |

## API Endpoints

### Public API

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/register` | User registration |
| GET | `/api/helpers/match` | Get matched helpers |
| POST | `/api/helpers` | Create helper profile |
| GET | `/api/dashboard` | User dashboard |
| POST | `/api/bookings` | Create booking |
| POST | `/api/payments/verify` | Verify payment |
| POST | `/api/verification/nin` | Submit NIN verification |
| GET | `/api/config/site` | Site configuration |

### Admin API

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/admin/api/auth/login` | Admin login |
| GET | `/admin/api/dashboard` | Dashboard KPIs |
| GET | `/admin/api/helpers` | List helpers |
| GET | `/admin/api/bookings` | List bookings |
| GET | `/admin/api/payments` | List payments |
| GET | `/admin/api/verifications` | Pending verifications |
| GET | `/admin/api/settings` | Site settings |

## Directory Structure

```
backend/
├── bin/                    # CLI scripts
│   ├── migrate.php        # Run migrations
│   └── seed.php           # Seed database
├── config/                 # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── payments.php
│   ├── webhooks.php
│   ├── routes.php
│   ├── admin-routes.php
│   └── container.php
├── public/                 # Web root
│   ├── index.php          # API entry point
│   ├── admin/             # Admin panel SPA
│   ├── dashboard/         # User dashboard SPA
│   └── uploads/           # User uploads
├── src/                    # Application code
│   ├── Controllers/       # HTTP controllers
│   ├── Services/          # Business logic
│   ├── Middleware/        # HTTP middleware
│   └── Database/          # Database layer
└── storage/               # App storage
    ├── logs/
    └── database.sqlite
```

## Features

### User Types
- **Employers**: Hire domestic workers
- **Helpers**: Register as domestic workers
- **Admins**: Manage platform

### Key Features
- Session-based authentication
- Role-based access control (RBAC)
- SQLite/MySQL support
- Flutterwave + Paystack payments
- QoreID NIN verification
- n8n webhook integrations
- Mobile-first admin panel
- Household dashboard

## Configuration

### Payment Gateways

Update `.env` with your payment keys:

```env
# Flutterwave
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_xxx
FLUTTERWAVE_SECRET_KEY=FLWSECK_xxx

# Paystack
PAYSTACK_PUBLIC_KEY=pk_xxx
PAYSTACK_SECRET_KEY=sk_xxx
```

### QoreID NIN Verification

```env
QOREID_TOKEN=your-token-from-qoreid-dashboard
```

The system uses QoreID's NIN Premium (with NIN) endpoint for verification.

### n8n Webhooks

All notifications (WhatsApp, SMS, Email) are sent via n8n webhooks:

```env
N8N_BASE_URL=https://your-n8n-instance.com/webhook
N8N_WEBHOOK_SECRET=your-secret
```

Events triggered:
- `booking_created`
- `payment_success`
- `payment_failed`
- `helper_verified`
- `verification_rejected`
- `new_lead`
- `new_rating`

## Production Deployment

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Configure MySQL instead of SQLite
3. Set up proper file permissions for `storage/` and `public/uploads/`
4. Configure your web server (Apache/Nginx) to point to `public/`
5. Enable HTTPS
6. Set up scheduled tasks for webhook retries

## License

Proprietary - Maids.ng
