<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RoleMiddleware implements MiddlewareInterface
{
    private string $resource;
    private string $action;

    public function __construct(string $resource, string $action)
    {
        $this->resource = $resource;
        $this->action = $action;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permissions = $request->getAttribute('admin_permissions', []);

        // Check if user has required permission
        if ($this->hasPermission($permissions)) {
            return $handler->handle($request);
        }

        // Return 403 if not authorized
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => "You don't have permission to {$this->action} {$this->resource}"
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }

    private function hasPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($permission['resource'] === $this->resource && $permission['action'] === $this->action) {
                return true;
            }
            // Wildcard permission check
            if ($permission['resource'] === '*' || $permission['action'] === '*') {
                return true;
            }
        }
        return false;
    }
}
