<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Log significant user actions for audit trail.
     * Captures: user, IP, route, method, timestamp, and relevant request data.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log state-changing requests (POST, PUT, PATCH, DELETE)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->logAction($request, $response);
        }

        return $response;
    }

    protected function logAction(Request $request, Response $response): void
    {
        try {
            // Filter out sensitive fields
            $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'card_number', 'cvv'];
            $data = collect($request->except($sensitiveFields))
                ->filter(fn($v) => !is_null($v) && $v !== '')
                ->take(20) // Limit stored fields
                ->toArray();

            \App\Models\AgentActivityLog::create([
                'agent_name' => 'AuditMiddleware',
                'action' => $request->method() . ' ' . $request->path(),
                'reasoning' => json_encode([
                    'user_id' => $request->user()?->id,
                    'user_name' => $request->user()?->name,
                    'ip' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                    'route' => $request->route()?->getName(),
                    'status_code' => $response->getStatusCode(),
                    'data_keys' => array_keys($data),
                ]),
                'decision' => $response->getStatusCode() < 400 ? 'success' : 'failure',
            ]);
        } catch (\Throwable $e) {
            // Silently fail — audit logging should never break the app
            \Illuminate\Support\Facades\Log::warning('AuditLog failed: ' . $e->getMessage());
        }
    }
}
