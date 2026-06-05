<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request with rate limiting.
     * Default: 60 requests per minute per IP. API routes: 30/min. Auth routes: 5/min.
     */
    public function handle(Request $request, Closure $next, string $tier = 'standard'): Response
    {
        $key = $this->resolveKey($request);
        $maxAttempts = $this->getMaxAttempts($tier);
        $decayMinutes = 1;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $this->limiter->remaining($key, $maxAttempts),
        ]);
    }

    protected function resolveKey(Request $request): string
    {
        return 'rate_limit:' . ($request->user()?->id ?: $request->ip()) . ':' . $request->path();
    }

    protected function getMaxAttempts(string $tier): int
    {
        return match ($tier) {
            'auth' => 5,           // 5 attempts/min for login/register
            'api' => 30,           // 30 requests/min for API
            'payment' => 10,       // 10 requests/min for payment endpoints
            'verification' => 3,   // 3 requests/min for ID verification
            default => 60,         // 60 requests/min standard
        };
    }
}
