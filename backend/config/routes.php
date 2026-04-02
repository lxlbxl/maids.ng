<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HelperController;
use App\Controllers\BookingController;
use App\Controllers\PaymentController;
use App\Controllers\DashboardController;
use App\Controllers\ConfigController;
use App\Controllers\LeadController;
use App\Controllers\RatingController;
use App\Controllers\VerificationController;
use App\Controllers\OtpController;
use App\Controllers\ClientRequestController;
use App\Middleware\AuthMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\WebhookVerificationMiddleware;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $container = $app->getContainer();

    // Health check
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'timestamp' => date('c')
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Auth routes
    $app->group('/api/auth', function (RouteCollectorProxy $group) use ($container) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/admin-login', [AuthController::class, 'adminLogin']);
        $group->post('/register', [AuthController::class, 'register']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->post('/refresh', [AuthController::class, 'refreshToken']);
        $group->get('/me', [AuthController::class, 'me'])->add($container->get(JwtAuthMiddleware::class));
    });

    // Legacy login endpoint (for backward compatibility with frontend)
    $app->post('/api/maid-login', [AuthController::class, 'login']);

    // Helper routes
    $app->group('/api/helpers', function (RouteCollectorProxy $group) use ($container) {
        $group->get('/match', [HelperController::class, 'match']);
        $group->get('/{id}', [HelperController::class, 'show']);
        $group->post('', [HelperController::class, 'create']);
        $group->put('/{id}', [HelperController::class, 'update'])->add($container->get(JwtAuthMiddleware::class));
        $group->put('/{id}/availability', [HelperController::class, 'updateAvailability'])->add($container->get(JwtAuthMiddleware::class));
        $group->get('/{id}/availability', [HelperController::class, 'getAvailability']);
        $group->post('/{id}/bank-details', [HelperController::class, 'saveBankDetails'])->add($container->get(JwtAuthMiddleware::class));
    });

    // Legacy endpoints (for backward compatibility with frontend)
    $app->get('/api/maid-get', [HelperController::class, 'match']);
    $app->post('/api/maid-post', [HelperController::class, 'create']);

    // Booking routes (protected)
    $app->group('/api/bookings', function (RouteCollectorProxy $group) {
        $group->post('', [BookingController::class, 'create']);
        $group->get('', [BookingController::class, 'getUserBookings']);
        $group->get('/{id}', [BookingController::class, 'show']);
        $group->post('/{id}/cancel', [BookingController::class, 'cancel']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Payment routes
    $app->group('/api/payments', function (RouteCollectorProxy $group) {
        $group->get('/config', [PaymentController::class, 'getConfig']);
        $group->post('/initialize', [PaymentController::class, 'initialize']);
        $group->post('/verify', [PaymentController::class, 'verifyCallback']);
        $group->post('/success', [PaymentController::class, 'success']);
        $group->post('/failure', [PaymentController::class, 'failure']);
        $group->get('/banks', [PaymentController::class, 'getBanks']);
        $group->post('/resolve-account', [PaymentController::class, 'resolveAccount']);
    });

    // Legacy payment endpoints
    $app->get('/api/maid-get-keys', [PaymentController::class, 'getConfig']);
    $app->post('/api/maid-payment-success', [PaymentController::class, 'success']);
    $app->post('/api/maid-payment-failure', [PaymentController::class, 'failure']);

    // Dashboard routes
    $app->get('/api/dashboard', [DashboardController::class, 'index'])->add($container->get(JwtAuthMiddleware::class));
    $app->get('/api/get-dash-data', [DashboardController::class, 'index'])->add($container->get(JwtAuthMiddleware::class)); // Legacy

    // Config routes
    $app->group('/api/config', function (RouteCollectorProxy $group) {
        $group->get('/site', [ConfigController::class, 'getSiteConfig']);
        $group->get('/contact', [ConfigController::class, 'getContactInfo']);
        $group->get('/fees', [ConfigController::class, 'getServiceFees']);
        $group->get('/locations', [ConfigController::class, 'getLocations']);
        $group->get('/work-types', [ConfigController::class, 'getWorkTypes']);
        $group->get('/skills', [ConfigController::class, 'getSkills']);
        $group->get('/payment', [ConfigController::class, 'getPaymentConfig']);
    });

    // Legacy config endpoints
    $app->get('/api/site-config', [ConfigController::class, 'getSiteConfig']);
    $app->get('/api/contact-info', [ConfigController::class, 'getContactInfo']);

    // Lead capture
    $app->post('/api/leads', [LeadController::class, 'capture']);
    $app->post('/api/maid-lead', [LeadController::class, 'capture']); // Legacy
    $app->post('/api/leads/{id}/convert', [LeadController::class, 'convert']);

    // Client Maid Requests (public – no auth required)
    $app->post('/api/client-requests', [ClientRequestController::class, 'submit']);

    // Ratings
    $app->post('/api/ratings', [RatingController::class, 'create'])->add($container->get(JwtAuthMiddleware::class));
    $app->post('/api/maid-ratings', [RatingController::class, 'create'])->add($container->get(JwtAuthMiddleware::class)); // Legacy
    $app->get('/api/helpers/{id}/ratings', [RatingController::class, 'getHelperRatings']);

    // Verification
    $app->post('/api/verification/nin', [VerificationController::class, 'submitNin']);
    $app->post('/api/nin-verification', [VerificationController::class, 'submitNin']); // Legacy
    $app->get('/api/verification/{id}', [VerificationController::class, 'getStatus']);

    // OTP Routes
    $app->group('/api/otp', function (RouteCollectorProxy $group) {
        $group->post('/send-phone', [OtpController::class, 'sendToPhone']);
        $group->post('/send-email', [OtpController::class, 'sendToEmail']);
        $group->post('/verify', [OtpController::class, 'verify']);
        $group->post('/request-pin-reset', [OtpController::class, 'requestPinReset']);
        $group->post('/reset-pin', [OtpController::class, 'resetPin']);
    });

    // Payment Webhooks (with signature verification)
    $logger = $container->get(LoggerInterface::class);
    $webhookMiddleware = new WebhookVerificationMiddleware($logger);

    $app->group('/api/webhooks', function (RouteCollectorProxy $group) {
        $group->post('/flutterwave', [PaymentController::class, 'flutterwaveWebhook']);
        $group->post('/paystack', [PaymentController::class, 'paystackWebhook']);
    })->add($webhookMiddleware);

    // Handle OPTIONS requests for CORS
    $app->options('/{routes:.+}', function ($request, $response) {
        return $response;
    });

    // Agency routes (Protected by JwtAuthMiddleware)
    // We reuse the user auth since Agencies are just Users with user_type='agency'
    $app->group('/api/agency', function (RouteCollectorProxy $group) {
        $group->get('/dashboard', [\App\Controllers\AgencyController::class, 'index']);
        $group->get('/maids', [\App\Controllers\AgencyController::class, 'getMaids']);
        $group->post('/maids', [\App\Controllers\AgencyController::class, 'addMaid']);
        $group->put('/maids/{id}', [\App\Controllers\AgencyController::class, 'updateMaid']);
        $group->delete('/maids/{id}', [\App\Controllers\AgencyController::class, 'deleteMaid']);

        $group->get('/profile', [\App\Controllers\AgencyController::class, 'getProfile']);
        $group->put('/profile', [\App\Controllers\AgencyController::class, 'updateProfile']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Employer Routes
    $app->group('/api/employer', function (RouteCollectorProxy $group) {
        $group->get('/profile', [\App\Controllers\EmployerController::class, 'getProfile']);
        $group->put('/profile', [\App\Controllers\EmployerController::class, 'updateProfile']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Disputes Routes
    $app->group('/api/disputes', function (RouteCollectorProxy $group) {
        $group->post('', [\App\Controllers\DisputeController::class, 'create']);
        $group->get('', [\App\Controllers\DisputeController::class, 'index']);
        $group->get('/{id}', [\App\Controllers\DisputeController::class, 'show']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Messaging Routes
    $app->group('/api/messages', function (RouteCollectorProxy $group) {
        $group->post('', [\App\Controllers\MessageController::class, 'sendMessage']);
        $group->get('/booking/{id}', [\App\Controllers\MessageController::class, 'getMessages']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Notifications (for user to list their notifications)
    $app->group('/api/notifications', function (RouteCollectorProxy $group) {
        $group->get('', [\App\Controllers\NotificationController::class, 'index']);
        $group->post('/{id}/read', [\App\Controllers\NotificationController::class, 'markRead']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Helper Actions (accept/decline booking requests)
    $app->group('/api/me', function (RouteCollectorProxy $group) {
        $group->get('/requests', [\App\Controllers\BookingController::class, 'getIncomingRequests']);
        $group->post('/bookings/{id}/accept', [\App\Controllers\BookingController::class, 'accept']);
        $group->post('/bookings/{id}/decline', [\App\Controllers\BookingController::class, 'decline']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Agency Hire Request Approval (approve/reject employer booking)
    $app->group('/api/agency/bookings', function (RouteCollectorProxy $group) {
        $group->get('/pending', [\App\Controllers\AgencyController::class, 'getPendingBookings']);
        $group->post('/{id}/approve', [\App\Controllers\AgencyController::class, 'approveBooking']);
        $group->post('/{id}/reject', [\App\Controllers\AgencyController::class, 'rejectBooking']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Admin Hire Requests Management
    $app->group('/api/admin/bookings', function (RouteCollectorProxy $group) {
        $group->get('', [\App\Controllers\Admin\AdminBookingController::class, 'index']);
        $group->get('/{id}', [\App\Controllers\Admin\AdminBookingController::class, 'show']);
        $group->put('/{id}', [\App\Controllers\Admin\AdminBookingController::class, 'update']);
        $group->post('/{id}/cancel', [\App\Controllers\Admin\AdminBookingController::class, 'cancel']);
        $group->post('/{id}/assign', [\App\Controllers\Admin\AdminBookingController::class, 'assignHelper']);
    })->add($container->get(JwtAuthMiddleware::class));

    // Admin Helper Bulk Upload
    $app->group('/api/admin/helpers', function (RouteCollectorProxy $group) {
        $group->get('', [\App\Controllers\Admin\AdminHelperController::class, 'index']);
        $group->get('/{id}', [\App\Controllers\Admin\AdminHelperController::class, 'show']);
        $group->put('/{id}', [\App\Controllers\Admin\AdminHelperController::class, 'update']);
        $group->delete('/{id}', [\App\Controllers\Admin\AdminHelperController::class, 'delete']);
        $group->post('/{id}/verify', [\App\Controllers\Admin\AdminHelperController::class, 'verify']);
        $group->post('/bulk', [\App\Controllers\Admin\AdminHelperController::class, 'bulkUpload']);
    })->add($container->get(JwtAuthMiddleware::class));
};

