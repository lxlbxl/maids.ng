<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Middleware that supports both JWT and Session authentication
 * Use this for API endpoints that need to support mobile apps
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Try JWT authentication first
        $authHeader = $request->getHeaderLine('Authorization');
        $token = $this->jwtService->extractTokenFromHeader($authHeader);

        if ($token) {
            $payload = $this->jwtService->validateToken($token);

            if ($payload && !isset($payload['error'])) {
                // Valid JWT - check if it's an access token
                if (!$this->jwtService->isAccessToken($payload)) {
                    return $this->unauthorizedResponse('Invalid token type. Access token required.');
                }

                $userId = $this->jwtService->getUserIdFromToken($payload);
                $userData = $payload['data'] ?? [];

                $request = $request
                    ->withAttribute('user_id', $userId)
                    ->withAttribute('user_phone', $userData['phone'] ?? null)
                    ->withAttribute('user_type', $userData['role'] ?? 'employer')
                    ->withAttribute('auth_method', 'jwt');

                return $handler->handle($request);
            }

            // Token was provided but invalid
            if (isset($payload['error'])) {
                $errorCode = $payload['code'] ?? 'INVALID_TOKEN';
                $errorMessage = $payload['error'] ?? 'Invalid token';

                if ($errorCode === 'TOKEN_EXPIRED') {
                    return $this->unauthorizedResponse($errorMessage, 401, 'TOKEN_EXPIRED');
                }

                return $this->unauthorizedResponse($errorMessage);
            }
        }

        // Fall back to session authentication
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_phone'])) {
            $request = $request
                ->withAttribute('user_id', $_SESSION['user_id'])
                ->withAttribute('user_phone', $_SESSION['user_phone'])
                ->withAttribute('user_type', $_SESSION['user_type'] ?? 'employer')
                ->withAttribute('auth_method', 'session');

            return $handler->handle($request);
        }

        // No valid authentication found
        return $this->unauthorizedResponse('Authentication required. Please login.');
    }

    private function unauthorizedResponse(string $message, int $status = 401, string $code = 'UNAUTHORIZED'): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
