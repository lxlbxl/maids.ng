<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AdminVerificationTransactionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminMaidController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminDisputeController;
use App\Http\Controllers\AdminFinancialController;
use App\Http\Controllers\AdminVerificationController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminController as ApiAdminController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Employer\DashboardController as EmployerDashboardController;
use App\Http\Controllers\EmployerProfileController;
use App\Http\Controllers\Maid\DashboardController as MaidDashboardController;
use App\Http\Controllers\MaidProfileController;
use App\Http\Controllers\MaidVerificationController;
use App\Http\Controllers\MaidSearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\MatchingFeeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminAuditLogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Routes
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Conversational Onboarding (Public - no account required upfront)
Route::get('/onboarding', function () {
    return Inertia::render('Employer/OnboardingQuiz', [
        'guaranteeFee' => (int) \App\Models\Setting::get('matching_fee_amount', 5000),
    ]);
})->name('onboarding');

// Public onboarding API routes (no auth required)
Route::post('/onboarding/create-account', [MatchingController::class, 'createAccount'])->name('onboarding.create-account');
Route::post('/onboarding/find-matches', [MatchingController::class, 'findMatches'])->name('onboarding.find-matches');
Route::post('/onboarding/guarantee-match', [MatchingController::class, 'activateGuaranteeMatch'])->name('onboarding.guarantee-match');

// Standalone Verification Service (Public - no account required)
Route::get('/verify-service', function () {
    return Inertia::render('VerificationService', [
        'fee' => (int) \App\Models\Setting::get('standalone_verification_fee', 2000)
    ]);
})->name('verify-service');

Route::post('/standalone-verification/initialize', [App\Http\Controllers\StandaloneVerificationController::class, 'initialize'])->name('standalone-verification.initialize');
Route::get('/standalone-verification/verify', [App\Http\Controllers\StandaloneVerificationController::class, 'verifyPayment'])->name('standalone-verification.verify');
Route::get('/standalone-verification/report/{reference}', [App\Http\Controllers\StandaloneVerificationController::class, 'showReport'])->name('standalone-verification.report');

// Maid Search (Public)
Route::get('/maids', [MaidSearchController::class, 'index'])->name('maids.search');
Route::get('/maids/search', [MaidSearchController::class, 'search'])->name('maids.search.api');
Route::get('/maids/featured', [MaidSearchController::class, 'featured'])->name('maids.featured');
Route::get('/maids/locations', [MaidSearchController::class, 'locations'])->name('maids.locations');
Route::get('/maids/{id}', [MaidSearchController::class, 'show'])->name('maids.show');

