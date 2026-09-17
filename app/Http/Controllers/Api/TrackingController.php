<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public event intake for the front end.
 *
 * Without this the funnel could only see what the server happened to touch:
 * quiz_complete and matches_viewed fired server-side, but quiz_start had never
 * been recorded once and page views not at all — so "sessions" counted people
 * who finished a quiz rather than people who arrived, and there was no way to
 * see where in the quiz anyone gave up.
 *
 * Deliberately unauthenticated and rate-limited: visitors are anonymous until
 * they register, and that is exactly the part of the funnel we were blind to.
 */
class TrackingController extends Controller
{
    /** Events a visitor's browser may report. Anything else is ignored. */
    private const ALLOWED = [
        'page_view', 'quiz_start', 'quiz_step', 'quiz_abandon',
        'matches_viewed', 'cta_click', 'payment_started',
    ];

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'event'    => 'required|string|max:40',
            'url'      => 'nullable|string|max:500',
            'data'     => 'nullable|array',
        ]);

        if (!in_array($v['event'], self::ALLOWED, true)) {
            // Not an error: an old client should not see failures for an event
            // name we have since retired.
            return response()->json(['success' => true, 'recorded' => false]);
        }

        UserEvent::record(
            $v['event'],
            $v['data'] ?? [],
            $request->user()?->id,
            null,   // session id falls back to the current session
            $v['url'] ?? $request->headers->get('referer')
        );

        return response()->json(['success' => true, 'recorded' => true]);
    }
}
