@echo off
setlocal enabledelayedexpansion
title Maids.ng Control Room Setup
echo ============================================
echo   Maids.ng - Control Room Deploy Script
echo ============================================
echo.

:: Step 1: Install PHP dependencies
echo [1/4] Installing PHP dependencies...
call composer install --no-interaction --prefer-dist --optimize-autoloader
if %errorlevel% neq 0 (
    echo ERROR: composer install failed
    pause
    exit /b 1
)
echo OK - composer install complete
echo.

:: Step 2: Run database migrations
echo [2/4] Running database migrations...
call php artisan migrate --force
if %errorlevel% neq 0 (
    echo ERROR: migrate failed
    pause
    exit /b 1
)
echo OK - migrations complete
echo.

:: Step 3: Seed agent defaults
echo [3/4] Seeding agent override defaults...
call php artisan db:seed --class=AgentOverrideSeeder --force
if %errorlevel% neq 0 (
    echo ERROR: seed failed
    pause
    exit /b 1
)
echo OK - agent overrides seeded
echo.

:: Step 4: Install and build frontend
echo [4/4] Installing and building frontend assets...
if not exist "node_modules" (
    call npm install --no-audit --no-fund
    if %errorlevel% neq 0 (
        echo ERROR: npm install failed
        pause
        exit /b 1
    )
)
call npm run build
if %errorlevel% neq 0 (
    echo ERROR: npm run build failed
    pause
    exit /b 1
)
echo OK - frontend built
echo.

:: Done
echo ============================================
echo   Setup Complete!
echo   Access the Control Room at:
echo   /admin/control-room
echo ============================================
pause
