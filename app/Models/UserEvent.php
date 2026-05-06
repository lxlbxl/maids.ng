<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserEvent Model
 * 
 * Tracks user interactions for analytics and metrics:
 * - Page views
 * - Quiz starts/abandonments/completions
 * - Match views and clicks
 * - Booking and payment events
 * - Login/logout events
 */
class UserEvent extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'event_type',
        'page_url',
        'event_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'event_data' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ─── Static Helpers ───────────────────────────────────────────────────────

    /**
     * Record a user event.
     *
     * @param string $eventType The type of event (quiz_start, quiz_abandon, etc.)
     * @param array $data Additional event data
     * @param int|null $userId Optional user ID (null for guest events)
     * @param string|null $sessionId Optional session ID (auto-detected if null)
     * @param string|null $pageUrl Optional page URL (auto-detected if null)
     */
    public static function record(
        string $eventType,
        array $data = [],
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $pageUrl = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'session_id' => $sessionId ?? session()->getId(),
            'event_type' => $eventType,
            'page_url' => $pageUrl,
            'event_data' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Record a quiz start event.
     */
    public static function recordQuizStart(int $userId = null, array $data = []): self
    {
        return self::record('quiz_start', $data, $userId);
    }

    /**
     * Record a quiz abandonment event.
     */
    public static function recordQuizAbandon(int $userId = null, array $data = []): self
    {
        return self::record('quiz_abandon', $data, $userId);
    }

    /**
     * Record a quiz completion event.
     */
    public static function recordQuizComplete(int $userId = null, array $data = []): self
    {
        return self::record('quiz_complete', $data, $userId);
    }

    /**
     * Record a matches viewed event.
     */
    public static function recordMatchesViewed(int $userId = null, array $data = []): self
    {
        return self::record('matches_viewed', $data, $userId);
    }

    /**
     * Record a page view event.
     */
    public static function recordPageView(int $userId = null, array $data = []): self
    {
        return self::record('page_view', $data, $userId);
    }
}