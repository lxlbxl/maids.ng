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
    return Inertia::render('Employer/OnboardingQuiz');
})->name('onboarding');

// Standalone Verification Service (Public - no account required)
Route::get('/verify-service', function () {
    return Inertia::render('VerificationService');
})->name('verify-service');

// Maid Search (Public)
Route::get('/maids', [MaidSearchController::class, 'index'])->name('maids.search');
Route::get('/maids/search', [MaidSearchController::class, 'search'])->name('maids.search.api');
Route::get('/maids/featured', [MaidSearchController::class, 'featured'])->name('maids.featured');
Route::get('/maids/locations', [MaidSearchController::class, 'locations'])->name('maids.locations');
Route::get('/maids/{id}', [MaidSearchController::class, 'show'])->name('maids.show');

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

        Route::get('/escalations', [App\Http\Controllers\Admin\EscalationController::class, 'index'])->name('escalations');
        Route::post('/escalations/{id}/resolve', [App\Http\Controllers\Admin\EscalationController::class, 'resolve'])->name('escalations.resolve');

        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('/payments/stats', [PaymentController::class, 'stats'])->name('payments.stats');
        Route::post('/notifications/test', [NotificationController::class, 'sendTest'])->name('notifications.test');

        // CSV Exports
        Route::get('/export/users', [\App\Http\Controllers\ExportController::class, 'downloadUsers'])->name('export.users');
        Route::get('/export/financials', [\App\Http\Controllers\ExportController::class, 'downloadFinancials'])->name('export.financials');
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
