<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpTokenIsValid
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        // Fallback to reading directly from env if config is cached incorrectly
        $expectedToken = env('MCP_SECRET_KEY'); 

        if (!$expectedToken) {
            return response()->json(['message' => 'MCP Secret Key is not configured on the backend.'], 500);
        }

        if (!$token || $token !== $expectedToken) {
            return response()->json(['message' => 'Unauthorized. Invalid MCP token.'], 401);
        }

        return $next($request);
    }
}
