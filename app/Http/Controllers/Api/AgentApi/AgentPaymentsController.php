<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentNote;
use App\Models\EmployerPreference;
use App\Models\EmployerWallet;
use App\Models\MaidAssignment;
use App\Models\MatchingFeePayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FlutterwavePwbtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentPaymentsController extends ApiController
{
    public function status($userId): JsonResponse
    {
        try {
            // 'paid' is what every write path actually sets — reconcile(),
            // recordPayment() and the webhook all use it, and nothing in the
            // codebase has ever written 'completed'. Filtering on 'completed'
            // alone made this endpoint answer has_paid=false for every customer
            // who had ever paid, which is what drove agents to record the same
            // transfer again and again (8 rows for one ₦20,000 transfer on
            // employer 460). 'completed' is kept for forward compatibility.
            $payment = MatchingFeePayment::where('employer_id', $userId)
                ->whereIn('status', ['paid', 'completed'])
                ->latest()
                ->first();

            return $this->success([
                'has_paid'     => $payment !== null,
                'reference'    => $payment?->reference,
                'paid_at'      => $payment?->paid_at,
                'payment_type' => $payment?->payment_type,
            ], 'Payment status retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to check payment status: ' . $e->getMessage(), 500);
        }
    }

    public function generateLink(): JsonResponse
    {
        $amount = config('settings.matching_fee_amount', 5000);
        $reference = 'mng_' . uniqid();
        $callbackUrl = config('app.url') . '/api/v1/payment/callback';

        return $this->success([
            'payment_link'       => "https://paystack.com/pay/maids-matching-fee?amount={$amount}&reference={$reference}&callback_url=" . urlencode($callbackUrl),
            'reference'          => $reference,
            'amount'             => $amount,
            'currency'           => 'NGN',
            'callback_url'       => $callbackUrl,
        ], 'Payment link generated');
    }

    /**
     * Generate a Flutterwave Pay with Bank Transfer (PWBT) virtual account.
     *
     * Called by the Paperclip agent when a WhatsApp user wants to pay the
     * matching fee in-chat. Returns bank details formatted for WhatsApp display.
     * No BVN/NIN required — works for unverified users.
     */
    public function generatePwbt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount'  => 'nullable|integer|min:1000|max:500000',
            // Set when the employer is hiring an ADDITIONAL helper rather than
            // paying again for the same one. The matching fee is per helper —
            // one household commonly wants two or three (MAI-1209 wants three
            // housekeepers; MNG-7 is two nannies for two elderly parents).
            'new_request' => 'nullable|boolean',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $amount = $validated['amount'] ?? (int) (config('settings.matching_fee_amount', 20000));

            $pwbtService = app(FlutterwavePwbtService::class);
            $result = $pwbtService->generateForUser($user, $amount, $request->boolean('new_request'));

            return $this->success([
                'payment_id'         => $result['payment_id'],
                'tx_ref'             => $result['tx_ref'],
                'amount'             => $result['amount'],
                'currency'           => $result['currency'],
                'account_number'     => $result['account_number'],
                'account_bank'       => $result['account_bank'],
                'account_name'       => $result['account_name'],
                // The fixed account never expires, so both fields are null now.
                // Kept in the response so any agent prompt or client still
                // reading them gets an explicit "no expiry" rather than a
                // missing key.
                'expires_at'         => $result['expires_at'] ?? null,
                'expires_in_minutes' => null,
                'already_paid'       => $result['already_paid'] ?? false,
                'whatsapp_text'      => $result['whatsapp_text'],
            ], 'PWBT account generated');
        } catch (\RuntimeException $e) {
            Log::error('PWBT generation failed', [
                'user_id' => $validated['user_id'] ?? '?',
                'error' => $e->getMessage(),
            ]);

            // Provider not configured / down — tell the agent to stop retrying and
            // fall back to manual payment rather than hammering the endpoint.
            if (str_contains($e->getMessage(), 'PWBT_UNAVAILABLE')) {
                return response()->json([
                    'success'     => false,
                    'error_code'  => 'PWBT_UNAVAILABLE',
                    'message'     => 'In-chat bank transfer is temporarily unavailable. Do NOT retry this endpoint.',
                    'fallback'    => [
                        'action'       => 'manual_payment',
                        'instructions' => 'Ask the lead to pay the ₦'
                            . number_format($amount ?? 20000)
                            . ' matching fee via the website checkout link, or collect a firm commitment and '
                            . 'create a HIGH-priority note for the ops team to send account details. '
                            . 'Once they pay, record it with POST /payments/record.',
                    ],
                ], 503);
            }

            return $this->error('Failed to generate payment account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actively pull a PWBT transaction's status from Flutterwave and, if it has
     * been paid, mark the payment complete + fire PaymentConfirmed.
     *
     * This is the agent's source of truth for in-chat payments. We do NOT rely
     * on the Flutterwave webhook because the same Flutterwave account is shared
     * across several Digital20 brands and its single webhook URL may not point
     * here. Payments into the fixed virtual account carry no reference of ours,
     * so attribution is by amount + virtual account + time window, with each
     * Flutterwave transaction id claimable exactly once (unique index on
     * flutterwave_tx_id). Where several customers are waiting on the same amount
     * and the sender name gives no signal, the result is 'ambiguous' rather than
     * a guess — that case needs a human to match the transfer.
     *
     * Accepts any one of: payment_id, tx_ref, user_id (latest pending PWBT).
     * Idempotent — safe to poll repeatedly.
     */
    public function verifyPwbt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'nullable|integer',
            'tx_ref'     => 'nullable|string',
            'user_id'    => 'nullable|integer',
        ]);

        $query = MatchingFeePayment::where('gateway', 'flutterwave')->whereNotNull('tx_ref');

        if (!empty($validated['payment_id'])) {
            $query->where('id', $validated['payment_id']);
        } elseif (!empty($validated['tx_ref'])) {
            $query->where('tx_ref', $validated['tx_ref']);
        } elseif (!empty($validated['user_id'])) {
            $query->where('employer_id', $validated['user_id'])->latest();
        } else {
            return $this->error('Provide payment_id, tx_ref, or user_id.', 422);
        }

        $payment = $query->first();
        if (!$payment) {
            return $this->error('No matching PWBT payment found.', 404);
        }

        $result = app(FlutterwavePwbtService::class)->reconcile($payment->fresh());

        $messages = [
            'already'         => 'Payment already confirmed.',
            'paid'            => 'Payment confirmed.',
            'pending'         => 'No successful transfer seen yet.',
            'amount_mismatch' => 'Amount paid does not match the matching fee.',
            'ref_mismatch'    => 'Transaction reference mismatch.',
            'gateway_error'   => 'Could not reach Flutterwave — try again shortly.',
            'ambiguous'       => 'A transfer arrived but more than one customer is waiting on this amount — '
                               . 'escalate so a human can match it. Do NOT record it manually.',
        ];

        $payment->refresh();

        return $this->success(array_merge($result, [
            'reference'  => $payment->reference ?? $payment->tx_ref,
            'amount'     => (int) $payment->amount,
            'paid_at'    => $payment->paid_at,
            'expires_at' => $payment->expires_at,
        ]), $messages[$result['status']] ?? 'Status checked.');
    }

    /**
     * Confirm a payment from the evidence on the customer's own bank receipt.
     *
     * verify-pwbt answers "did anyone pay?" for a single open record. This
     * answers the harder question — "is THIS customer's transfer the one that
     * arrived?" — which amount and timing alone cannot settle once two people
     * are paying ₦20,000 on the same evening.
     *
     * Evidence, strongest first:
     *   session_id  — "Session ID" / "Transaction Reference" on the receipt.
     *                 Flutterwave stores the same NIBSS string as flw_ref, so a
     *                 match is exact and decisive on its own.
     *   sender_name + sender_bank — both must agree with the originator
     *                 Flutterwave recorded. Either alone is not enough.
     *
     * If the customer sent a receipt screenshot, read these fields off the
     * image and pass them here — this endpoint takes the extracted values, not
     * the image itself.
     *
     * Never invents a match: unmatched evidence returns not_found, and two
     * equally plausible transfers return ambiguous for a human to settle.
     */
    public function claimTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'     => 'required_without:payment_id|nullable|integer',
            'payment_id'  => 'required_without:user_id|nullable|integer',
            'session_id'  => 'nullable|string|max:120',
            'sender_name' => 'nullable|string|max:160',
            'sender_bank' => 'nullable|string|max:120',
        ]);

        if (empty($validated['session_id']) && (empty($validated['sender_name']) || empty($validated['sender_bank']))) {
            return $this->error(
                'Provide session_id, or BOTH sender_name and sender_bank. '
                . 'Ask the customer for their transfer receipt — the session id is on it.',
                422
            );
        }

        $query = MatchingFeePayment::query();
        if (!empty($validated['payment_id'])) {
            $query->where('id', $validated['payment_id']);
        } else {
            $query->where('employer_id', $validated['user_id'])
                  ->whereIn('status', ['pending', 'paid'])
                  ->latest();
        }

        $payment = $query->first();
        if (!$payment) {
            return $this->error('No payment record found for this customer. Call generate-pwbt first.', 404);
        }

        if (in_array($payment->status, ['paid', 'completed'], true)) {
            return $this->success([
                'status' => 'already', 'paid' => true,
                'payment_id' => $payment->id, 'reference' => $payment->reference,
            ], 'Payment already confirmed.');
        }

        $pwbt = app(FlutterwavePwbtService::class);
        $found = $pwbt->findTransferByEvidence($payment, [
            'session_id'  => $validated['session_id'] ?? null,
            'sender_name' => $validated['sender_name'] ?? null,
            'sender_bank' => $validated['sender_bank'] ?? null,
        ]);

        switch ($found['status']) {
            case 'matched':
                $result = $pwbt->settleFromEvidence($payment->fresh(), $found['transaction']);
                $payment->refresh();

                if (empty($result['paid'])) {
                    return $this->success([
                        'status' => 'pending', 'paid' => false, 'payment_id' => $payment->id,
                    ], 'That transfer was claimed by another record a moment ago — re-check in a few seconds.');
                }

                return $this->success([
                    'status'      => 'paid',
                    'paid'        => true,
                    'payment_id'  => $payment->id,
                    'amount'      => (int) $payment->amount,
                    'reference'   => $payment->reference,
                    'flw_ref'     => $payment->flw_ref,
                    'sender_name' => $found['transaction']['meta']['originatorname'] ?? null,
                    'sender_bank' => $found['transaction']['meta']['bankname'] ?? null,
                ], 'Payment confirmed from receipt evidence.');

            case 'already_claimed':
                return $this->success([
                    'status' => 'already_claimed', 'paid' => false,
                    'hint'   => 'That transfer is already recorded against another customer. '
                              . 'Do not record it again — escalate to Operations Manager.',
                ], 'Transfer already claimed.');

            case 'ambiguous':
                return $this->success([
                    'status' => 'ambiguous', 'paid' => false,
                    'candidates' => $found['candidates'] ?? null,
                    'hint'   => 'More than one transfer fits this evidence. Ask the customer for the '
                              . 'session id from their receipt — that is exact.',
                ], 'Could not tell the transfers apart.');

            default:
                return $this->success([
                    'status' => 'not_found', 'paid' => false,
                    'hint'   => 'No transfer on our account matches that evidence yet. Bank transfers can take '
                              . 'a few minutes. If the customer insists it has left their account, ask for the '
                              . 'receipt screenshot and check the session id.',
                ], 'No matching transfer found.');
        }
    }

    public function scanPending72h(): JsonResponse
    {
        try {
            $preferences = EmployerPreference::where('quiz_status', 'completed')
                ->where('quiz_completed_at', '<', now()->subHours(72))
                ->where(function ($q) {
                    $q->where('matching_status', '!=', 'paid')
                      ->orWhereNull('matching_status');
                })
                ->whereDoesntHave('payment', fn($q) => $q->whereIn('status', ['paid', 'completed']))
                ->with('employer:id,name,phone,email')
                ->get();

            return $this->success([
                'count'       => $preferences->count(),
                'preferences' => $preferences,
            ], 'Pending payments 72h+');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan pending payments: ' . $e->getMessage(), 500);
        }
    }

    public function scanSalaryDelayed(): JsonResponse
    {
        try {
            $assignments = MaidAssignment::active()
                ->whereNotNull('salary_amount')
                ->whereHas('salaryPayments', fn($q) => $q->where('status', 'pending')
                    ->where('due_date', '<', now()->subDays(3)))
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count'       => $assignments->count(),
                'assignments' => $assignments,
            ], 'Salary delayed assignments');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan salary delayed: ' . $e->getMessage(), 500);
        }
    }

    public function releaseEscrow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_id' => 'nullable|integer|exists:maid_assignments,id',
            'amount'        => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string|max:5000',
        ]);

        try {
            AgentNote::create([
                'entity_type'    => 'escrow_release',
                'entity_id'      => $validated['assignment_id'] ?? 0,
                'note'           => $validated['notes'] ?? 'Escrow release requested by agent',
                'action_taken'   => 'release_escrow',
                'agent_type'     => request()->agent_api_key->agent_type ?? null,
                'agent_user_id'  => null,
            ]);

            return $this->success([
                'released'       => false,
                'assignment_id'  => $validated['assignment_id'] ?? null,
                'amount'         => $validated['amount'] ?? null,
            ], 'Escrow release logged (stub)');
        } catch (\Throwable $e) {
            return $this->error('Failed to release escrow: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record a matching fee payment that was made outside the system.
     * Used by agents when a payment was verified manually or paid via bank transfer.
     */
    /**
     * Strip an external payment reference down to a comparable core.
     *
     * Agents wrap the same bank reference in different prefixes as they retry —
     * "OPAY_260915020100118268926151", "260915020100118268926151" and
     * "RECON_OPAY_260915020100118268926151" were one transfer recorded three
     * times. The digits are the transfer; everything else is decoration.
     * Returns '' when there is no meaningful digit core to compare, so
     * generated references like "mng_manual_6aaa4fa4eb7ab" never collide.
     */
    private function referenceCore(string $reference): string
    {
        $digits = preg_replace('/\D+/', '', $reference) ?? '';
        return strlen($digits) >= 10 ? $digits : '';
    }

    /**
     * Is this a NIBSS session id?
     *
     * Every Nigerian interbank transfer carries one, the payer sees it on their
     * receipt as "Session ID" or "Transaction Reference", and Flutterwave stores
     * the same string as flw_ref. That makes it the one identifier shared by the
     * customer, the gateway and us — so when we have it we persist it to
     * flw_ref, where a unique index makes recording the same transfer twice
     * impossible at the database level rather than merely unlikely.
     *
     * They are 30 digits and begin with the 6-digit NIBSS institution code
     * (OPay transfers start 100004). We accept 20+ digits to stay tolerant of
     * banks that format them differently.
     */
    private function looksLikeSessionId(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return strlen($digits) >= 20;
    }

    public function recordPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employer_id'   => 'required|integer|exists:users,id',
            'amount'        => 'nullable|integer|min:1000',
            'reference'     => 'nullable|string|max:255',
            'payment_type'  => 'nullable|in:matching_fee,premium_matching,renewal',
            'notes'         => 'nullable|string|max:5000',
        ]);

        try {
            $amount = $validated['amount'] ?? (int) config('settings.matching_fee_amount', 20000);

            // Find or create an employer preference for the payment
            $preference = EmployerPreference::where('employer_id', $validated['employer_id'])
                ->latest()->first();
            if (!$preference) {
                $preference = EmployerPreference::create([
                    'employer_id'     => $validated['employer_id'],
                    'matching_status' => 'paid',
                ]);
            } else if ($preference->matching_status !== 'paid') {
                $preference->update(['matching_status' => 'paid']);
            }

            $paymentType = $validated['payment_type'] ?? 'matching_fee';

            // ---- Duplicate-logging mitigator -------------------------------
            // Agents legitimately re-record when they think a payment did not
            // register, and before the status() fix above they were told
            // exactly that on every check. The result was eight 'paid' rows for
            // one ₦20,000 transfer, and ₦200,000 of revenue that never existed.
            //
            // Two guards. First, the same external reference is the same money,
            // however the agent decorated it — 'OPAY_2609...', '2609...' and
            // 'RECON_OPAY_2609...' were all one transfer. Compare on a
            // normalised core rather than the literal string.
            $incomingRef = $validated['reference'] ?? null;
            if ($incomingRef) {
                $core = $this->referenceCore($incomingRef);
                if ($core !== '') {
                    $existing = MatchingFeePayment::where('employer_id', $validated['employer_id'])
                        ->whereIn('status', ['paid', 'completed'])
                        ->get()
                        ->first(fn ($p) => $this->referenceCore((string) $p->reference) === $core);

                    if ($existing) {
                        Log::info('Duplicate payment record suppressed (same reference core)', [
                            'employer_id' => $validated['employer_id'],
                            'incoming'    => $incomingRef,
                            'existing_id' => $existing->id,
                        ]);
                        return $this->success([
                            'payment_id'  => $existing->id,
                            'employer_id' => $existing->employer_id,
                            'amount'      => $existing->amount,
                            'status'      => $existing->status,
                            'reference'   => $existing->reference,
                            'duplicate'   => true,
                        ], 'Payment already recorded — existing record returned');
                    }
                }
            }

            // Second, this employer may already be settled for this fee. Repeat
            // business is real (a second match later on), so this is not a hard
            // block — but it must be deliberate, via confirm_additional.
            // Scoped to the request, not the employer: a household hiring a
            // second helper owes a second fee, and blocking that would lose real
            // revenue. confirm_additional remains for a genuinely separate
            // payment against the SAME request.
            if (!$request->boolean('confirm_additional')) {
                $settled = MatchingFeePayment::where('employer_id', $validated['employer_id'])
                    ->where('payment_type', $paymentType)
                    ->where('preference_id', $preference->id)
                    ->whereIn('status', ['paid', 'completed'])
                    ->latest()
                    ->first();

                if ($settled) {
                    Log::info('Duplicate payment record suppressed (employer already settled)', [
                        'employer_id' => $validated['employer_id'],
                        'existing_id' => $settled->id,
                    ]);
                    return $this->success([
                        'payment_id'  => $settled->id,
                        'employer_id' => $settled->employer_id,
                        'amount'      => $settled->amount,
                        'status'      => $settled->status,
                        'reference'   => $settled->reference,
                        'paid_at'     => $settled->paid_at,
                        'duplicate'   => true,
                        'hint'        => 'This request is already marked paid for ' . $paymentType
                                       . '. If the employer is hiring an ADDITIONAL helper, that is a separate '
                                       . 'request and a separate fee — open it with new_request=true on '
                                       . 'generate-pwbt rather than recording against this one.',
                    ], 'Payment already recorded — existing record returned');
                }
            }

            // Prefer settling the employer's open pending row over minting a new
            // one, so a manual confirmation closes the PWBT record the customer
            // was actually given rather than leaving it dangling forever.
            $payment = MatchingFeePayment::where('employer_id', $validated['employer_id'])
                ->where('payment_type', $paymentType)
                ->where('status', 'pending')
                ->latest()
                ->first();

            // A session id on the receipt is the transfer's true identity.
            // Persisting it to flw_ref lets the unique index refuse a second
            // record of the same money outright.
            $sessionId = null;
            if ($incomingRef && $this->looksLikeSessionId($incomingRef)) {
                $sessionId = preg_replace('/\D+/', '', $incomingRef);
            }

            if ($payment) {
                $payment->update([
                    'amount'    => $amount,
                    'reference' => $incomingRef ?: $payment->reference,
                    'gateway'   => 'manual',
                    'status'    => 'paid',
                    'paid_at'   => now(),
                    'flw_ref'   => $sessionId ?: $payment->flw_ref,
                ]);
            } else {
                $payment = MatchingFeePayment::create([
                    'preference_id' => $preference->id,
                    'employer_id'   => $validated['employer_id'],
                    'amount'       => $amount,
                    'reference'    => $incomingRef ?: ('mng_manual_' . uniqid()),
                    'gateway'      => 'manual',
                    'status'       => 'paid',
                    'payment_type' => $paymentType,
                    'paid_at'      => now(),
                    'flw_ref'      => $sessionId,
                ]);
            }

            // Log as agent note
            AgentNote::create([
                'entity_type'   => 'payment',
                'entity_id'     => $payment->id,
                'note'          => $validated['notes'] ?? "Manual payment of ₦{$amount} recorded by agent",
                'action_taken'  => 'record_payment',
                'outcome'       => 'completed',
                'agent_type'    => request()->agent_api_key->agent_type ?? null,
                'agent_user_id' => null,
            ]);

            Log::info('Agent manually recorded payment', [
                'payment_id'   => $payment->id,
                'employer_id'  => $validated['employer_id'],
                'amount'       => $amount,
            ]);

            return $this->success([
                'payment_id'    => $payment->id,
                'employer_id'   => $payment->employer_id,
                'amount'        => $payment->amount,
                'status'        => $payment->status,
                'reference'     => $payment->reference,
            ], 'Payment recorded successfully', [], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique violation on flw_ref — this exact transfer is already
            // recorded, possibly against another employer. Surface the existing
            // record rather than a 500; the agent's next step is the same
            // either way.
            $sid = isset($sessionId) ? $sessionId : null;
            if ($sid) {
                $existing = MatchingFeePayment::where('flw_ref', $sid)->first();
                if ($existing) {
                    Log::info('Duplicate payment record refused by flw_ref index', [
                        'session_id' => $sid, 'existing_id' => $existing->id,
                        'attempted_employer' => $validated['employer_id'] ?? null,
                    ]);
                    return $this->success([
                        'payment_id'  => $existing->id,
                        'employer_id' => $existing->employer_id,
                        'amount'      => $existing->amount,
                        'status'      => $existing->status,
                        'reference'   => $existing->reference,
                        'duplicate'   => true,
                        'hint'        => $existing->employer_id === (int) ($validated['employer_id'] ?? 0)
                            ? 'This transfer is already recorded for this customer.'
                            : 'This transfer is already recorded against a DIFFERENT customer (employer '
                              . $existing->employer_id . '). Escalate — do not record it again.',
                    ], 'Transfer already recorded');
                }
            }
            Log::error('Manual payment record failed', [
                'employer_id' => $validated['employer_id'] ?? '?',
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            Log::error('Manual payment record failed', [
                'employer_id' => $validated['employer_id'] ?? '?',
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }
}
