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
            $payment = MatchingFeePayment::where('employer_id', $userId)
                ->where('status', 'completed')
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
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $amount = $validated['amount'] ?? (int) (config('settings.matching_fee_amount', 20000));

            $pwbtService = app(FlutterwavePwbtService::class);
            $result = $pwbtService->generateForUser($user, $amount);

            return $this->success([
                'payment_id'         => $result['payment_id'],
                'tx_ref'             => $result['tx_ref'],
                'amount'             => $result['amount'],
                'currency'           => $result['currency'],
                'account_number'     => $result['account_number'],
                'account_bank'       => $result['account_bank'],
                'account_name'       => $result['account_name'],
                'expires_at'         => $result['expires_at'],
                'expires_in_minutes' => $result['expires_in_minutes'],
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
     * here. Isolation is by tx_ref: only Maids.ng mints "MNG-<userId>-..." refs
     * and stores them in matching_fee_payments, so another brand's transaction
     * simply won't resolve to a row here.
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
        ];

        $payment->refresh();

        return $this->success(array_merge($result, [
            'reference'  => $payment->reference ?? $payment->tx_ref,
            'amount'     => (int) $payment->amount,
            'paid_at'    => $payment->paid_at,
            'expires_at' => $payment->expires_at,
        ]), $messages[$result['status']] ?? 'Status checked.');
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
                ->whereDoesntHave('payment', fn($q) => $q->where('status', 'completed'))
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

            $payment = MatchingFeePayment::create([
                'preference_id' => $preference->id,
                'employer_id'   => $validated['employer_id'],
                'amount'       => $amount,
                'reference'    => $validated['reference'] ?? ('mng_manual_' . uniqid()),
                'gateway'      => 'manual',
                'status'       => 'paid',
                'payment_type' => $validated['payment_type'] ?? 'matching_fee',
                'paid_at'      => now(),
            ]);

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
        } catch (\Throwable $e) {
            Log::error('Manual payment record failed', [
                'employer_id' => $validated['employer_id'] ?? '?',
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }
}
