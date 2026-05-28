<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AdminVerificationTransactionController;
use App\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
use App\Http\Controllers\Admin\AgentConversationController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminMaidController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminDisputeController;
use App\Http\Controllers\AdminFinancialController;
use App\Http\Controllers\AdminVerificationController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminApiDocsController;
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

// Public static pages
Route::get('/about', function () {
    return Inertia::render('About'); })->name('about');
Route::get('/contact', function () {
    return Inertia::render('Contact'); })->name('contact');
Route::get('/blog', function () {
    return Inertia::render('Blog'); })->name('blog');
Route::get('/terms', function () {
    return Inertia::render('Terms'); })->name('terms');
Route::get('/privacy', function () {
    return Inertia::render('Privacy'); })->name('privacy');

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
Route::get('/standalone-verification/status/{reference}', [App\Http\Controllers\StandaloneVerificationController::class, 'status'])->name('standalone-verification.status');

// Maid Search (Public)
Route::get('/maids', [MaidSearchController::class, 'index'])->name('maids.search');
Route::get('/maids/search', [MaidSearchController::class, 'search'])->name('maids.search.api');
Route::get('/maids/featured', [MaidSearchController::class, 'featured'])->name('maids.featured');
Route::get('/maids/locations', [MaidSearchController::class, 'locations'])->name('maids.locations');
Route::get('/api/unmatched-employer-locations', function () {
    $states = \Illuminate\Support\Facades\Cache::remember('unmatched_employer_locations', 300, function () {
        return \App\Models\EmployerPreference::whereIn('matching_status', ['pending', 'guarantee_search'])
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->pluck('state')
            ->toArray();
    });
    return response()->json($states);
});
Route::get('/maids/{id}', [MaidSearchController::class, 'show'])->name('maids.show');

// Deployment & Maintenance (Token-only auth — works on shared hosting)
// IMPORTANT: Set DEPLOY_SECRET in your .env file, or it uses a default.
// For security, change the default below to your own secret string.
$deploySecret = env('DEPLOY_SECRET');
if (!$deploySecret) {
    try {
        $deploySecret = \App\Models\Setting::get('deploy_secret');
    } catch (\Throwable $e) {
        $deploySecret = null;
    }
}
// Fallback: if no secret is set yet, allow with 'setup-now' as temporary token
if (!$deploySecret) {
    $deploySecret = 'setup-now';
}

Route::get('/deploy-all', function (\Illuminate\Http\Request $request) use ($deploySecret) {
    $secret = $request->query('token');
    if (!$secret || $secret !== $deploySecret) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Provide ?token=YOUR_SECRET',
            'hint' => $deploySecret === 'setup-now' ? 'No DEPLOY_SECRET set. Using temporary token: setup-now' : null,
        ], 403);
    }

    $output = [];

    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrationOutput = trim(\Illuminate\Support\Facades\Artisan::output());
        $output['migrations'] = $migrationOutput ?: 'No pending migrations.';
    } catch (\Exception $e) {
        $output['migrations_error'] = $e->getMessage();
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        $output['caches'] = 'All caches cleared.';
    } catch (\Exception $e) {
        $output['cache_error'] = $e->getMessage();
    }

    return response()->json([
        'success' => true,
        'message' => 'Deployment complete.',
        'details' => $output,
        'timestamp' => now()->toDateTimeString(),
    ]);
});

Route::get('/deploy-fix-db', function (\Illuminate\Http\Request $request) use ($deploySecret) {
    $secret = $request->query('token');
    if (!$secret || $secret !== $deploySecret) {
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
        'message' => empty($added) ? 'Database is already up to date.' : 'Added: ' . implode(', ', $added)
    ]);
});

