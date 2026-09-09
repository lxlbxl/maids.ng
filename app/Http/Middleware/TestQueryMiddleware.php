<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TestQueryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('Middleware received', [
            'mode' => $request->input('hub.mode'),
            'token' => $request->input('hub.verify_token'),
            'challenge' => $request->input('hub.challenge'),
        ]);
        return $next($request);
    }
}
