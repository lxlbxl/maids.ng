<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class DeprecatedMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private string $sunsetDate;
    private string $replacementUrl;

    public function __construct(LoggerInterface $logger, string $sunsetDate = '2026-10-01', string $replacementUrl = '/api/v1')
    {
        $this->logger = $logger;
        $this->sunsetDate = $sunsetDate;
        $this->replacementUrl = $replacementUrl;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Log deprecation warning
        $path = $request->getUri()->getPath();
        $this->logger->warning("DEPRECATED ENDPOINT ACCESSED", [
            'path' => $path,
            'method' => $request->getMethod(),
            'sunset_date' => $this->sunsetDate
        ]);

        // Add deprecation headers
        $response = $handler->handle($request);

        $deprecationHeader = "This endpoint is deprecated and will be removed on {$this->sunsetDate}. Please migrate to {$this->replacementUrl}";
        $response = $response->withHeader('Deprecation', $deprecationHeader);
        $response = $response->withHeader('Link', "<{$this->replacementUrl}>; rel=\"successor-version\"");

        return $response;
    }
}