// Guest Routes
Route::middleware('guest')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Register
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    // Maid Specific Multi-step Registration
    Route::get('/register/maid', [RegisterController::class, 'showMaidRegistrationForm'])->name('register.maid');
    Route::post('/register/maid', [RegisterController::class, 'registerMaid']);

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
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');

    // Admin Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // People Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{id}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::post('/users/{id}/role', [AdminUserController::class, 'assignRole'])->name('users.role');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::get('/maids', [AdminMaidController::class, 'index'])->name('maids');
        Route::get('/staff', [AdminUserController::class, 'staff'])->name('staff');
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
        Route::post('/settings/api-token', [AdminSettingsController::class, 'generateApiToken'])->name('settings.api-token');
        Route::post('/settings/revoke-tokens', [AdminSettingsController::class, 'revokeApiTokens'])->name('settings.revoke-tokens');
        Route::get('/api-docs', [AdminApiDocsController::class, 'index'])->name('api_docs');
        Route::get('/webhooks', [App\Http\Controllers\Admin\AdminWebhookController::class, 'index'])->name('webhooks');

        // Agent Command Center
        Route::get('/agents', [\App\Http\Controllers\Admin\AdminAgentConfigController::class, 'index'])->name('agents');
        Route::post('/agents/toggle', [\App\Http\Controllers\Admin\AdminAgentConfigController::class, 'toggleAgent'])->name('agents.toggle');
        Route::post('/agents/channels/toggle', [\App\Http\Controllers\Admin\AdminAgentConfigController::class, 'toggleChannel'])->name('agents.channels.toggle');
        Route::post('/agents/conversations/{id}/close', [\App\Http\Controllers\Admin\AdminAgentConfigController::class, 'closeConversation'])->name('agents.conversations.close');

        // AI Matching Queue
        Route::get('/matching', [\App\Http\Controllers\Admin\AdminMatchingController::class, 'index'])->name('matching');
        Route::post('/matching/force-process', [\App\Http\Controllers\Admin\AdminMatchingController::class, 'forceProcess'])->name('matching.force-process');
        Route::post('/matching/{jobId}/approve', [\App\Http\Controllers\Admin\AdminMatchingController::class, 'approve'])->name('matching.approve');
        Route::post('/matching/{jobId}/reject', [\App\Http\Controllers\Admin\AdminMatchingController::class, 'reject'])->name('matching.reject');
        Route::post('/matching/{jobId}/retry', [\App\Http\Controllers\Admin\AdminMatchingController::class, 'retry'])->name('matching.retry');

        // Dispute Refund Action
        Route::post('/disputes/{id}/refund', [\App\Http\Controllers\AdminDisputeController::class, 'refund'])->name('disputes.refund');

        // Salary Management
        Route::get('/salary', [\App\Http\Controllers\Admin\AdminSalaryController::class, 'index'])->name('salary');
        Route::post('/salary/{id}/nudge', [\App\Http\Controllers\Admin\AdminSalaryController::class, 'nudge'])->name('salary.nudge');
        Route::post('/salary/{id}/process', [\App\Http\Controllers\Admin\AdminSalaryController::class, 'processPayment'])->name('salary.process');
        Route::post('/salary/{id}/mark-paid', [\App\Http\Controllers\Admin\AdminSalaryController::class, 'markPaid'])->name('salary.mark-paid');
        Route::get('/salary/export', [\App\Http\Controllers\Admin\AdminSalaryController::class, 'export'])->name('salary.export');


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

        Route::get('/escalations', [App\Http\Controllers\Admin\EscalationController::class, 'index'])->name('escalations');
        Route::post('/escalations/{id}/resolve', [App\Http\Controllers\Admin\EscalationController::class, 'resolve'])->name('escalations.resolve');

        // ── Agent Administration ──
        Route::prefix('agent')->name('agent.')->group(function () {
            // Prompt Templates
            Route::get('prompts', [PromptTemplateController::class, 'index'])->name('prompts.index');
            Route::get('prompts/create', [PromptTemplateController::class, 'create'])->name('prompts.create');
            Route::post('prompts', [PromptTemplateController::class, 'store'])->name('prompts.store');
            Route::get('prompts/{template}/edit', [PromptTemplateController::class, 'edit'])->name('prompts.edit');
            Route::put('prompts/{template}', [PromptTemplateController::class, 'update'])->name('prompts.update');
            Route::post('prompts/{template}/rollback', [PromptTemplateController::class, 'rollback'])->name('prompts.rollback');

            // Knowledge Base
            Route::get('knowledge', [KnowledgeBaseController::class, 'index'])->name('knowledge.index');
            Route::get('knowledge/create', [KnowledgeBaseController::class, 'create'])->name('knowledge.create');
            Route::post('knowledge', [KnowledgeBaseController::class, 'store'])->name('knowledge.store');
            Route::get('knowledge/{article}/edit', [KnowledgeBaseController::class, 'edit'])->name('knowledge.edit');
            Route::put('knowledge/{article}', [KnowledgeBaseController::class, 'update'])->name('knowledge.update');
            Route::delete('knowledge/{article}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge.destroy');

            // Conversations Dashboard
            Route::get('conversations', [AgentConversationController::class, 'index'])->name('conversations.index');
            Route::get('conversations/{conversation}', [AgentConversationController::class, 'show'])->name('conversations.show');
            Route::post('conversations/{conversation}/assign', [AgentConversationController::class, 'assign'])->name('conversations.assign');
            Route::post('conversations/{conversation}/escalate', [AgentConversationController::class, 'escalate'])->name('conversations.escalate');
            Route::post('conversations/{conversation}/close', [AgentConversationController::class, 'close'])->name('conversations.close');
            Route::post('conversations/{conversation}/note', [AgentConversationController::class, 'addNote'])->name('conversations.note');
            Route::get('conversations/analytics', [AgentConversationController::class, 'analytics'])->name('conversations.analytics');
        });

        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('/payments/stats', [PaymentController::class, 'stats'])->name('payments.stats');
        Route::post('/notifications/test', [NotificationController::class, 'sendTest'])->name('notifications.test');

        // AI Settings - Fetch models from provider API (same controller as settings page)
        Route::get('/ai/models/{provider}', [AdminSettingsController::class, 'fetchAiModels'])->name('ai.models');

        // ── Connection Test Endpoints ──
        Route::post('/settings/test/qoreid', [AdminSettingsController::class, 'testQoreid'])->name('settings.test.qoreid');
        Route::post('/settings/test/paystack', [AdminSettingsController::class, 'testPaystack'])->name('settings.test.paystack');
        Route::post('/settings/test/flutterwave', [AdminSettingsController::class, 'testFlutterwave'])->name('settings.test.flutterwave');
        Route::post('/settings/test/openai', [AdminSettingsController::class, 'testOpenai'])->name('settings.test.openai');
        Route::post('/settings/test/openrouter', [AdminSettingsController::class, 'testOpenrouter'])->name('settings.test.openrouter');
        Route::post('/settings/test/termii', [AdminSettingsController::class, 'testTermii'])->name('settings.test.termii');
        Route::post('/settings/test/twilio', [AdminSettingsController::class, 'testTwilio'])->name('settings.test.twilio');
        Route::post('/settings/test/africas-talking', [AdminSettingsController::class, 'testAfricasTalking'])->name('settings.test.africas-talking');
        Route::post('/settings/test/email', [AdminSettingsController::class, 'testEmail'])->name('settings.test.email');
        Route::post('/settings/test/meta', [AdminSettingsController::class, 'testMeta'])->name('settings.test.meta');

        // CSV Exports
        Route::get('/export/users', [\App\Http\Controllers\ExportController::class, 'downloadUsers'])->name('export.users');
        Route::get('/export/financials', [\App\Http\Controllers\ExportController::class, 'downloadFinancials'])->name('export.financials');

        // Cache clearing for shared hosting (no CLI access)
        Route::get('/clear-cache', function () {
            $cleared = [];

            // Clear route cache
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            $cleared[] = 'Route cache cleared';

            // Clear config cache
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            $cleared[] = 'Config cache cleared';

            // Clear view cache
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            $cleared[] = 'View cache cleared';

            return response()->json([
                'success' => true,
                'message' => 'Caches cleared successfully',
                'actions' => $cleared
            ]);
        })->name('clear-cache');

        // SEO Admin
        Route::prefix('seo')->name('seo.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'index'])->name('dashboard');
            Route::get('/pages', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'pages'])->name('pages');
            Route::get('/pages/{id}', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'showPage'])->name('page.show');
            Route::post('/pages/{id}/regenerate', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'regenerateContent'])->name('page.regenerate');
            Route::post('/bulk-generate', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'bulkGenerate'])->name('bulk.generate');
            Route::post('/bulk-refresh', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'bulkRefreshContent'])->name('bulk.refresh');
            Route::get('/locations', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'locations'])->name('locations');
            Route::get('/services', [\App\Http\Controllers\Admin\Seo\SeoDashboardController::class, 'services'])->name('services');
        });
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
            return Inertia::render('Employer/OnboardingQuiz', [
                'guaranteeFee' => (int) \App\Models\Setting::get('matching_fee_amount', 5000),
            ]);
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

