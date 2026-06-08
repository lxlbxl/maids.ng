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

// New AI-Native System Controllers
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MaidController;
use App\Http\Controllers\Api\EmployerController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AmbassadorChatController;
use App\Http\Controllers\Api\AgentChannelWebhookController;
use App\Http\Controllers\Api\UserEventController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\CliAgentController;

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
    Route::get('/maids', [MaidController::class, 'listAvailable']);
    // Removed unimplemented /search, /top-rated, /verified. Use query params on /maids instead (e.g. ?location=... &verified_only=true)
    Route::get('/maids/{id}', [MaidController::class, 'publicProfile']);

    // Reference Data
    Route::get('/reference/skills', [MaidController::class, 'getSkills']);
    Route::get('/reference/help-types', [MaidController::class, 'getHelpTypes']);
    Route::get('/reference/payment-methods', [PaymentController::class, 'getPaymentMethods']);

    // Public Matching API
    Route::post('/matching/find', [\App\Http\Controllers\MatchingController::class, 'findMatches']);

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
| Dedicated MCP Agent Routes (Option B)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/mcp')->middleware(['mcp.auth'])->group(function () {
    // Maid Management
    Route::get('/maids/{maid_id}', [\App\Http\Controllers\Api\McpAgentController::class, 'getMaidProfile']);
    Route::patch('/maids/{maid_id}/availability', [\App\Http\Controllers\Api\McpAgentController::class, 'updateMaidAvailability']);
    Route::get('/maids/{maid_id}/earnings', [\App\Http\Controllers\Api\McpAgentController::class, 'getMaidEarnings']);

    // Employer Management
    Route::get('/employers/{employer_id}/preferences', [\App\Http\Controllers\Api\McpAgentController::class, 'getEmployerPreferences']);
    Route::patch('/employers/{employer_id}/preferences', [\App\Http\Controllers\Api\McpAgentController::class, 'updateEmployerPreferences']);

    // Booking & Assignment
    Route::post('/bookings/create', [\App\Http\Controllers\Api\McpAgentController::class, 'createBooking']);
    Route::post('/bookings/{booking_id}/cancel', [\App\Http\Controllers\Api\McpAgentController::class, 'cancelBooking']);
    Route::get('/bookings', [\App\Http\Controllers\Api\McpAgentController::class, 'getUserBookings']);
    Route::post('/matching/trigger', [\App\Http\Controllers\Api\McpAgentController::class, 'triggerAiMatching']);

    // Support
    Route::post('/reviews', [\App\Http\Controllers\Api\McpAgentController::class, 'createReview']);
    Route::post('/disputes', [\App\Http\Controllers\Api\McpAgentController::class, 'fileDispute']);
});

