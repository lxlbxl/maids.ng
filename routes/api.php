<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\Api\MatchingController as ApiMatchingController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Maid\MaidController as LegacyMaidController;
use App\Http\Controllers\Api\Employer\EmployerController as LegacyEmployerController;
use App\Http\Controllers\Api\Booking\BookingController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Admin\AdminController as LegacyAdminController;

// New AI-Native System Controllers
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MaidController;
use App\Http\Controllers\Api\EmployerController;
use App\Http\Controllers\Api\ReportController;

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
        | AI-Native Maid Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('maid')->middleware(['role:maid'])->group(function () {
            // Profile Management
            Route::get('/profile', [MaidController::class, 'profile']);
            Route::put('/profile', [MaidController::class, 'updateProfile']);
            Route::put('/availability', [MaidController::class, 'updateAvailability']);

            // Assignments
            Route::get('/assignments', [MaidController::class, 'assignments']);
            Route::get('/assignments/{id}', [MaidController::class, 'assignmentDetail']);

            // Earnings & Payments
            Route::get('/earnings', [MaidController::class, 'earnings']);
            Route::get('/payments', [MaidController::class, 'paymentHistory']);
            Route::get('/upcoming-payments', [MaidController::class, 'upcomingPayments']);

            // Dashboard
            Route::get('/dashboard', [MaidController::class, 'dashboard']);
        });

        /*
        |--------------------------------------------------------------------------
        | Legacy Maid Routes (Backward Compatibility)
        |--------------------------------------------------------------------------
        */

        Route::prefix('maid-legacy')->middleware(['role:maid'])->group(function () {
            // Profile Management
            Route::get('/profile', [LegacyMaidController::class, 'myProfile']);
            Route::put('/profile', [LegacyMaidController::class, 'updateProfile']);
            Route::put('/bank-details', [LegacyMaidController::class, 'updateBankDetails']);

            // Bookings
            Route::get('/bookings', [BookingController::class, 'getMaidBookings']);
            Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
        });

        /*
        |--------------------------------------------------------------------------
        | AI-Native Employer Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('employer')->middleware(['role:employer'])->group(function () {
            // Profile
            Route::get('/profile', [EmployerController::class, 'profile']);
            Route::put('/profile', [EmployerController::class, 'updateProfile']);

            // Preferences
            Route::get('/preferences', [EmployerController::class, 'preferences']);
            Route::post('/preferences', [EmployerController::class, 'createPreference']);
            Route::get('/preferences/{id}', [EmployerController::class, 'preferenceDetail']);
            Route::put('/preferences/{id}', [EmployerController::class, 'updatePreference']);
            Route::post('/preferences/{id}/cancel', [EmployerController::class, 'cancelPreference']);

            // Assignments
            Route::get('/assignments', [EmployerController::class, 'assignments']);
            Route::get('/assignments/{id}', [EmployerController::class, 'assignmentDetail']);

            // Spending & Payments
            Route::get('/spending', [EmployerController::class, 'spending']);
            Route::get('/payments', [EmployerController::class, 'paymentHistory']);
            Route::get('/upcoming-payments', [EmployerController::class, 'upcomingPayments']);

            // Reviews
            Route::post('/reviews', [EmployerController::class, 'submitReview']);
            Route::get('/my-reviews', [EmployerController::class, 'myReviews']);

            // Dashboard
            Route::get('/dashboard', [EmployerController::class, 'dashboard']);
        });

        /*
        |--------------------------------------------------------------------------
        | Legacy Employer Routes (Backward Compatibility)
        |--------------------------------------------------------------------------
        */

        Route::prefix('employer-legacy')->middleware(['role:employer'])->group(function () {
            // Preferences
            Route::get('/preferences', [LegacyEmployerController::class, 'getPreferences']);
            Route::post('/preferences', [LegacyEmployerController::class, 'createPreference']);
            Route::put('/preferences/{id}', [LegacyEmployerController::class, 'updatePreference']);
            Route::delete('/preferences/{id}', [LegacyEmployerController::class, 'deletePreference']);

            // Bookings
            Route::get('/bookings', [LegacyEmployerController::class, 'getBookings']);

            // Reviews
            Route::get('/reviews', [LegacyEmployerController::class, 'getReviews']);
            Route::post('/reviews', [LegacyEmployerController::class, 'createReview']);

            // Dashboard
            Route::get('/dashboard', [LegacyEmployerController::class, 'getDashboardStats']);
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
        | AI-Native Assignment Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('assignments')->group(function () {
            Route::get('/', [AssignmentController::class, 'index']);
            Route::post('/', [AssignmentController::class, 'store']);
            Route::get('/statistics', [AssignmentController::class, 'statistics']);
            Route::get('/{id}', [AssignmentController::class, 'show']);
            Route::post('/{id}/accept', [AssignmentController::class, 'accept']);
            Route::post('/{id}/reject', [AssignmentController::class, 'reject']);
            Route::post('/{id}/complete', [AssignmentController::class, 'complete']);
            Route::post('/{id}/cancel', [AssignmentController::class, 'cancel']);
        });

        /*
        |--------------------------------------------------------------------------
        | AI-Native Wallet Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('wallets')->group(function () {
            Route::get('/', [WalletController::class, 'show']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
            Route::post('/deposit', [WalletController::class, 'credit']);
            Route::post('/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/withdrawals/pending', [WalletController::class, 'withdrawals']);
        });

        /*
        |--------------------------------------------------------------------------
        | AI-Native Salary Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('salary')->group(function () {
            Route::get('/schedules', [SalaryController::class, 'index']);
            Route::get('/schedules/{id}', [SalaryController::class, 'show']);
            Route::post('/schedules/{id}/pay', [SalaryController::class, 'pay']);
            Route::get('/payments', [SalaryController::class, 'history']);
            Route::get('/overdue', [SalaryController::class, 'overdue']);
            Route::get('/statistics', [SalaryController::class, 'statistics']);
        });

        /*
        |--------------------------------------------------------------------------
        | AI-Native Matching Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('matching')->group(function () {
            Route::post('/request', [MatchingController::class, 'requestMatch']);
            Route::get('/status/{jobId}', [MatchingController::class, 'status']);
            Route::get('/results/{jobId}', [MatchingController::class, 'results']);
            Route::post('/manual-assign', [MatchingController::class, 'review']);
            Route::get('/queue', [MatchingController::class, 'statistics']);
        });

        /*
        |--------------------------------------------------------------------------
        | AI-Native Notification Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
        });

        /*
        |--------------------------------------------------------------------------
        | AI-Native Admin Routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('admin')->middleware(['role:admin'])->group(function () {
            // Dashboard
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/system-health', [AdminController::class, 'systemHealth']);

            // Users
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{id}', [AdminController::class, 'userDetail']);
            Route::put('/users/{id}/status', [AdminController::class, 'updateUserStatus']);
            Route::post('/users/{id}/verify-maid', [AdminController::class, 'verifyMaid']);

            // Assignments
            Route::get('/assignments', [AdminController::class, 'assignments']);
            Route::get('/assignments/{id}', [AdminController::class, 'assignmentDetail']);
            Route::post('/assignments/{id}/cancel', [AdminController::class, 'cancelAssignment']);

            // Withdrawals
            Route::get('/withdrawals', [AdminController::class, 'withdrawals']);
            Route::get('/withdrawals/pending', [AdminController::class, 'pendingWithdrawals']);
            Route::post('/withdrawals/{id}/approve', [AdminController::class, 'approveWithdrawal']);
            Route::post('/withdrawals/{id}/reject', [AdminController::class, 'rejectWithdrawal']);

            // Settings
            Route::get('/settings', [AdminController::class, 'settings']);
            Route::put('/settings', [AdminController::class, 'updateSettings']);

            // AI Configuration
            Route::get('/ai/config', [AdminController::class, 'aiConfig']);
            Route::put('/ai/config', [AdminController::class, 'updateAiConfig']);
            Route::post('/ai/test-connection', [AdminController::class, 'testAiConnection']);

            // Reports
            Route::get('/reports/platform-overview', [ReportController::class, 'platformOverview']);
            Route::get('/reports/financial', [ReportController::class, 'financialReport']);
            Route::get('/reports/user-activity', [ReportController::class, 'userActivityReport']);
            Route::get('/reports/assignment-analytics', [ReportController::class, 'assignmentAnalytics']);
            Route::get('/reports/ai-matching', [ReportController::class, 'aiMatchingReport']);
            Route::get('/reports/notifications', [ReportController::class, 'notificationReport']);
            Route::get('/reports/agent-logs', [ReportController::class, 'agentActivityLogs']);
            
            // New AI-Native Stats & Controls
            Route::get('/matching/queue', [ApiMatchingController::class, 'getQueueStatus']);
            Route::get('/matching/statistics', [ApiMatchingController::class, 'statistics']);

            // Salary Management Oversight
            Route::get('/salary/schedules', [AdminController::class, 'salarySchedules']);
            Route::get('/salary/overdue', [AdminController::class, 'overdueSalaries']);
            Route::post('/salary/{id}/escalate', [AdminController::class, 'escalateSalary']);
            Route::post('/salary/{id}/remind', [AdminController::class, 'sendSalaryReminder']);
            Route::post('/salary/batch-pay', [AdminController::class, 'processBatchPayment']);
            Route::post('/salary/{id}/mark-paid', [AdminController::class, 'markSchedulePaid']);
            Route::get('/salary/payments', [AdminController::class, 'salaryPaymentHistory']);
            Route::get('/salary/statistics', [SalaryController::class, 'statistics']);

            // AI Matching Engine Monitor
            Route::get('/ai-matching/monitor', [AdminController::class, 'aiMatchingMonitor']);
            Route::get('/ai-matching/jobs/{jobId}', [AdminController::class, 'aiMatchingJobDetail']);
            Route::post('/ai-matching/jobs/{jobId}/retry', [AdminController::class, 'retryMatchingJob']);
            Route::post('/ai-matching/jobs/{jobId}/cancel', [AdminController::class, 'cancelMatchingJob']);

            // Wallet Oversight
            Route::get('/wallets/overview', [AdminController::class, 'walletOverview']);
            Route::post('/wallets/{walletId}/adjust', [AdminController::class, 'adjustWalletBalance']);
            
            Route::post('/reports/export', [ReportController::class, 'export']);
        });

        /*
        |--------------------------------------------------------------------------
        | Legacy Admin Routes (Backward Compatibility)
        |--------------------------------------------------------------------------
        */

        Route::prefix('admin-legacy')->middleware(['role:admin'])->group(function () {
            // Dashboard
            Route::get('/dashboard', [LegacyAdminController::class, 'getDashboardStats']);
            Route::get('/system-health', [LegacyAdminController::class, 'getSystemHealth']);

            // Users
            Route::get('/users', [LegacyAdminController::class, 'listUsers']);
            Route::get('/users/{id}', [LegacyAdminController::class, 'getUser']);
            Route::put('/users/{id}/status', [LegacyAdminController::class, 'updateUserStatus']);

            // Maids
            Route::get('/maids', [LegacyAdminController::class, 'listMaids']);
            Route::put('/maids/{id}/verify', [LegacyAdminController::class, 'verifyMaid']);

            // Bookings
            Route::get('/bookings', [LegacyAdminController::class, 'listBookings']);

            // Payments
            Route::get('/payments', [LegacyAdminController::class, 'listPayments']);
            Route::get('/revenue-report', [LegacyAdminController::class, 'getRevenueReport']);

            // Reviews
            Route::get('/reviews', [LegacyAdminController::class, 'listReviews']);

            // AI Settings - Fetch models from provider API
            Route::get('/ai/models/{provider}', [LegacyAdminController::class, 'fetchAiModels']);
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
