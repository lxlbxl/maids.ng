<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct()
    {
        // Load allowed origins from environment variable (comma-separated)
        $originsEnv = $_ENV['CORS_ALLOWED_ORIGINS'] ?? $_SERVER['CORS_ALLOWED_ORIGINS'] ?? '';

        if (empty($originsEnv)) {
            // Default allowed origins for development
            $this->allowedOrigins = [
                'http://localhost:8000',
                'http://localhost:3000',
                'http://127.0.0.1:8000',
            ];
        } else {
            $this->allowedOrigins = array_map('trim', explode(',', $originsEnv));
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            return $this->addCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        // Only allow specific origins, not wildcard
        $allowedOrigin = $this->getAllowedOrigin($origin);

        if ($allowedOrigin === null) {
            // Origin not allowed - don't add CORS headers
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400');
    }

    private function getAllowedOrigin(string $origin): ?string
    {
        if (empty($origin)) {
            // Same-origin requests don't have Origin header
            return $this->allowedOrigins[0] ?? null;
        }

        // Check if origin is in allowed list
        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        // Check for wildcard subdomain patterns (e.g., *.maids.ng)
        foreach ($this->allowedOrigins as $allowed) {
            if (str_starts_with($allowed, '*.')) {
                $domain = substr($allowed, 2);
                $originHost = parse_url($origin, PHP_URL_HOST);
                if ($originHost && (str_ends_with($originHost, '.' . $domain) || $originHost === $domain)) {
                    return $origin;
                }
            }
        }

        return null;
    }
}
