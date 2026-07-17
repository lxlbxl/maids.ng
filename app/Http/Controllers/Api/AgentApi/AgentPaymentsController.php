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
    public function status(int $userId): JsonResponse
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
            return $this->error('Failed to generate payment account: ' . $e->getMessage(), 500);
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
}
