# Maids.ng v2 - Laravel 11 + Inertia React Setup Guide

## Project Overview

Maids.ng v2 is a complete rebuild of the domestic staffing platform using:
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React 18 + Inertia.js
- **Styling**: Tailwind CSS
- **Database**: MySQL/PostgreSQL
- **Authentication**: Laravel Breeze + Spatie RBAC

## Phase 1 Complete ✅

### What's Been Built

#### 1. Database Schema (10 Migrations)
- `users` - Core user accounts
- `maids` - Maid profiles with verification
- `employers` - Employer profiles
- `skills` - Available skills catalog
- `maid_skills` - Maid-skill relationships
- `bookings` - Booking records
- `payments` - Payment transactions
- `verification_documents` - NIN/doc uploads
- `audit_logs` - Activity tracking
- `settings` - System configuration

#### 2. Eloquent Models (9 Models)
- `User` - Authentication + roles
- `Maid` - Profile + relationships
- `Employer` - Profile + relationships
- `Skill` - Skills catalog
- `Booking` - Booking management
- `Payment` - Payment records
- `VerificationDocument` - Document uploads
- `AuditLog` - Activity logging
- `Setting` - Configuration

#### 3. Authentication System
- Login/Register with role selection
- Password reset
- Email verification ready
- Spatie RBAC integration
- Role middleware (admin, maid, employer)

#### 4. React Components
- **Layouts**: GuestLayout, AuthenticatedLayout
- **Pages**:
  - Welcome (Landing)
  - Auth: Login, Register, ForgotPassword
  - Admin: Dashboard
  - Maid: Dashboard, Onboarding, Profile, Verification, Bookings, Earnings
  - Employer: Dashboard, Onboarding, Profile, Maids, Bookings, Payments

#### 5. Dashboard Controllers
- `Admin\DashboardController` - Admin stats
- `Maid\DashboardController` - Maid stats
- `Employer\DashboardController` - Employer stats

## Setup Instructions

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+ or PostgreSQL 14+

### Step 1: Install PHP Dependencies

```bash
cd maids-ng-v2
composer install
```

### Step 2: Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` file:
```env
APP_NAME="Maids.ng"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maids_ng_v2
DB_USERNAME=root
DB_PASSWORD=your_password

MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_user
MAIL_PASSWORD=your_mailtrap_pass
```

### Step 3: Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE maids_ng_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed
```

### Step 4: Install Node Dependencies

```bash
npm install
```

### Step 5: Build Assets

```bash
# Development
npm run dev

# Production
npm run build
```

### Step 6: Start Development Server

```bash
php artisan serve
```

Visit: http://localhost:8000

## Default Users (After Seeding)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@maids.ng | password |
| Maid | maid@maids.ng | password |
| Employer | employer@maids.ng | password |

## Project Structure

```
maids-ng-v2/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   ├── Auth/
│   │   │   ├── Employer/
│   │   │   └── Maid/
│   │   └── Middleware/
│   ├── Models/
│   └── Providers/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── js/
│   │   ├── Layouts/
│   │   ├── Pages/
│   │   │   ├── Admin/
│   │   │   ├── Auth/
│   │   │   ├── Employer/
│   │   │   └── Maid/
│   │   └── app.jsx
│   ├── css/
│   └── views/
├── routes/
│   └── web.php
└── storage/
```

## Available Routes

### Public
- `/` - Welcome page

### Guest
- `/login` - Login
- `/register` - Register
- `/forgot-password` - Password reset

### Authenticated
- `/logout` - Logout

### Admin (role:admin)
- `/admin/dashboard` - Admin dashboard

### Maid (role:maid)
- `/maid/dashboard` - Maid dashboard
- `/maid/onboarding` - Onboarding
- `/maid/profile` - Profile
- `/maid/verification` - Verification
- `/maid/bookings` - Bookings
- `/maid/earnings` - Earnings

### Employer (role:employer)
- `/employer/dashboard` - Employer dashboard
- `/employer/onboarding` - Onboarding
- `/employer/profile` - Profile
- `/employer/maids` - Find maids
- `/employer/bookings` - Bookings
- `/employer/payments` - Payments

## Next Steps (Phase 2)

1. **Maid Profile Management**
   - Complete profile form
   - Photo upload
   - Skills selection
   - Experience details

2. **NIN Verification System**
   - Document upload
   - Verification workflow
   - Admin approval

3. **Employer Features**
   - Search/filter maids
   - Booking creation
   - Payment integration

4. **Booking System**
   - Booking workflow
   - Status management
   - Notifications

5. **Payment Integration**
   - Paystack/Flutterwave
   - Commission calculation
   - Payout system

## Development Commands

```bash
# Run migrations fresh with seed
php artisan migrate:fresh --seed

# Create new controller
php artisan make:controller Api/MaidController

# Create new model with migration
php artisan make:model Service -m

# Run tests
php artisan test

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Storage link
php artisan storage:link
```

## Troubleshooting

### Common Issues

1. **Vite manifest not found**
   ```bash
   npm run build
   ```

2. **Permission denied on storage**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

3. **Database connection failed**
   - Check `.env` DB credentials
   - Ensure MySQL is running
   - Create database manually

4. **Class not found errors**
   ```bash
   composer dump-autoload
   ```

## Security Notes

- All passwords are hashed with bcrypt
- CSRF protection enabled
- Role-based access control implemented
- SQL injection protection via Eloquent
- XSS protection via React escaping

## License

Private - Maids.ng Platform