/*
|--------------------------------------------------------------------------
| Ambassador Agent Chat (Session-based)
|--------------------------------------------------------------------------
*/
Route::post('/ambassador/chat', [\App\Http\Controllers\Api\AmbassadorChatController::class, 'chat'])->name('ambassador.chat');
Route::get('/ambassador/conversation/{id}', [\App\Http\Controllers\Api\AmbassadorChatController::class, 'history'])->name('ambassador.history');

// Setup for shared hosting (runs ALL migrations + seeds)
Route::get('/run-setup', function () use ($deploySecret) {
    $secret = request()->query('token');
    if (!$secret || $secret !== $deploySecret) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Provide ?token=YOUR_SECRET',
            'hint' => $deploySecret === 'setup-now' ? 'No DEPLOY_SECRET set. Using temporary token: setup-now' : null,
        ], 403);
    }

    $output = '';

    try {
        // Run ALL pending migrations
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output .= "=== Migrations ===\n" . trim(\Illuminate\Support\Facades\Artisan::output()) . "\n\n";

        // Seed agent knowledge
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'AgentKnowledgeSeeder', '--force' => true]);
            $output .= trim(\Illuminate\Support\Facades\Artisan::output()) . "\n";
        } catch (\Exception $e) {
            $output .= "Agent Knowledge seed skipped: " . $e->getMessage() . "\n";
        }

        // Seed SEO data
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'SeoLocationSeeder', '--force' => true]);
            $output .= trim(\Illuminate\Support\Facades\Artisan::output()) . "\n";
        } catch (\Exception $e) {
            $output .= "SEO Location seed skipped: " . $e->getMessage() . "\n";
        }
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'SeoServiceSeeder', '--force' => true]);
            $output .= trim(\Illuminate\Support\Facades\Artisan::output()) . "\n";
        } catch (\Exception $e) {
            $output .= "SEO Service seed skipped: " . $e->getMessage() . "\n";
        }

        // Generate SEO page registry
        try {
            \App\Jobs\GenerateSeoPageRegistry::dispatchSync();
            $count = \App\Models\SeoPage::count();
            $output .= "SEO Page Registry: {$count} pages generated.\n";
        } catch (\Exception $e) {
            $output .= "SEO Page Registry skipped: " . $e->getMessage() . "\n";
        }

        // Clear caches
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        $output .= "\nAll caches cleared.";
    } catch (\Exception $e) {
        $output .= "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    }

    return response("<pre>{$output}</pre>");
});

