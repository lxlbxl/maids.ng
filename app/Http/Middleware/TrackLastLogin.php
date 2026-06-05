<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * TrackLastLogin Middleware
 * 
 * Updates the `last_login_at` timestamp on the users table whenever
 * an authenticated user makes a request. This enables:
 * - User activity analytics
 * - Inactive user identification
 * - Engagement metrics
 */
class TrackLastLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Only update if it's been at least 1 minute since last update
            // to avoid excessive database writes on every request
            if (!$user->last_login_at || $user->last_login_at->diffInMinutes(now()) >= 1) {
                $user->update([
                    'last_login_at' => now(),
                ]);
            }
        }

        return $next($request);
    }
}