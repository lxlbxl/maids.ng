<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $storageDir;
    private array $strictEndpoints = [
        '/api/auth/login' => ['max' => 5, 'window' => 60],      // 5 per minute
        '/api/auth/register' => ['max' => 3, 'window' => 60],   // 3 per minute
        '/admin/api/auth/login' => ['max' => 5, 'window' => 300], // 5 per 5 minutes
        '/api/payments/initialize' => ['max' => 10, 'window' => 60], // 10 per minute
        '/api/verification/nin' => ['max' => 5, 'window' => 300], // 5 per 5 minutes
    ];

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->storageDir = dirname(__DIR__, 2) . '/storage/rate_limits';

        // Ensure storage directory exists
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $this->getClientIp($request);
        $path = $request->getUri()->getPath();

        // Get rate limit for this endpoint
        $limits = $this->getLimitsForPath($path);
        $maxRequests = $limits['max'];
        $windowSeconds = $limits['window'];

        // Generate unique key for this IP + endpoint combination
        $key = $this->generateKey($clientIp, $path);

        // Check rate limit
        $rateLimitInfo = $this->checkRateLimit($key, $maxRequests, $windowSeconds);

        if (!$rateLimitInfo['allowed']) {
            return $this->tooManyRequestsResponse($rateLimitInfo);
        }

        // Process request and add rate limit headers
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $rateLimitInfo['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $rateLimitInfo['reset']);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        // Check for forwarded IP (behind proxy/load balancer)
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwardedFor)) {
            $ips = array_map('trim', explode(',', $forwardedFor));
            return $ips[0]; // First IP is the client
        }

        $realIp = $request->getHeaderLine('X-Real-IP');
        if (!empty($realIp)) {
            return $realIp;
        }

        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function getLimitsForPath(string $path): array
    {
        // Check for strict endpoint limits
        foreach ($this->strictEndpoints as $endpoint => $limits) {
            if (str_starts_with($path, $endpoint)) {
                return $limits;
            }
        }

        // Default limits
        return [
            'max' => $this->maxRequests,
            'window' => $this->windowSeconds,
        ];
    }

    private function generateKey(string $ip, string $path): string
    {
        // Normalize path to group similar endpoints
        $normalizedPath = preg_replace('/\/\d+/', '/{id}', $path);
        return md5($ip . ':' . $normalizedPath);
    }

    private function checkRateLimit(string $key, int $maxRequests, int $windowSeconds): array
    {
        $file = $this->storageDir . '/' . $key . '.json';
        $now = time();

        // Read existing data
        $data = $this->readRateLimitData($file);

        // Clean old entries
        $windowStart = $now - $windowSeconds;
        $data['requests'] = array_filter(
            $data['requests'] ?? [],
            fn($timestamp) => $timestamp > $windowStart
        );

        // Count requests in window
        $requestCount = count($data['requests']);

        if ($requestCount >= $maxRequests) {
            // Calculate reset time
            $oldestRequest = min($data['requests']);
            $resetTime = $oldestRequest + $windowSeconds;

            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $resetTime,
                'retry_after' => $resetTime - $now,
            ];
        }

        // Add current request
        $data['requests'][] = $now;
        $this->writeRateLimitData($file, $data);

        return [
            'allowed' => true,
            'remaining' => $maxRequests - count($data['requests']),
            'reset' => $now + $windowSeconds,
        ];
    }

    private function readRateLimitData(string $file): array
    {
        if (!file_exists($file)) {
            return ['requests' => []];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return ['requests' => []];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : ['requests' => []];
    }

    private function writeRateLimitData(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    private function tooManyRequestsResponse(array $rateLimitInfo): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $rateLimitInfo['retry_after'],
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-RateLimit-Limit', '0')
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset', (string) $rateLimitInfo['reset'])
            ->withHeader('Retry-After', (string) $rateLimitInfo['retry_after'])
            ->withStatus(429);
    }

    /**
     * Clean up old rate limit files (run periodically via cron)
     */
    public static function cleanup(string $storageDir = null): int
    {
        $dir = $storageDir ?? dirname(__DIR__, 2) . '/storage/rate_limits';

        if (!is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        $files = glob($dir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            // Delete files older than 1 hour
            if (filemtime($file) < ($now - 3600)) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
