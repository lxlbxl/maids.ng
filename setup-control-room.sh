#!/usr/bin/env bash
set -euo pipefail

echo "============================================"
echo "  Maids.ng - Control Room Deploy Script"
echo "============================================"
echo ""

# Step 1: Install PHP dependencies
echo "[1/4] Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader
echo "OK"
echo ""

# Step 2: Run database migrations
echo "[2/4] Running database migrations..."
php artisan migrate --force
echo "OK"
echo ""

# Step 3: Seed agent defaults
echo "[3/4] Seeding agent override defaults..."
php artisan db:seed --class=AgentOverrideSeeder --force
echo "OK"
echo ""

# Step 4: Install and build frontend
echo "[4/4] Installing and building frontend assets..."
if [ ! -d "node_modules" ]; then
    npm install --no-audit --no-fund
fi
npm run build
echo "OK"
echo ""

# Optional: clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

echo "============================================"
echo "  Setup Complete!"
echo "  Access the Control Room at:"
echo "  /admin/control-room"
echo "============================================"