// SEO-only setup endpoint (token-protected)
Route::get('/run-seo-setup', function () use ($deploySecret) {
    $secret = request()->query('token');
    if (!$secret || $secret !== $deploySecret) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Provide ?token=YOUR_SECRET',
            'hint' => $deploySecret === 'setup-now' ? 'No DEPLOY_SECRET set. Using temporary token: setup-now' : null,
        ], 403);
    }

    $output = '';

    try {
        // 1. Run ALL migrations (simpler than individual paths)
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output .= "=== Migrations ===\n" . trim(\Illuminate\Support\Facades\Artisan::output()) . "\n\n";

        // 2. Seed locations
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'SeoLocationSeeder', '--force' => true]);
            $output .= trim(\Illuminate\Support\Facades\Artisan::output()) . "\n";
        } catch (\Exception $e) {
            $output .= "Location seed error: " . $e->getMessage() . "\n";
        }

        // 3. Seed services
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'SeoServiceSeeder', '--force' => true]);
            $output .= trim(\Illuminate\Support\Facades\Artisan::output()) . "\n";
        } catch (\Exception $e) {
            $output .= "Service seed error: " . $e->getMessage() . "\n";
        }

        // 4. Generate page registry
        try {
            \App\Jobs\GenerateSeoPageRegistry::dispatchSync();
            $count = \App\Models\SeoPage::count();
            $output .= "Page Registry: {$count} pages generated.\n";
        } catch (\Exception $e) {
            $output .= "Page registry error: " . $e->getMessage() . "\n";
        }

        // 5. Clear caches
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        $output .= "\nCaches cleared.\n";

    } catch (\Exception $e) {
        $output .= "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    }

    return response("<pre>{$output}</pre>");
});

require __DIR__ . '/seo.php';
require __DIR__ . '/control_room.php';