// Deployment & Maintenance (Protected: requires deploy_secret token AND admin role)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/deploy-all', function (\Illuminate\Http\Request $request) {
        // Validate deploy secret
        $secret = $request->query('token');
        $expectedSecret = env('DEPLOY_SECRET', \App\Models\Setting::get('deploy_secret', ''));
        if (!$secret || !$expectedSecret || $secret !== $expectedSecret) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $results = [];
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $results['migration'] = \Illuminate\Support\Facades\Artisan::output();

            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            $results['cache'] = 'All caches cleared successfully.';

            return response()->json([
                'success' => true,
                'message' => 'Deployment successful.',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deployment failed: ' . $e->getMessage(),
                'partial_results' => $results
            ], 500);
        }
    });

    Route::get('/deploy-fix-db', function (\Illuminate\Http\Request $request) {
        $secret = $request->query('token');
        $expectedSecret = env('DEPLOY_SECRET', \App\Models\Setting::get('deploy_secret', ''));
        if (!$secret || !$expectedSecret || $secret !== $expectedSecret) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $added = [];

        if (!\Illuminate\Support\Facades\Schema::hasColumn('settings', 'is_encrypted')) {
            \Illuminate\Support\Facades\Schema::table('settings', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->boolean('is_encrypted')->default(false)->after('value');
            });
            $added[] = 'settings.is_encrypted';
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('settings', 'group')) {
            \Illuminate\Support\Facades\Schema::table('settings', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('group')->default('general')->after('is_encrypted');
            });
            $added[] = 'settings.group';
        }

        if (!\Illuminate\Support\Facades\Schema::hasColumn('matching_fee_payments', 'payment_type')) {
            \Illuminate\Support\Facades\Schema::table('matching_fee_payments', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('payment_type')->default('matching_fee')->after('status');
            });
            $added[] = 'matching_fee_payments.payment_type';
        }

        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE employer_preferences MODIFY COLUMN matching_status VARCHAR(255) DEFAULT 'pending'");
            $added[] = 'employer_preferences.matching_status_varchar';
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Could not modify matching_status: ' . $e->getMessage());
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('standalone_verifications')) {
            \Illuminate\Support\Facades\Schema::create('standalone_verifications', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
                $table->string('maid_nin');
                $table->string('maid_first_name');
                $table->string('maid_last_name');
                $table->decimal('amount', 10, 2);
                $table->string('payment_reference')->unique();
                $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
                $table->string('gateway')->default('paystack');
                $table->enum('verification_status', ['pending', 'success', 'failed', 'review'])->default('pending');
                $table->json('verification_data')->nullable();
                $table->string('report_path')->nullable();
                $table->timestamps();
            });
            $added[] = 'standalone_verifications_table';
        }

        return response()->json([
            'success' => true,
            'message' => empty($added) ? 'Database is already up to date.' : 'Added missing columns/tables: ' . implode(', ', $added)
        ]);
    });
});

