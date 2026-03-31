<?php

declare(strict_types=1);

use App\Controllers\Admin\AdminAuthController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminHelperController;
use App\Controllers\Admin\AdminBookingController;
use App\Controllers\Admin\AdminPaymentController;
use App\Controllers\Admin\AdminSettingsController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\AdminVerificationController;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\PermissionMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // Admin auth (no middleware required)
    $app->post('/admin/api/auth/login', [AdminAuthController::class, 'login']);

    // Protected admin routes
    $app->group('/admin/api', function (RouteCollectorProxy $group) {
        // Auth (no permission required, just auth)
        $group->post('/auth/logout', [AdminAuthController::class, 'logout']);
        $group->get('/auth/me', [AdminAuthController::class, 'me']);

        // Dashboard (view only)
        $group->get('/dashboard', [AdminDashboardController::class, 'index'])
            ->add(new PermissionMiddleware('dashboard.view'));

        // Helpers management
        $group->group('/helpers', function (RouteCollectorProxy $helpers) {
            $helpers->get('', [AdminHelperController::class, 'index'])
                ->add(new PermissionMiddleware('helpers.view'));
            $helpers->get('/{id}', [AdminHelperController::class, 'show'])
                ->add(new PermissionMiddleware('helpers.view'));
            $helpers->put('/{id}', [AdminHelperController::class, 'update'])
                ->add(new PermissionMiddleware('helpers.edit'));
            $helpers->delete('/{id}', [AdminHelperController::class, 'delete'])
                ->add(new PermissionMiddleware('helpers.delete'));
            $helpers->put('/{id}/verify', [AdminHelperController::class, 'verify'])
                ->add(new PermissionMiddleware('helpers.verify'));
            $helpers->put('/{id}/badge', [AdminHelperController::class, 'updateBadge'])
                ->add(new PermissionMiddleware('helpers.edit'));
        });

        // Bookings management
        $group->group('/bookings', function (RouteCollectorProxy $bookings) {
            $bookings->get('', [AdminBookingController::class, 'index'])
                ->add(new PermissionMiddleware('bookings.view'));
            $bookings->get('/stats', [AdminBookingController::class, 'getStats'])
                ->add(new PermissionMiddleware('bookings.view'));
            $bookings->get('/{id}', [AdminBookingController::class, 'show'])
                ->add(new PermissionMiddleware('bookings.view'));
            $bookings->put('/{id}', [AdminBookingController::class, 'update'])
                ->add(new PermissionMiddleware('bookings.edit'));
            $bookings->post('/{id}/cancel', [AdminBookingController::class, 'cancel'])
                ->add(new PermissionMiddleware('bookings.cancel'));
        });

        // Payments management
        $group->group('/payments', function (RouteCollectorProxy $payments) {
            $payments->get('', [AdminPaymentController::class, 'index'])
                ->add(new PermissionMiddleware('payments.view'));
            $payments->get('/stats', [AdminPaymentController::class, 'getStats'])
                ->add(new PermissionMiddleware('payments.view'));
            $payments->get('/export', [AdminPaymentController::class, 'export'])
                ->add(new PermissionMiddleware('payments.export'));
            $payments->get('/{id}', [AdminPaymentController::class, 'show'])
                ->add(new PermissionMiddleware('payments.view'));
            $payments->post('/{id}/refund', [AdminPaymentController::class, 'refund'])
                ->add(new PermissionMiddleware('payments.refund'));
        });

        // Verifications management
        $group->group('/verifications', function (RouteCollectorProxy $verifications) {
            $verifications->get('', [AdminVerificationController::class, 'index'])
                ->add(new PermissionMiddleware('verifications.view'));
            $verifications->get('/stats', [AdminVerificationController::class, 'getStats'])
                ->add(new PermissionMiddleware('verifications.view'));
            $verifications->get('/{id}', [AdminVerificationController::class, 'show'])
                ->add(new PermissionMiddleware('verifications.view'));
            $verifications->post('/{id}/approve', [AdminVerificationController::class, 'approve'])
                ->add(new PermissionMiddleware('verifications.approve'));
            $verifications->post('/{id}/reject', [AdminVerificationController::class, 'reject'])
                ->add(new PermissionMiddleware('verifications.reject'));
        });

        // Admin users management (requires user management permissions)
        $group->group('/users', function (RouteCollectorProxy $users) {
            $users->get('', [AdminUserController::class, 'index'])
                ->add(new PermissionMiddleware('users.view'));
            $users->post('', [AdminUserController::class, 'create'])
                ->add(new PermissionMiddleware('users.create'));
            $users->put('/{id}', [AdminUserController::class, 'update'])
                ->add(new PermissionMiddleware('users.edit'));
            $users->delete('/{id}', [AdminUserController::class, 'delete'])
                ->add(new PermissionMiddleware('users.delete'));
        });

        // Roles management (requires role management permissions)
        $group->group('/roles', function (RouteCollectorProxy $roles) {
            $roles->get('', [AdminUserController::class, 'getRoles'])
                ->add(new PermissionMiddleware('roles.view'));
            $roles->post('', [AdminUserController::class, 'createRole'])
                ->add(new PermissionMiddleware('roles.create'));
            $roles->put('/{id}', [AdminUserController::class, 'updateRole'])
                ->add(new PermissionMiddleware('roles.edit'));
            $roles->delete('/{id}', [AdminUserController::class, 'deleteRole'])
                ->add(new PermissionMiddleware('roles.delete'));
        });

        // Agencies management
        $group->group('/agencies', function (RouteCollectorProxy $agencies) {
            $agencies->get('', [\App\Controllers\Admin\AdminAgencyController::class, 'getAllAgencies'])
                ->add(new PermissionMiddleware('users.view'));
            $agencies->get('/{id}', [\App\Controllers\Admin\AdminAgencyController::class, 'getAgencyDetails'])
                ->add(new PermissionMiddleware('users.view'));
            $agencies->put('/{id}/verify', [\App\Controllers\Admin\AdminAgencyController::class, 'verifyAgency'])
                ->add(new PermissionMiddleware('users.edit'));
            $agencies->delete('/{id}', [\App\Controllers\Admin\AdminAgencyController::class, 'deleteAgency'])
                ->add(new PermissionMiddleware('users.delete'));
        });

        // Settings management
        $group->group('/settings', function (RouteCollectorProxy $settings) {
            $settings->get('', [AdminSettingsController::class, 'index'])
                ->add(new PermissionMiddleware('settings.view'));
            $settings->put('', [AdminSettingsController::class, 'update'])
                ->add(new PermissionMiddleware('settings.edit'));
            $settings->put('/{key}', [AdminSettingsController::class, 'updateSingle'])
                ->add(new PermissionMiddleware('settings.edit'));
            $settings->delete('/{key}', [AdminSettingsController::class, 'delete'])
                ->add(new PermissionMiddleware('settings.edit'));
        });

    })->add(new AdminAuthMiddleware());
};