/*
|--------------------------------------------------------------------------
| Dedicated CLI Agent Routes (with Audit Logging)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/cli')->middleware(['mcp.auth'])->group(function () {
    // System Status & Health
    Route::get('/status', [CliAgentController::class, 'status']);
    Route::get('/health', [CliAgentController::class, 'health']);

    // Maid Management
    Route::get('/maids', [CliAgentController::class, 'listMaids']);
    Route::get('/maids/{maid_id}', [CliAgentController::class, 'getMaidProfile']);
    Route::patch('/maids/{maid_id}/availability', [CliAgentController::class, 'updateMaidAvailability']);
    Route::get('/maids/{maid_id}/earnings', [CliAgentController::class, 'getMaidEarnings']);

    // Employer Management
    Route::get('/employers/{employer_id}/preferences', [CliAgentController::class, 'getEmployerPreferences']);
    Route::patch('/employers/{employer_id}/preferences', [CliAgentController::class, 'updateEmployerPreferences']);

    // Booking Management
    Route::get('/bookings', [CliAgentController::class, 'listBookings']);
    Route::get('/bookings/user', [CliAgentController::class, 'getUserBookings']);
    Route::post('/bookings/create', [CliAgentController::class, 'createBooking']);
    Route::post('/bookings/{booking_id}/cancel', [CliAgentController::class, 'cancelBooking']);

    // Assignment Management
    Route::get('/assignments', [CliAgentController::class, 'listAssignments']);
    Route::get('/assignments/{id}', [CliAgentController::class, 'getAssignment']);
    Route::post('/assignments/{id}/accept', [CliAgentController::class, 'acceptAssignment']);
    Route::post('/assignments/{id}/reject', [CliAgentController::class, 'rejectAssignment']);
    Route::post('/assignments/{id}/complete', [CliAgentController::class, 'completeAssignment']);
    Route::get('/assignments/statistics', [CliAgentController::class, 'getAssignmentStatistics']);

    // Wallet & Payments
    Route::get('/wallet', [CliAgentController::class, 'getWallet']);
    Route::get('/wallet/transactions', [CliAgentController::class, 'getWalletTransactions']);

    // Notifications
    Route::get('/notifications', [CliAgentController::class, 'listNotifications']);
    Route::get('/notifications/unread-count', [CliAgentController::class, 'getUnreadCount']);
    Route::post('/notifications/{id}/read', [CliAgentController::class, 'markNotificationAsRead']);
    Route::post('/notifications/mark-all-read', [CliAgentController::class, 'markAllNotificationsAsRead']);
    Route::delete('/notifications/{id}', [CliAgentController::class, 'deleteNotification']);

    // User Management
    Route::get('/users', [CliAgentController::class, 'listUsers']);
    Route::get('/users/{id}', [CliAgentController::class, 'getUser']);
    Route::put('/users/{id}/status', [CliAgentController::class, 'updateUserStatus']);

    // Reference Data
    Route::get('/reference/skills', [CliAgentController::class, 'getSkills']);
    Route::get('/reference/help-types', [CliAgentController::class, 'getHelpTypes']);

    // Matching Management
    Route::post('/matching/request', [CliAgentController::class, 'requestMatch']);
    Route::get('/matching/status/{jobId}', [CliAgentController::class, 'matchingStatus']);
    Route::get('/matching/results/{jobId}', [CliAgentController::class, 'matchingResults']);
    Route::post('/matching/manual-assign', [CliAgentController::class, 'manualAssign']);
    Route::get('/matching/queue', [CliAgentController::class, 'matchingQueue']);
});

    }); // closes auth:sanctum

}); // closes v1 prefix

/*
|--------------------------------------------------------------------------
| Agent API Routes — External Agent Operations
|--------------------------------------------------------------------------
|
| Endpoints for external agents (OpenClaw, n8n, Hermes, Claude Code).
| Auth via Bearer mng_sk_{key} stored in agent_api_keys table.
|
*/
Route::prefix('agent-api/v1')->middleware(['agent.auth'])->group(function () {

    Route::post('/users/lookup', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'lookup']);
    Route::post('/users', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'store']);
    Route::get('/users/{id}/summary', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'summary']);
    Route::patch('/users/{id}', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'update']);
    Route::get('/users/{id}/conversation-history', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'conversationHistory']);
    Route::get('/users/scan/inactive', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'scanInactive']);
    Route::get('/users/scan/incomplete-maids', [\App\Http\Controllers\Api\AgentApi\UserController::class, 'scanIncompleteMaids']);

    Route::post('/notes', [\App\Http\Controllers\Api\AgentApi\NoteController::class, 'store']);
    Route::get('/notes/{entityType}/{entityId}', [\App\Http\Controllers\Api\AgentApi\NoteController::class, 'index']);

    Route::post('/messages/send', [\App\Http\Controllers\Api\AgentApi\MessageController::class, 'send']);
    Route::post('/messages/sms', [\App\Http\Controllers\Api\AgentApi\MessageController::class, 'sms']);
    Route::post('/messages/call', [\App\Http\Controllers\Api\AgentApi\MessageController::class, 'call']);
    Route::post('/messages/ambassador', [\App\Http\Controllers\Api\AgentApi\ConversationController::class, 'ambassadorMessage']);

    Route::post('/conversations/message', [\App\Http\Controllers\Api\AgentApi\ConversationController::class, 'message']);
    Route::get('/conversations', [\App\Http\Controllers\Api\AgentApi\ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [\App\Http\Controllers\Api\AgentApi\ConversationController::class, 'show']);
    Route::get('/conversations/{id}/messages', [\App\Http\Controllers\Api\AgentApi\ConversationController::class, 'messages']);
    Route::get('/agent/identity/lookup', [\App\Http\Controllers\Api\AgentApi\ConversationController::class, 'identityLookup']);

    Route::post('/campaigns/send-direct', [\App\Http\Controllers\Api\AgentApi\CampaignController::class, 'sendDirect']);
    Route::get('/campaigns/check-cooldown/{channelIdentityId}', [\App\Http\Controllers\Api\AgentApi\CampaignController::class, 'checkCooldown']);

    // ── Metrics ──
    Route::get('/metrics/platform', [\App\Http\Controllers\Api\AgentApi\MetricsController::class, 'platform']);
    Route::get('/metrics/agent-health', [\App\Http\Controllers\Api\AgentApi\MetricsController::class, 'agentHealth']);
    Route::get('/metrics/revenue', [\App\Http\Controllers\Api\AgentApi\MetricsController::class, 'revenue']);
    Route::get('/metrics/funnel', [\App\Http\Controllers\Api\AgentApi\MetricsController::class, 'funnel']);

    // ── Onboarding ──
    Route::get('/onboarding/{userId}', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'show']);
    Route::get('/onboarding/scan/needs-welcome-call', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'scanNeedsWelcomeCall']);
    Route::get('/onboarding/scan/quiz-abandoned', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'scanQuizAbandoned']);
    Route::get('/onboarding/scan/awaiting-payment', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'scanAwaitingPayment']);
    Route::get('/onboarding/scan/maid-profile-incomplete', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'scanMaidProfileIncomplete']);
    Route::get('/onboarding/scan/nin-pending', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'scanNinPending']);
    Route::get('/onboarding/scan/abandoned', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'scanAbandoned']);
    Route::get('/onboarding/touchpoints/{journeyId}', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'touchpoints']);
    Route::post('/onboarding/touchpoints', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'createTouchpoint']);
    Route::patch('/onboarding/{userId}/milestone', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'milestone']);
    Route::patch('/onboarding/{journeyId}/status', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'updateStatus']);
    Route::post('/onboarding/{journeyId}/note', [\App\Http\Controllers\Api\AgentApi\OnboardingController::class, 'addNote']);

    // ── Fulfillment ──
    Route::post('/fulfillment/open', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'open']);
    Route::get('/fulfillment/{id}', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'show']);
    Route::get('/fulfillment/by-employer/{userId}', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'byEmployer']);
    Route::get('/fulfillment/scan/all-active', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'scanAllActive']);
    Route::get('/fulfillment/scan/stalled', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'scanStalled']);
    Route::get('/fulfillment/scan/awaiting-first-day', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'scanAwaitingFirstDay']);
    Route::get('/fulfillment/scan/day-one-not-confirmed', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'scanDayOneNotConfirmed']);
    Route::get('/fulfillment/scan/ready-to-activate', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'scanReadyToActivate']);
    Route::patch('/fulfillment/{id}/stage', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'updateStage']);
    Route::post('/fulfillment/{id}/record-salary', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'recordSalary']);
    Route::post('/fulfillment/{id}/set-start-date', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'setStartDate']);
    Route::post('/fulfillment/{id}/confirm-arrival', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'confirmArrival']);
    Route::post('/fulfillment/{id}/note', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'addNote']);
    Route::post('/fulfillment/{id}/activate', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'activate']);
    Route::post('/fulfillment/{id}/fail', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'fail']);
    Route::get('/fulfillment/events/{id}', [\App\Http\Controllers\Api\AgentApi\FulfillmentController::class, 'events']);

    // ── Sales ──
    Route::get('/sales/pipeline/{userId}', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'pipeline']);
    Route::get('/sales/scan/hot-leads', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'scanHotLeads']);
    Route::get('/sales/scan/warm-leads', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'scanWarmLeads']);
    Route::get('/sales/scan/payment-pending', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'scanPaymentPending']);
    Route::get('/sales/scan/winback-recent', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'scanWinbackRecent']);
    Route::get('/sales/scan/winback-lapsed', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'scanWinbackLapsed']);
    Route::get('/sales/scan/upsell-candidates', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'scanUpsellCandidates']);
    Route::patch('/sales/pipeline/{userId}', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'updatePipeline']);
    Route::post('/sales/pipeline/{userId}/event', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'logEvent']);
    Route::post('/sales/pipeline/{userId}/action-taken', [\App\Http\Controllers\Api\AgentApi\SalesController::class, 'actionTaken']);

    // ── Customer Success ──
    Route::get('/cs/cases/{id}', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'show']);
    Route::get('/cs/cases/by-employer/{userId}', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'byEmployer']);
    Route::get('/cs/cases/scan/at-risk', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'scanAtRisk']);
    Route::get('/cs/cases/scan/appraisal-due', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'scanAppraisalDue']);
    Route::get('/cs/cases/scan/no-contact-30d', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'scanNoContact30d']);
    Route::patch('/cs/cases/{id}/health', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'updateHealth']);
    Route::post('/cs/cases/{id}/appraisal', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'appraisal']);
    Route::post('/cs/cases/{id}/note', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'addNote']);
    Route::get('/cs/tickets', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'listTickets']);
    Route::get('/cs/tickets/{id}', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'showTicket']);
    Route::get('/cs/tickets/scan/sla-breached', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'scanSlaBreached']);
    Route::get('/cs/tickets/scan/critical-open', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'scanCriticalOpen']);
    Route::post('/cs/tickets', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'createTicket']);
    Route::patch('/cs/tickets/{id}', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'updateTicket']);
    Route::post('/cs/tickets/{id}/resolve', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'resolveTicket']);
    Route::post('/cs/tickets/{id}/escalate', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'escalateTicket']);
    Route::get('/cs/exits/scan/recent', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'scanExitsRecent']);
    Route::post('/cs/exits', [\App\Http\Controllers\Api\AgentApi\CsController::class, 'createExit']);

    // ── Payments ──
    Route::get('/payments/status/{userId}', [\App\Http\Controllers\Api\AgentApi\AgentPaymentsController::class, 'status']);
    Route::get('/payments/generate-link', [\App\Http\Controllers\Api\AgentApi\AgentPaymentsController::class, 'generateLink']);
    Route::get('/payments/scan/pending-72h', [\App\Http\Controllers\Api\AgentApi\AgentPaymentsController::class, 'scanPending72h']);
    Route::get('/wallets/scan/salary-delayed', [\App\Http\Controllers\Api\AgentApi\AgentPaymentsController::class, 'scanSalaryDelayed']);
    Route::post('/wallets/release-escrow', [\App\Http\Controllers\Api\AgentApi\AgentPaymentsController::class, 'releaseEscrow']);

    // ── Matching & Assignments ──
    Route::post('/matching/run', [\App\Http\Controllers\Api\AgentApi\AgentMatchingController::class, 'run']);
    Route::post('/matching/assign', [\App\Http\Controllers\Api\AgentApi\AgentMatchingController::class, 'assign']);
    Route::get('/assignments/{id}', [\App\Http\Controllers\Api\AgentApi\AgentMatchingController::class, 'showAssignment']);
    Route::patch('/assignments/{id}/status', [\App\Http\Controllers\Api\AgentApi\AgentMatchingController::class, 'updateAssignmentStatus']);
    Route::get('/assignments/scan/no-start-date', [\App\Http\Controllers\Api\AgentApi\AgentMatchingController::class, 'scanNoStartDate']);
    Route::get('/assignments/scan/expiring-soon', [\App\Http\Controllers\Api\AgentApi\AgentMatchingController::class, 'scanExpiringSoon']);

    // ── Communications ──
    Route::get('/communications/thread/{phone}', [\App\Http\Controllers\Api\AgentApi\CommsController::class, 'threadByPhone']);
    Route::get('/communications/thread/by-user/{userId}', [\App\Http\Controllers\Api\AgentApi\CommsController::class, 'threadByUser']);
    Route::post('/communications/event', [\App\Http\Controllers\Api\AgentApi\CommsController::class, 'logEvent']);
    Route::get('/calls/logs', [\App\Http\Controllers\Api\AgentApi\CommsController::class, 'callLogs']);
    Route::get('/calls/logs/{id}', [\App\Http\Controllers\Api\AgentApi\CommsController::class, 'showCallLog']);
    Route::patch('/calls/logs/{id}/outcome', [\App\Http\Controllers\Api\AgentApi\CommsController::class, 'updateCallOutcome']);

    // ── Campaigns ──
    Route::get('/campaigns', [\App\Http\Controllers\Api\AgentApi\CampaignsController::class, 'index']);
    Route::get('/campaigns/{id}/logs', [\App\Http\Controllers\Api\AgentApi\CampaignsController::class, 'logs']);
    Route::post('/campaigns/{id}/dispatch', [\App\Http\Controllers\Api\AgentApi\CampaignsController::class, 'dispatch']);
});
