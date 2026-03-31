<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check session auth
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_phone'])) {
            $request = $request
                ->withAttribute('user_id', $_SESSION['user_id'])
                ->withAttribute('user_phone', $_SESSION['user_phone'])
                ->withAttribute('user_type', $_SESSION['user_type'] ?? 'employer');

            return $handler->handle($request);
        }

        // Return 401 if not authenticated
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Please login to continue'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
