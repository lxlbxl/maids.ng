<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One household asking for one helper. See the migration for why this exists.
 */
class HireRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'employer_id', 'preference_id', 'replaces_request_id',
        'role', 'area', 'live_arrangement', 'details', 'job_code',
        'status', 'fee_amount',
        'paid_at', 'matched_at', 'resumed_at', 'closed_at', 'close_reason',
        'assignment_id', 'fulfillment_case_id', 'maid_user_id',
    ];

    protected $casts = [
        'paid_at'    => 'datetime',
        'matched_at' => 'datetime',
        'resumed_at' => 'datetime',
        'closed_at'  => 'datetime',
        'fee_amount' => 'integer',
    ];

    /** A request is live until it is filled or withdrawn. */
    public const OPEN_STATUSES = ['open', 'paid', 'matching', 'matched'];

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function maid()
    {
        return $this->belongsTo(User::class, 'maid_user_id');
    }

    public function payments()
    {
        return $this->hasMany(MatchingFeePayment::class, 'hire_request_id');
    }

    public function assignment()
    {
        return $this->belongsTo(MaidAssignment::class, 'assignment_id');
    }

    /**
     * Has this request been paid for?
     *
     * A guarantee replacement inherits the original's payment: the household has
     * already paid for a helper and did not get one that stuck, so the
     * replacement is covered. Walks the chain in case a replacement itself had
     * to be replaced, with a depth cap so a bad link cannot loop forever.
     */
    public function isPaid(): bool
    {
        if ($this->paid_at !== null
            || $this->payments()->whereIn('status', ['paid', 'completed'])->exists()) {
            return true;
        }

        $seen = [$this->id];
        $node = $this;
        for ($i = 0; $i < 5 && $node->replaces_request_id; $i++) {
            if (in_array($node->replaces_request_id, $seen, true)) {
                break;
            }
            $seen[] = $node->replaces_request_id;
            $node = static::find($node->replaces_request_id);
            if (!$node) {
                break;
            }
            if ($node->paid_at !== null
                || $node->payments()->whereIn('status', ['paid', 'completed'])->exists()) {
                return true;
            }
        }

        return false;
    }

    /** The request this one replaces under the 10-day guarantee, if any. */
    public function replaces()
    {
        return $this->belongsTo(self::class, 'replaces_request_id');
    }

    /**
     * Open a request. The reference is derived from the id so it is stable,
     * sortable and quotable in a WhatsApp thread.
     */
    public static function open(array $attrs): self
    {
        return DB::transaction(function () use ($attrs) {
            $r = static::create(array_merge([
                'reference'  => 'REQ-TEMP-' . uniqid(),
                'status'     => 'open',
                'fee_amount' => (int) (Setting::get('matching_fee_amount') ?: 20000),
            ], $attrs));

            $r->update(['reference' => 'REQ-' . str_pad((string) $r->id, 5, '0', STR_PAD_LEFT)]);

            return $r->fresh();
        });
    }

    /**
     * The helper started. This — not payment, not the match — is what the family
     * is paying for, so it is what closes the request.
     */
    public function markResumed(?string $reason = null): void
    {
        $this->update([
            'status'       => 'fulfilled',
            'resumed_at'   => $this->resumed_at ?? now(),
            'closed_at'    => now(),
            'close_reason' => $reason ?? 'helper confirmed resumed',
        ]);
    }
}
