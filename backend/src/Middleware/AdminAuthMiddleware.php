<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check admin session
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role_id'])) {
            $request = $request
                ->withAttribute('admin_id', $_SESSION['admin_id'])
                ->withAttribute('admin_email', $_SESSION['admin_email'])
                ->withAttribute('admin_name', $_SESSION['admin_name'])
                ->withAttribute('admin_role_id', $_SESSION['admin_role_id'])
                ->withAttribute('admin_permissions', $_SESSION['admin_permissions'] ?? []);

            return $handler->handle($request);
        }

        // Return 401 if not authenticated
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Admin authentication required'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
