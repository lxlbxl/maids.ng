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
Route::get('/about', function () { return Inertia::render('About'); })->name('about');
Route::get('/contact', function () { return Inertia::render('Contact'); })->name('contact');
Route::get('/blog', function () { return Inertia::render('Blog'); })->name('blog');
Route::get('/terms', function () { return Inertia::render('Terms'); })->name('terms');
Route::get('/privacy', function () { return Inertia::render('Privacy'); })->name('privacy');

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
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear');
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');

    // Admin Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // People Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
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

        // Agent Command Center
        Route::get('/agents', function () {
            $stats = ['active_agents' => 0, 'open_conversations' => 0, 'new_leads' => 0, 'messages_today' => 0];
            $conversations = [];
            $leads = [];
            $agentSettings = [];

            try {
                // Load agent-related settings
                $settingKeys = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'like', 'agent_%')
                    ->orWhere('key', 'like', 'channel_%')
                    ->get();
                foreach ($settingKeys as $s) {
                    $agentSettings[$s->key] = $s->value;
                }

                // Conversations
                if (\Illuminate\Support\Facades\Schema::hasTable('agent_conversations')) {
                    $conversations = \Illuminate\Support\Facades\DB::table('agent_conversations')
                        ->join('agent_channel_identities', 'agent_conversations.channel_identity_id', '=', 'agent_channel_identities.id')
                        ->select('agent_conversations.*', 'agent_channel_identities.display_name', 'agent_channel_identities.phone', 'agent_channel_identities.email', 'agent_channel_identities.channel as ch')
                        ->where('agent_conversations.status', 'open')
                        ->orderByDesc('agent_conversations.last_message_at')
                        ->limit(50)->get()
                        ->map(fn($c) => [
                            'id' => $c->id,
                            'channel' => $c->ch,
                            'status' => $c->status,
                            'last_message_at' => $c->last_message_at,
                            'identity' => ['display_name' => $c->display_name, 'phone' => $c->phone, 'channel' => $c->ch],
                        ])->all();
                    $stats['open_conversations'] = count($conversations);
                }

                // Leads
                if (\Illuminate\Support\Facades\Schema::hasTable('agent_leads')) {
                    $leads = \Illuminate\Support\Facades\DB::table('agent_leads')
                        ->join('agent_channel_identities', 'agent_leads.channel_identity_id', '=', 'agent_channel_identities.id')
                        ->select('agent_leads.*', 'agent_channel_identities.display_name', 'agent_channel_identities.phone', 'agent_channel_identities.channel as ch')
                        ->orderByDesc('agent_leads.created_at')
                        ->limit(100)->get()
                        ->map(fn($l) => [
                            'id' => $l->id,
                            'status' => $l->status,
                            'phone' => $l->phone,
                            'email' => $l->email,
                            'created_at' => $l->created_at,
                            'converted_at' => $l->converted_at ?? null,
                            'identity' => ['display_name' => $l->display_name, 'phone' => $l->phone, 'channel' => $l->ch],
                        ])->all();
                    $stats['new_leads'] = \Illuminate\Support\Facades\DB::table('agent_leads')->where('status', 'new')->count();
                }

                // Messages today
                if (\Illuminate\Support\Facades\Schema::hasTable('agent_messages')) {
                    $stats['messages_today'] = \Illuminate\Support\Facades\DB::table('agent_messages')
                        ->where('created_at', '>=', now()->startOfDay())->count();
                }
                $stats['active_agents'] = 7;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Agent page load error: ' . $e->getMessage());
            }

            return Inertia::render('Admin/Agents', compact('stats', 'conversations', 'leads', 'agentSettings'));
        })->name('agents');

        // Toggle agent on/off
        Route::post('/agents/toggle', function (\Illuminate\Http\Request $request) {
            $agent = $request->input('agent');
            $enabled = $request->boolean('enabled');
            \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
                ['key' => "agent_{$agent}_enabled"],
                ['value' => $enabled ? 'true' : 'false', 'group' => 'agents', 'updated_at' => now(), 'created_at' => now()]
            );
            return back()->with('success', "Agent updated.");
        })->name('agents.toggle');

        // Toggle channel on/off
        Route::post('/agents/channels/toggle', function (\Illuminate\Http\Request $request) {
            $channel = $request->input('channel');
            $enabled = $request->boolean('enabled');
            \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
                ['key' => "channel_{$channel}_enabled"],
                ['value' => $enabled ? 'true' : 'false', 'group' => 'agents', 'updated_at' => now(), 'created_at' => now()]
            );
            return back()->with('success', "Channel updated.");
        })->name('agents.channels.toggle');

        // Close conversation
        Route::post('/agents/conversations/{id}/close', function ($id) {
            try {
                \Illuminate\Support\Facades\DB::table('agent_conversations')->where('id', $id)->update(['status' => 'closed', 'updated_at' => now()]);
                return back()->with('success', 'Conversation closed.');
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }
        })->name('agents.conversations.close');

        // AI Matching & Salary Ops — data passed via Inertia props (not API calls)
        Route::get('/matching', function () {
            $jobs = [];
            $stats = [
                'total_jobs' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'requires_review' => 0,
            ];

            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('ai_matching_queue')) {
                    $query = \Illuminate\Support\Facades\DB::table('ai_matching_queue')
                        ->orderByDesc('created_at')
                        ->limit(50);

                    $rawJobs = $query->get();

                    $jobs = $rawJobs->map(function ($job) {
                        $employer = null;
                        if ($job->employer_id) {
                            $employer = \Illuminate\Support\Facades\DB::table('users')
                                ->where('id', $job->employer_id)
                                ->select('id', 'name', 'email')
                                ->first();
                        }
                        return [
                            'job_id' => $job->job_id,
                            'employer' => $employer ? ['name' => $employer->name] : ['name' => 'Unknown'],
                            'status' => $job->status ?? 'pending',
                            'priority' => $job->priority ?? 5,
                            'created_at' => $job->created_at,
                            'completed_at' => $job->completed_at ?? null,
                            'ai_confidence_score' => $job->ai_confidence_score ? (float) $job->ai_confidence_score / 100 : null,
                            'requires_review' => (bool) ($job->requires_review ?? false),
                        ];
                    })->all();

                    $stats = [
                        'total_jobs' => \Illuminate\Support\Facades\DB::table('ai_matching_queue')->count(),
                        'pending' => \Illuminate\Support\Facades\DB::table('ai_matching_queue')->where('status', 'pending')->count(),
                        'processing' => \Illuminate\Support\Facades\DB::table('ai_matching_queue')->where('status', 'processing')->count(),
                        'completed' => \Illuminate\Support\Facades\DB::table('ai_matching_queue')->where('status', 'completed')->count(),
                        'failed' => \Illuminate\Support\Facades\DB::table('ai_matching_queue')->where('status', 'failed')->count(),
                        'requires_review' => \Illuminate\Support\Facades\DB::table('ai_matching_queue')->where('requires_review', true)->count(),
                    ];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Matching queue data fetch failed: ' . $e->getMessage());
            }

            return Inertia::render('Admin/MatchingQueue', [
                'jobs' => $jobs,
                'stats' => $stats,
            ]);
        })->name('matching');

        // Matching Queue Actions
        Route::post('/matching/force-process', function () {
            try {
                $updated = \Illuminate\Support\Facades\DB::table('ai_matching_queue')
                    ->where('status', 'pending')
                    ->update(['status' => 'processing', 'updated_at' => now()]);

                try {
                    \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                        'agent_type' => 'admin_manual',
                        'action' => 'force_process_queue',
                        'description' => "Admin force-processed {$updated} pending matching jobs",
                        'metadata' => json_encode(['admin_id' => auth()->id(), 'jobs_affected' => $updated]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                }

                return back()->with('success', "Force-processing initiated for {$updated} pending jobs.");
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
            }
        })->name('matching.force-process');

        Route::post('/matching/{jobId}/approve', function ($jobId) {
            try {
                \Illuminate\Support\Facades\DB::table('ai_matching_queue')
                    ->where('job_id', $jobId)
                    ->update(['requires_review' => false, 'status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
                return back()->with('success', "Match {$jobId} approved.");
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
            }
        })->name('matching.approve');

        Route::post('/matching/{jobId}/reject', function ($jobId) {
            try {
                \Illuminate\Support\Facades\DB::table('ai_matching_queue')
                    ->where('job_id', $jobId)
                    ->update(['requires_review' => false, 'status' => 'failed', 'updated_at' => now()]);
                return back()->with('success', "Match {$jobId} rejected.");
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
            }
        })->name('matching.reject');

        Route::post('/matching/{jobId}/retry', function ($jobId) {
            try {
                \Illuminate\Support\Facades\DB::table('ai_matching_queue')
                    ->where('job_id', $jobId)
                    ->update(['status' => 'pending', 'requires_review' => false, 'updated_at' => now()]);
                return back()->with('success', "Match {$jobId} re-queued.");
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
            }
        })->name('matching.retry');

        // Review Moderation Actions
        Route::post('/reviews/{id}/flag', function ($id) {
            try {
                \Illuminate\Support\Facades\DB::table('reviews')->where('id', $id)->update([
                    'flagged' => true,
                    'updated_at' => now(),
                ]);
                try {
                    \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                        'agent_type' => 'admin_manual',
                        'action' => 'review_flagged',
                        'description' => "Review #{$id} flagged for moderation",
                        'metadata' => json_encode(['review_id' => $id, 'admin_id' => auth()->id()]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                }
                return back()->with('success', 'Review flagged.');
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
            }
        })->name('reviews.flag');

        Route::delete('/reviews/{id}', function ($id) {
            try {
                \Illuminate\Support\Facades\DB::table('reviews')->where('id', $id)->delete();
                try {
                    \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                        'agent_type' => 'admin_manual',
                        'action' => 'review_deleted',
                        'description' => "Review #{$id} deleted by admin",
                        'metadata' => json_encode(['review_id' => $id, 'admin_id' => auth()->id()]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                }
                return back()->with('success', 'Review deleted.');
            } catch (\Throwable $e) {
                return back()->withErrors(['message' => 'Failed: ' . $e->getMessage()]);
            }
        })->name('reviews.delete');

        // Dispute Refund Action
        Route::post('/disputes/{id}/refund', function ($id) {
            try {
                $dispute = \Illuminate\Support\Facades\DB::table('disputes')->where('id', $id)->first();
                if (!$dispute) {
                    return back()->withErrors(['message' => 'Dispute not found.']);
                }

                // Find the related matching fee payment and attempt refund
                $booking = $dispute->booking_id ? \Illuminate\Support\Facades\DB::table('bookings')->where('id', $dispute->booking_id)->first() : null;
                $employerId = $booking->employer_id ?? $dispute->user_id ?? null;

                if ($employerId) {
                    // Credit employer wallet as refund
                    try {
                        $walletService = app(\App\Services\WalletService::class);
                        $walletService->creditEmployerWallet(
                            $employerId,
                            $booking->agreed_salary ?? 5000,
                            "Refund for dispute #DISP-{$id}",
                            $dispute->booking_id,
                            'dispute_refund'
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Wallet refund failed for dispute: ' . $e->getMessage());
                    }
                }

                // Update dispute status
                \Illuminate\Support\Facades\DB::table('disputes')->where('id', $id)->update([
                    'status' => 'refunded',
                    'updated_at' => now(),
                ]);

                try {
                    \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                        'agent_type' => 'admin_manual',
                        'action' => 'dispute_refund',
                        'description' => "Refund initiated for dispute #{$id}",
                        'metadata' => json_encode(['dispute_id' => $id, 'employer_id' => $employerId, 'admin_id' => auth()->id()]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                }

                return back()->with('success', 'Refund initiated successfully.');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Dispute refund failed: ' . $e->getMessage());
                return back()->withErrors(['message' => 'Refund failed: ' . $e->getMessage()]);
            }
        })->name('disputes.refund');

        Route::get('/salary', function () {
            $schedules = [];
            $stats = [
                'total_schedules' => 0,
                'total_paid' => 0,
                'total_pending' => 0,
                'total_overdue' => 0,
                'total_amount_scheduled' => 0,
                'total_amount_paid' => 0,
                'overdue_amount' => 0,
            ];

            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('salary_schedules')) {
                    $rawSchedules = \Illuminate\Support\Facades\DB::table('salary_schedules')
                        ->orderByDesc('created_at')
                        ->limit(50)
                        ->get();

                    $schedules = $rawSchedules->map(function ($s) {
                        $employer = $s->employer_id ? \Illuminate\Support\Facades\DB::table('users')->where('id', $s->employer_id)->select('name')->first() : null;
                        $maid = $s->maid_id ? \Illuminate\Support\Facades\DB::table('users')->where('id', $s->maid_id)->select('name')->first() : null;

                        $daysOverdue = 0;
                        if ($s->payment_status === 'overdue' && $s->next_salary_due_date) {
                            $daysOverdue = max(0, (int) now()->diffInDays($s->next_salary_due_date));
                        }

                        return [
                            'id' => $s->id,
                            'assignment' => [
                                'employer' => ['name' => $employer->name ?? 'Unknown'],
                                'maid' => ['name' => $maid->name ?? 'Unknown'],
                            ],
                            'amount' => (float) $s->monthly_salary,
                            'due_date' => $s->next_salary_due_date ?? $s->first_salary_date ?? now()->format('Y-m-d'),
                            'status' => $s->payment_status ?? 'pending',
                            'days_overdue' => $daysOverdue,
                        ];
                    })->all();

                    $stats = [
                        'total_schedules' => \Illuminate\Support\Facades\DB::table('salary_schedules')->count(),
                        'total_paid' => \Illuminate\Support\Facades\DB::table('salary_schedules')->where('payment_status', 'paid')->count(),
                        'total_pending' => \Illuminate\Support\Facades\DB::table('salary_schedules')->where('payment_status', 'pending')->count(),
                        'total_overdue' => \Illuminate\Support\Facades\DB::table('salary_schedules')->where('payment_status', 'overdue')->count(),
                        'total_amount_scheduled' => (float) \Illuminate\Support\Facades\DB::table('salary_schedules')->sum('monthly_salary'),
                        'total_amount_paid' => (float) \Illuminate\Support\Facades\DB::table('salary_payments')->where('status', 'completed')->sum('amount'),
                        'overdue_amount' => (float) \Illuminate\Support\Facades\DB::table('salary_schedules')->where('payment_status', 'overdue')->sum('monthly_salary'),
                    ];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Salary data fetch failed: ' . $e->getMessage());
            }

            return Inertia::render('Admin/SalaryManagement', [
                'schedules' => $schedules,
                'stats' => $stats,
            ]);
        })->name('salary');

        // Salary Management Actions (send nudge, process payment, mark paid, export)
        Route::post('/salary/{id}/nudge', function ($id) {
            try {
                $schedule = \Illuminate\Support\Facades\DB::table('salary_schedules')->where('id', $id)->first();
                if (!$schedule) {
                    return back()->withErrors(['message' => 'Salary schedule not found.']);
                }

                $employer = \Illuminate\Support\Facades\DB::table('users')->where('id', $schedule->employer_id)->first();
                if (!$employer) {
                    return back()->withErrors(['message' => 'Employer not found.']);
                }

                $maid = \Illuminate\Support\Facades\DB::table('users')->where('id', $schedule->maid_id)->first();
                $maidName = $maid->name ?? 'your maid';
                $amountFormatted = number_format($schedule->monthly_salary, 2);

                // Create in-app notification for the employer
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'user_id' => $employer->id,
                    'type' => 'salary_reminder',
                    'title' => 'Salary Payment Overdue',
                    'message' => "Your salary payment of ₦{$amountFormatted} for {$maidName} is overdue. Please process payment as soon as possible to maintain your account standing.",
                    'data' => json_encode([
                        'schedule_id' => $schedule->id,
                        'amount' => $schedule->monthly_salary,
                        'maid_name' => $maidName,
                        'nudged_by' => 'admin',
                        'nudged_at' => now()->toIso8601String(),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Attempt SMS notification via service (graceful failure)
                try {
                    $notificationService = app(\App\Services\NotificationService::class);
                    $notificationService->sendSms(
                        (object) $employer,
                        "Hi {$employer->name}, this is a reminder that your salary payment of ₦{$amountFormatted} for {$maidName} is overdue. Please process payment at your earliest convenience. - Maids.ng",
                        ['type' => 'salary_nudge', 'schedule_id' => $schedule->id],
                        'salary_reminder'
                    );
                } catch (\Throwable $smsErr) {
                    \Illuminate\Support\Facades\Log::info('SMS nudge skipped: ' . $smsErr->getMessage());
                }

                // Update the schedule reminder tracking
                \Illuminate\Support\Facades\DB::table('salary_schedules')
                    ->where('id', $id)
                    ->update([
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => \Illuminate\Support\Facades\DB::raw('reminder_count + 1'),
                        'updated_at' => now(),
                    ]);

                // Log admin activity
                try {
                    \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                        'agent_type' => 'admin_manual',
                        'action' => 'salary_nudge_sent',
                        'description' => "Salary nudge sent to {$employer->name} for schedule #{$id}",
                        'metadata' => json_encode(['schedule_id' => $id, 'employer_id' => $employer->id, 'admin_id' => auth()->id()]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Agent activity logging is optional
                }

                return back()->with('success', "Payment nudge sent to {$employer->name} successfully.");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Salary nudge failed: ' . $e->getMessage());
                return back()->withErrors(['message' => 'Failed to send nudge: ' . $e->getMessage()]);
            }
        })->name('salary.nudge');

        Route::post('/salary/{id}/process', function ($id) {
            try {
                $schedule = \Illuminate\Support\Facades\DB::table('salary_schedules')->where('id', $id)->first();
                if (!$schedule) {
                    return back()->withErrors(['message' => 'Salary schedule not found.']);
                }

                if ($schedule->payment_status === 'paid') {
                    return back()->withErrors(['message' => 'This salary has already been paid.']);
                }

                $salaryService = app(\App\Services\SalaryManagementService::class);
                $result = $salaryService->processSalaryPayment(
                    $schedule->assignment_id,
                    (float) $schedule->monthly_salary,
                    'Admin-processed salary payment for schedule #' . $id
                );

                if ($result) {
                    // Update schedule status
                    \Illuminate\Support\Facades\DB::table('salary_schedules')
                        ->where('id', $id)
                        ->update([
                            'payment_status' => 'paid',
                            'updated_at' => now(),
                        ]);

                    return redirect()->route('admin.salary')->with('success', 'Salary payment processed successfully.');
                }

                return back()->withErrors(['message' => 'Failed to process payment. Employer may have insufficient wallet balance.']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Salary process failed: ' . $e->getMessage());
                return back()->withErrors(['message' => 'Payment processing failed: ' . $e->getMessage()]);
            }
        })->name('salary.process');

        Route::post('/salary/{id}/mark-paid', function ($id) {
            try {
                $schedule = \Illuminate\Support\Facades\DB::table('salary_schedules')->where('id', $id)->first();
                if (!$schedule) {
                    return back()->withErrors(['message' => 'Salary schedule not found.']);
                }

                // Update status to paid (manual admin override — no wallet debit)
                \Illuminate\Support\Facades\DB::table('salary_schedules')
                    ->where('id', $id)
                    ->update([
                        'payment_status' => 'paid',
                        'updated_at' => now(),
                    ]);

                // Record as manual payment
                try {
                    \Illuminate\Support\Facades\DB::table('salary_payments')->insert([
                        'assignment_id' => $schedule->assignment_id,
                        'employer_id' => $schedule->employer_id,
                        'maid_id' => $schedule->maid_id,
                        'amount' => $schedule->monthly_salary,
                        'description' => 'Manually marked as paid by admin (ID: ' . auth()->id() . ')',
                        'paid_at' => now(),
                        'status' => 'completed',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Could not record manual salary payment: ' . $e->getMessage());
                }

                // Log admin activity
                try {
                    \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                        'agent_type' => 'admin_manual',
                        'action' => 'salary_marked_paid',
                        'description' => "Salary schedule #{$id} manually marked as paid",
                        'metadata' => json_encode(['schedule_id' => $id, 'admin_id' => auth()->id()]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Optional
                }

                return redirect()->route('admin.salary')->with('success', 'Salary marked as paid.');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Mark paid failed: ' . $e->getMessage());
                return back()->withErrors(['message' => 'Failed to mark as paid: ' . $e->getMessage()]);
            }
        })->name('salary.mark-paid');

        Route::get('/salary/export', function () {
            try {
                $schedules = \Illuminate\Support\Facades\DB::table('salary_schedules')
                    ->orderByDesc('created_at')
                    ->get();

                $csv = "ID,Employer ID,Maid ID,Monthly Salary,Payment Status,Due Date,Reminder Count,Created At\n";
                foreach ($schedules as $s) {
                    $csv .= implode(',', [
                        $s->id,
                        $s->employer_id ?? '',
                        $s->maid_id ?? '',
                        $s->monthly_salary ?? 0,
                        $s->payment_status ?? 'unknown',
                        $s->next_salary_due_date ?? '',
                        $s->reminder_count ?? 0,
                        $s->created_at ?? '',
                    ]) . "\n";
                }

                return response($csv, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="salary_report_' . now()->format('Y-m-d') . '.csv"',
                ]);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
            }
        })->name('salary.export');

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