// Guest Routes
Route::middleware('guest')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Register
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    // Forgot Password
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    // Reset Password
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.store');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Notifications (All users)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear');
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');

    // Admin Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // People Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/maids', [AdminMaidController::class, 'index'])->name('maids');
        Route::get('/maids/{id}', [AdminMaidController::class, 'show'])->name('maids.show');
        Route::post('/maids/{id}/status', [AdminMaidController::class, 'updateStatus'])->name('maids.status');
        Route::get('/employers', [AdminUserController::class, 'employers'])->name('employers');

        // Operations
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings');
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{id}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.status');
        Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes');
        Route::post('/disputes/{id}/resolve', [AdminDisputeController::class, 'resolve'])->name('disputes.resolve');

        // Financials
        Route::get('/payments', [AdminFinancialController::class, 'payments'])->name('payments');
        Route::get('/earnings', [AdminFinancialController::class, 'earnings'])->name('earnings');
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews');
        Route::post('/reviews/{id}/flag', [AdminReviewController::class, 'toggleFlag'])->name('reviews.flag');
        Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

        // System
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications_panel');
        Route::post('/notifications', [AdminNotificationController::class, 'store'])->name('notifications.broadcast');
        Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit');
        Route::delete('/audit/purge', [AdminAuditLogController::class, 'destroyAll'])->name('audit.purge');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');

        // AI Matching & Salary Ops
        Route::get('/matching', function () {
            return Inertia::render('Admin/MatchingQueue');
        })->name('matching');

        Route::get('/salary', function () {
            return Inertia::render('Admin/SalaryManagement');
        })->name('salary');

        // Existing sub-routes
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{id}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::post('/users/{id}/role', [AdminUserController::class, 'assignRole'])->name('users.role');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications');
        Route::post('/verifications/{id}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{id}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');

        // Verification Service Transactions (Standalone NIN Verification)
        Route::get('/verification-transactions', [AdminVerificationTransactionController::class, 'index'])->name('verification-transactions');
        Route::get('/verification-transactions/{id}', [AdminVerificationTransactionController::class, 'show'])->name('verification-transactions.show');
        Route::post('/verification-transactions/{id}', [AdminVerificationTransactionController::class, 'update'])->name('verification-transactions.update');
        Route::post('/verification-transactions/pricing', [AdminVerificationTransactionController::class, 'updatePricing'])->name('verification-transactions.update-pricing');
        Route::get('/verification-transactions/export', [AdminVerificationTransactionController::class, 'export'])->name('verification-transactions.export');
        Route::get('/verification-transactions/stats', [AdminVerificationTransactionController::class, 'stats'])->name('verification-transactions.stats');

        Route::post('/escalations/{id}/resolve', [App\Http\Controllers\Admin\EscalationController::class, 'resolve'])->name('escalations.resolve');

        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('/payments/stats', [PaymentController::class, 'stats'])->name('payments.stats');
        Route::post('/notifications/test', [NotificationController::class, 'sendTest'])->name('notifications.test');

        // AI Settings - Fetch models from provider API (same controller as settings page)
        Route::get('/ai/models/{provider}', [AdminSettingsController::class, 'fetchAiModels'])->name('ai.models');

        // CSV Exports
        Route::get('/export/users', [\App\Http\Controllers\ExportController::class, 'downloadUsers'])->name('export.users');
        Route::get('/export/financials', [\App\Http\Controllers\ExportController::class, 'downloadFinancials'])->name('export.financials');

        // Cache clearing for shared hosting (no CLI access)
        Route::get('/clear-cache', function () {
            $cleared = [];

            // Clear route cache
            $routeCache = base_path('bootstrap/cache/routes-v7.php');
            if (file_exists($routeCache)) {
                @unlink($routeCache);
                $cleared[] = 'routes';
            }

            // Clear config cache
            $configCache = base_path('bootstrap/cache/config.php');
            if (file_exists($configCache)) {
                @unlink($configCache);
                $cleared[] = 'config';
            }

            // Clear compiled services
            $servicesCache = base_path('bootstrap/cache/services.php');
            if (file_exists($servicesCache)) {
                @unlink($servicesCache);
                $cleared[] = 'services';
            }

            // Clear application cache files
            $cachePath = storage_path('framework/cache/data');
            if (is_dir($cachePath)) {
                $files = glob($cachePath . '/*');
                foreach ($files as $file) {
                    if (is_file($file))
                        @unlink($file);
                    if (is_dir($file)) {
                        array_map('unlink', glob("$file/*"));
                        @rmdir($file);
                    }
                }
                $cleared[] = 'app-cache';
            }

            // Clear compiled views
            $viewPath = storage_path('framework/views');
            if (is_dir($viewPath)) {
                $files = glob($viewPath . '/*.php');
                foreach ($files as $file) {
                    @unlink($file);
                }
                $cleared[] = 'views';
            }

            return response()->json([
                'success' => true,
                'message' => 'Caches cleared: ' . (empty($cleared) ? 'nothing to clear' : implode(', ', $cleared))
            ]);
        })->name('clear-cache');
    });

    // Maid Routes
    Route::middleware(['role:maid'])->prefix('maid')->name('maid.')->group(function () {
        Route::get('/dashboard', [MaidDashboardController::class, 'index'])->name('dashboard');

        // Profile
        Route::get('/profile', [MaidProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [MaidProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo', [MaidProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::post('/profile/availability', [MaidProfileController::class, 'updateAvailability'])->name('profile.availability');
        Route::post('/profile/skills', [MaidProfileController::class, 'updateSkills'])->name('profile.skills');
        Route::post('/profile/bank-details', [MaidProfileController::class, 'updateBankDetails'])->name('profile.bank');

        // Verification
        Route::get('/verification', [MaidVerificationController::class, 'show'])->name('verification');
        Route::post('/verification/nin', [MaidVerificationController::class, 'submitNin'])->name('verification.nin');
        Route::post('/verification/nin/verify', [MaidVerificationController::class, 'verifyNin'])->name('verification.nin.verify');
        Route::post('/verification/document', [MaidVerificationController::class, 'submitDocument'])->name('verification.document');
        Route::get('/verification/status', [MaidVerificationController::class, 'status'])->name('verification.status');

        // Bookings
        Route::get('/bookings', [BookingController::class, 'indexMaid'])->name('bookings');
        Route::get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{id}/accept', [BookingController::class, 'accept'])->name('bookings.accept');
        Route::post('/bookings/{id}/reject', [BookingController::class, 'reject'])->name('bookings.reject');
        Route::get('/bookings/stats', [BookingController::class, 'stats'])->name('bookings.stats');

        // Earnings/Payments
        Route::get('/earnings', [PaymentController::class, 'indexMaid'])->name('earnings');
        Route::post('/earnings/payout', [PaymentController::class, 'requestPayout'])->name('earnings.payout');

        // Reviews
        Route::get('/reviews', [ReviewController::class, 'indexMaid'])->name('reviews');
        Route::get('/reviews/stats', [ReviewController::class, 'stats'])->name('reviews.stats');
    });

    // Employer Routes
    Route::middleware(['role:employer'])->prefix('employer')->name('employer.')->group(function () {
        Route::get('/dashboard', [EmployerDashboardController::class, 'index'])->name('dashboard');

        // Profile
        Route::get('/profile', [EmployerProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [EmployerProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo', [EmployerProfileController::class, 'updatePhoto'])->name('profile.photo');

        // Onboarding & Matching
        Route::get('/onboarding', function () {
            return Inertia::render('Employer/OnboardingQuiz');
        })->name('onboarding');
        Route::post('/matching/find', [MatchingController::class, 'findMatches'])->name('matching.find');
        Route::post('/matching/select', [MatchingController::class, 'selectMaid'])->name('matching.select');
        Route::get('/matching/payment/{preference}', [MatchingController::class, 'showPayment'])->name('matching.payment');
        Route::get('/guarantee-match/payment/{preference}', [MatchingController::class, 'showGuaranteePayment'])->name('guarantee-match.payment');

        // Matching Fee Payments
        Route::post('/matching-fee/initialize', [MatchingFeeController::class, 'initialize'])->name('matching-fee.initialize');
        Route::get('/matching-fee/verify', [MatchingFeeController::class, 'verify'])->name('matching-fee.verify');
        Route::get('/matching-fee/history', [MatchingFeeController::class, 'history'])->name('matching-fee.history');
        Route::post('/matching-fee/refund', [MatchingFeeController::class, 'requestRefund'])->name('matching-fee.refund');

        // Maids Search
        Route::get('/maids', [MaidSearchController::class, 'index'])->name('maids');
        Route::get('/maids/{id}', [MaidSearchController::class, 'show'])->name('maids.show');

        // Bookings
        Route::get('/bookings', [BookingController::class, 'indexEmployer'])->name('bookings');
        Route::post('/bookings', [BookingController::class, 'create'])->name('bookings.create');
        Route::get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{id}/start', [BookingController::class, 'start'])->name('bookings.start');
        Route::post('/bookings/{id}/complete', [BookingController::class, 'complete'])->name('bookings.complete');
        Route::get('/bookings/stats', [BookingController::class, 'stats'])->name('bookings.stats');

        // Payments
        Route::get('/payments', [PaymentController::class, 'indexEmployer'])->name('payments');
        Route::post('/payments/initialize', [PaymentController::class, 'initialize'])->name('payments.initialize');
        Route::get('/payments/verify', [PaymentController::class, 'verify'])->name('payments.verify');

        // Reviews
        Route::get('/reviews', [ReviewController::class, 'indexEmployer'])->name('reviews');
        Route::post('/reviews', [ReviewController::class, 'create'])->name('reviews.create');
        Route::put('/reviews/{id}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    });
});

// Payment Webhooks (No auth required)
Route::post('/webhooks/paystack', [PaymentController::class, 'webhook'])->name('webhooks.paystack');
Route::post('/webhooks/flutterwave', [PaymentController::class, 'webhook'])->name('webhooks.flutterwave');

// Matching Fee Webhooks (No auth required)
Route::post('/webhooks/matching-fee/paystack', [MatchingFeeController::class, 'webhook'])->name('webhooks.matching-fee.paystack');
Route::post('/webhooks/matching-fee/flutterwave', [MatchingFeeController::class, 'webhook'])->name('webhooks.matching-fee.flutterwave');
