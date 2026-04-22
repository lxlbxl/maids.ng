<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\Api\MatchingController as ApiMatchingController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Maid\MaidController;
use App\Http\Controllers\Api\Employer\EmployerController;
use App\Http\Controllers\Api\Booking\BookingController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Admin\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are designed for the "Agentic Era" - optimized for AI agents
| and third-party integrations. All responses follow a standardized format
| with metadata, clear status codes, and structured data.
|
| Authentication: Laravel Sanctum (Token-based)
| Response Format: JSON with standardized envelope
| Version: 1.0.0
|
*/

// API Version Prefix
Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes (No Authentication Required)
    |--------------------------------------------------------------------------
    */

    // Health Check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'Maids.ng API',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public Maid Discovery
    Route::get('/maids', [MaidController::class, 'index']);
    Route::get('/maids/search', [MaidController::class, 'search']);
    Route::get('/maids/top-rated', [MaidController::class, 'getTopRated']);
    Route::get('/maids/verified', [MaidController::class, 'getVerified']);
    Route::get('/maids/{id}', [MaidController::class, 'show']);

    // Reference Data
    Route::get('/reference/skills', [MaidController::class, 'getSkills']);
    Route::get('/reference/help-types', [MaidController::class, 'getHelpTypes']);
    Route::get('/reference/payment-methods', [PaymentController::class, 'getPaymentMethods']);

    // Public Matching API
    Route::post('/matching/find', [ApiMatchingController::class, 'findMatches']);

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Authentication Required)
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum'])->group(function () {

        // User Profile
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);

        /*
        |--------------------------------------------------------------------------
        | Maid Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('maid')->middleware(['role:maid'])->group(function () {
            // Profile Management
            Route::get('/profile', [MaidController::class, 'myProfile']);
            Route::put('/profile', [MaidController::class, 'updateProfile']);
            Route::put('/bank-details', [MaidController::class, 'updateBankDetails']);

            // Bookings
            Route::get('/bookings', [BookingController::class, 'getMaidBookings']);
            Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
        });

        /*
        |--------------------------------------------------------------------------
        | Employer Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('employer')->middleware(['role:employer'])->group(function () {
            // Preferences
            Route::get('/preferences', [EmployerController::class, 'getPreferences']);
            Route::post('/preferences', [EmployerController::class, 'createPreference']);
            Route::put('/preferences/{id}', [EmployerController::class, 'updatePreference']);
            Route::delete('/preferences/{id}', [EmployerController::class, 'deletePreference']);

            // Bookings
            Route::get('/bookings', [EmployerController::class, 'getBookings']);

            // Reviews
            Route::get('/reviews', [EmployerController::class, 'getReviews']);
            Route::post('/reviews', [EmployerController::class, 'createReview']);

            // Dashboard
            Route::get('/dashboard', [EmployerController::class, 'getDashboardStats']);
        });

        /*
        |--------------------------------------------------------------------------
        | Booking Routes (All Authenticated Users)
        |--------------------------------------------------------------------------
        */

        Route::prefix('bookings')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::post('/', [BookingController::class, 'store']);
            Route::get('/statistics', [BookingController::class, 'getStatistics']);
            Route::get('/{id}', [BookingController::class, 'show']);
            Route::post('/{id}/start', [BookingController::class, 'start']);
            Route::post('/{id}/complete', [BookingController::class, 'complete']);
            Route::post('/{id}/cancel', [BookingController::class, 'cancel']);
        });

        /*
        |--------------------------------------------------------------------------
        | Payment Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index']);
            Route::post('/initialize', [PaymentController::class, 'initialize']);
            Route::get('/verify/{reference}', [PaymentController::class, 'verify']);
            Route::get('/statistics', [PaymentController::class, 'getStatistics']);
            Route::post('/{id}/retry', [PaymentController::class, 'retry']);
            Route::get('/{id}', [PaymentController::class, 'show']);
        });

        // Payment Webhook (Public but protected by signature)
        Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

        /*
        |--------------------------------------------------------------------------
        | Admin Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('admin')->middleware(['role:admin'])->group(function () {
            // Dashboard
            Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);
            Route::get('/system-health', [AdminController::class, 'getSystemHealth']);

            // Users
            Route::get('/users', [AdminController::class, 'listUsers']);
            Route::get('/users/{id}', [AdminController::class, 'getUser']);
            Route::put('/users/{id}/status', [AdminController::class, 'updateUserStatus']);

            // Maids
            Route::get('/maids', [AdminController::class, 'listMaids']);
            Route::put('/maids/{id}/verify', [AdminController::class, 'verifyMaid']);

            // Bookings
            Route::get('/bookings', [AdminController::class, 'listBookings']);

            // Payments
            Route::get('/payments', [AdminController::class, 'listPayments']);
            Route::get('/revenue-report', [AdminController::class, 'getRevenueReport']);

            // Reviews
            Route::get('/reviews', [AdminController::class, 'listReviews']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Legacy Routes (Backward Compatibility)
|--------------------------------------------------------------------------
*/

// Keep existing routes for backward compatibility
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public matching API - no authentication required
Route::post('/matching/find', [ApiMatchingController::class, 'findMatches']);
