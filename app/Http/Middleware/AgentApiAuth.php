<?php

namespace App\Http\Middleware;

use App\Models\AgentApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentApiAuth
{
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        $token = $request->bearerToken();

        if (! $token || ! str_starts_with($token, 'mng_sk_')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Provide Bearer token with mng_sk_ prefix.',
            ], 401);
        }

        $apiKey = AgentApiKey::findByKey($token);

        if (! $apiKey || ! $apiKey->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired API key.',
            ], 401);
        }

        if (! empty($scopes)) {
            foreach ($scopes as $scope) {
                if (! $apiKey->hasScope($scope)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Missing required scope: {$scope}",
                    ], 403);
                }
            }
        }

        $apiKey->markUsed();

        $request->merge(['agent_api_key' => $apiKey]);

        return $next($request);
    }
}
