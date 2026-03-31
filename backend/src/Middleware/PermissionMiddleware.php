<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Permission Middleware
 * Enforces admin permissions on routes
 *
 * Available permissions:
 * - helpers.view, helpers.create, helpers.edit, helpers.delete, helpers.verify
 * - bookings.view, bookings.create, bookings.edit, bookings.cancel
 * - payments.view, payments.refund, payments.export
 * - verifications.view, verifications.approve, verifications.reject
 * - users.view, users.create, users.edit, users.delete
 * - roles.view, roles.create, roles.edit, roles.delete
 * - settings.view, settings.edit
 * - leads.view, leads.convert, leads.delete
 * - reports.view, reports.export
 * - dashboard.view
 *
 * Use '*' for super admin (all permissions)
 */
class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * @var string|array Permission(s) required for this route
     */
    private $requiredPermissions;

    /**
     * @var bool If true, user must have ALL permissions. If false, ANY permission is sufficient.
     */
    private bool $requireAll;

    /**
     * @param string|array $permissions Required permission(s)
     * @param bool $requireAll Whether all permissions are required (default: false = any)
     */
    public function __construct($permissions, bool $requireAll = false)
    {
        $this->requiredPermissions = is_array($permissions) ? $permissions : [$permissions];
        $this->requireAll = $requireAll;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get admin permissions from request attributes (set by AdminAuthMiddleware)
        $adminPermissions = $request->getAttribute('admin_permissions', []);
        $adminRoleId = $request->getAttribute('admin_role_id');

        // Super admin (role_id = 1) has all permissions
        if ($adminRoleId === 1) {
            return $handler->handle($request);
        }

        // Check if admin has wildcard permission
        if (in_array('*', $adminPermissions, true)) {
            return $handler->handle($request);
        }

        // Check required permissions
        $hasPermission = $this->checkPermissions($adminPermissions);

        if (!$hasPermission) {
            return $this->forbiddenResponse();
        }

        return $handler->handle($request);
    }

    /**
     * Check if user has required permissions
     */
    private function checkPermissions(array $userPermissions): bool
    {
        if (empty($this->requiredPermissions)) {
            return true;
        }

        $matchedCount = 0;

        foreach ($this->requiredPermissions as $required) {
            // Direct match
            if (in_array($required, $userPermissions, true)) {
                $matchedCount++;
                continue;
            }

            // Check for category wildcard (e.g., "helpers.*")
            $category = explode('.', $required)[0] ?? '';
            if (in_array($category . '.*', $userPermissions, true)) {
                $matchedCount++;
                continue;
            }
        }

        if ($this->requireAll) {
            return $matchedCount === count($this->requiredPermissions);
        }

        return $matchedCount > 0;
    }

    /**
     * Return forbidden response
     */
    private function forbiddenResponse(): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => 'You do not have permission to perform this action',
            'required_permissions' => $this->requiredPermissions
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }

    /**
     * Static factory methods for common permission checks
     */

    public static function viewHelpers(): self
    {
        return new self('helpers.view');
    }

    public static function editHelpers(): self
    {
        return new self(['helpers.edit', 'helpers.create'], false);
    }

    public static function verifyHelpers(): self
    {
        return new self('helpers.verify');
    }

    public static function viewBookings(): self
    {
        return new self('bookings.view');
    }

    public static function editBookings(): self
    {
        return new self(['bookings.edit', 'bookings.cancel'], false);
    }

    public static function viewPayments(): self
    {
        return new self('payments.view');
    }

    public static function managePayments(): self
    {
        return new self(['payments.refund', 'payments.export'], false);
    }

    public static function viewVerifications(): self
    {
        return new self('verifications.view');
    }

    public static function approveVerifications(): self
    {
        return new self(['verifications.approve', 'verifications.reject'], false);
    }

    public static function viewUsers(): self
    {
        return new self('users.view');
    }

    public static function manageUsers(): self
    {
        return new self(['users.create', 'users.edit', 'users.delete'], false);
    }

    public static function viewSettings(): self
    {
        return new self('settings.view');
    }

    public static function editSettings(): self
    {
        return new self('settings.edit');
    }

    public static function viewReports(): self
    {
        return new self('reports.view');
    }

    public static function exportReports(): self
    {
        return new self('reports.export');
    }

    public static function fullAccess(): self
    {
        return new self('*');
    }
}
