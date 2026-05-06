<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserEvent;
use Illuminate\Http\Request;

/**
 * UserEvent API Controller
 * 
 * Receives analytics events from the frontend via sendBeacon/fetch.
 * Used by OnboardingQuiz for tracking quiz lifecycle events.
 */
class UserEventController extends Controller
{
    /**
     * Record a user event from the frontend.
     *
     * POST /api/user-events
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:50',
            'page_url' => 'nullable|string|max:500',
            'event_data' => 'nullable|array',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        UserEvent::create([
            'user_id' => $validated['user_id'] ?? $request->user()?->id,
            'session_id' => $request->session()?->getId() ?? uniqid('sess_'),
            'event_type' => $validated['event_type'],
            'page_url' => $validated['page_url'] ?? $request->header('Referer'),
            'event_data' => $validated['event_data'] ?? $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }
}