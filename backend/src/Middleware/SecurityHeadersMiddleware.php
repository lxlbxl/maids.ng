<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $cspDirectives;

    public function __construct(array $cspDirectives = null)
    {
        $this->cspDirectives = $cspDirectives ?? [
            'default-src' => "'self'",
            'script-src' => "'self' https://checkout.flutterwave.com https://cdn.tailwindcss.com 'unsafe-inline'",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src' => "'self' https://fonts.gstatic.com",
            'img-src' => "'self' data: https:",
            'connect-src' => "'self' https://api.flutterwave.com https://api.paystack.co https://api.qoreid.com https://n8n.ai20.city",
            'frame-ancestors' => "'none'",
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Content Security Policy
        $cspHeader = implode('; ', array_map(
            fn($directive, $value) => "$directive $value",
            array_keys($this->cspDirectives),
            $this->cspDirectives
        ));
        $response = $response->withHeader('Content-Security-Policy', $cspHeader);

        // Prevent clickjacking
        $response = $response->withHeader('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');

        // Referrer policy
        $response = $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy (restrict sensitive features)
        $response = $response->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS only in production
        if ($_ENV['APP_ENV'] ?? 'development' === 'production') {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
