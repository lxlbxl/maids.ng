<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\HireRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hire requests — the unit everything else hangs off.
 *
 * One request = one household asking for one helper. An employer may hold
 * several at once, each with its own fee, queue, placement and guarantee.
 */
class HireRequestController extends ApiController
{
    /** Open a request. This is the first step of any hire, before money. */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'employer_id'      => 'required|integer|exists:users,id',
            'preference_id'    => 'nullable|integer|exists:employer_preferences,id',
            'role'             => 'nullable|string|max:120',
            'area'             => 'nullable|string|max:120',
            'live_arrangement' => 'nullable|in:live_in,live_out',
            'details'          => 'nullable|string|max:2000',
            'job_code'         => 'nullable|string|max:16',
            'fee_amount'       => 'nullable|integer|min:1000|max:500000',
        ]);

        $r = HireRequest::open($v);

        return $this->success($this->present($r),
            "Request {$r->reference} opened — fee ₦" . number_format($r->fee_amount), [], 201);
    }

    /** Every request for an employer, open ones first. */
    public function forEmployer(int $employerId): JsonResponse
    {
        $rows = HireRequest::where('employer_id', $employerId)
            ->orderByRaw("CASE WHEN status IN ('open','paid','matching','matched') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get();

        $open = $rows->whereIn('status', HireRequest::OPEN_STATUSES);

        return $this->success([
            'employer_id'    => $employerId,
            'open_requests'  => $open->count(),
            'total_requests' => $rows->count(),
            // What the household still owes, so an agent never has to work it out
            'outstanding_fees' => '₦' . number_format(
                $open->filter(fn ($r) => !$r->isPaid())->sum('fee_amount')),
            'requests'       => $rows->map(fn ($r) => $this->present($r))->values(),
        ], $rows->count() ? 'Requests retrieved' : 'This employer has no requests yet');
    }

    public function show(string $reference): JsonResponse
    {
        $r = HireRequest::where('reference', $reference)->orWhere('id', $reference)->first();
        if (!$r) {
            return $this->error('No such request.', 404);
        }

        return $this->success($this->present($r), 'Request retrieved');
    }

    /**
     * Mark the helper resumed — which is what completes a request.
     *
     * Not payment, and not the match: those are promises. Resumption is the
     * outcome the family paid for, so it is what closes the request.
     */
    public function resumed(Request $request, string $reference): JsonResponse
    {
        $v = $request->validate(['notes' => 'nullable|string|max:300']);

        $r = HireRequest::where('reference', $reference)->orWhere('id', $reference)->first();
        if (!$r) {
            return $this->error('No such request.', 404);
        }
        if ($r->status === 'fulfilled') {
            return $this->success($this->present($r), 'Already marked fulfilled.');
        }
        if (!$r->maid_user_id) {
            return $this->error('This request has no helper assigned yet — match it first.', 422);
        }

        $r->markResumed($v['notes'] ?? null);

        return $this->success($this->present($r->fresh()),
            "{$r->reference} fulfilled — helper confirmed resumed.");
    }

    /** Withdraw a request that will not be filled. */
    public function cancel(Request $request, string $reference): JsonResponse
    {
        $v = $request->validate(['reason' => 'required|string|max:200']);

        $r = HireRequest::where('reference', $reference)->orWhere('id', $reference)->first();
        if (!$r) {
            return $this->error('No such request.', 404);
        }
        if ($r->status === 'fulfilled') {
            return $this->error('This request is already fulfilled — it cannot be cancelled.', 422);
        }

        $r->update([
            'status' => 'cancelled', 'closed_at' => now(), 'close_reason' => $v['reason'],
        ]);

        return $this->success($this->present($r->fresh()), 'Request cancelled');
    }

    /** Open requests across the business, for Operations. */
    public function open(): JsonResponse
    {
        $rows = HireRequest::whereIn('status', HireRequest::OPEN_STATUSES)
            ->orderBy('created_at')->get();

        return $this->success([
            'count'    => $rows->count(),
            'requests' => $rows->map(fn ($r) => $this->present($r))->values(),
        ], "{$rows->count()} open request(s)");
    }

    /** @return array<string, mixed> */
    private function present(HireRequest $r): array
    {
        return [
            'reference'           => $r->reference,
            'id'                  => $r->id,
            'employer_id'         => $r->employer_id,
            'employer_name'       => User::find($r->employer_id)?->name,
            'role'                => $r->role,
            'area'                => $r->area,
            'live_arrangement'    => $r->live_arrangement,
            'job_code'            => $r->job_code,
            'status'              => $r->status,
            'fee_amount'          => (int) $r->fee_amount,
            'paid'                => $r->isPaid(),
            'maid_user_id'        => $r->maid_user_id,
            'maid_name'           => $r->maid_user_id ? User::find($r->maid_user_id)?->name : null,
            'assignment_id'       => $r->assignment_id,
            'fulfillment_case_id' => $r->fulfillment_case_id,
            'paid_at'             => $r->paid_at,
            'matched_at'          => $r->matched_at,
            'resumed_at'          => $r->resumed_at,
            'closed_at'           => $r->closed_at,
            'close_reason'        => $r->close_reason,
        ];
    }
}
