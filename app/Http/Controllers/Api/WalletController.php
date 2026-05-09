<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Wallet\{DepositRequest, WithdrawalRequest};
use App\Models\EmployerWallet;
use App\Models\MaidWallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class WalletController extends ApiController
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get wallet balance and details.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role === 'employer') {
            $wallet = $this->walletService->getOrCreateEmployerWallet($user->id);
            $balance = $wallet->balance;
            $escrow = $wallet->escrow_balance;
            $available = $wallet->getAvailableBalance();
        } elseif ($user->role === 'maid') {
            $wallet = $this->walletService->getOrCreateMaidWallet($user->id);
            $balance = $wallet->balance;
            $escrow = 0;
            $available = $wallet->getAvailableBalance();
        } else {
            return $this->forbidden('Wallet not available for this user type.');
        }

        return $this->success([
            'wallet' => $wallet,
            'balance' => $balance,
            'escrow_balance' => $escrow,
            'available_balance' => $available,
            'balance_formatted' => '₦' . number_format($balance, 2),
        ], 'Wallet details retrieved successfully');
    }

    /**
     * Get wallet transactions.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role === 'employer') {
            $wallet = $this->walletService->getOrCreateEmployerWallet($user->id);
            $query = WalletTransaction::forEmployer($user->id);
        } elseif ($user->role === 'maid') {
            $wallet = $this->walletService->getOrCreateMaidWallet($user->id);
            $query = WalletTransaction::forMaid($user->id);
        } else {
            return $this->success([], 'No transactions found for this user type');
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($transactions, 'Transactions retrieved successfully');
    }

    /**
     * Credit wallet (deposit).
     */
    public function credit(DepositRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $result = $this->walletService->creditEmployerWallet(
            $user->id,
            $validated['amount'],
            'Deposit via ' . $validated['payment_method'],
            $validated['reference'] ?? null,
            $validated['payment_method']
        );

        if ($result) {
            return $this->success([
                'transaction' => $result,
                'new_balance' => $this->walletService->getEmployerBalance($user->id),
            ], 'Wallet credited successfully.');
        }

        return $this->error('Failed to credit wallet.', Response::HTTP_BAD_REQUEST);
    }

    /**
     * Request withdrawal.
     */
    public function withdraw(WithdrawalRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $maidWallet = $this->walletService->getOrCreateMaidWallet($user->id);

        if (!$maidWallet->hasSufficientBalance($validated['amount'])) {
            return $this->error('Insufficient balance.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'requested' => $validated['amount'],
                'available' => $maidWallet->balance,
            ], 'INSUFFICIENT_BALANCE');
        }

        $result = $this->walletService->requestMaidWithdrawal(
            $user->id,
            $validated['amount'],
            'Withdrawal request'
        );

        if ($result) {
            return $this->success([
                'transaction' => $result,
            ], 'Withdrawal request submitted successfully. Pending admin approval.');
        }

        return $this->error('Failed to process withdrawal request.', Response::HTTP_BAD_REQUEST);
    }

    /**
     * Get withdrawal requests (for maids).
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'maid') {
            return $this->forbidden();
        }

        $query = WalletTransaction::forMaid($user->id)
            ->where('type', 'withdrawal');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($withdrawals, 'Withdrawals retrieved successfully');
    }

    /**
     * Get escrow balance (for employers).
     */
    public function escrowBalance(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return $this->forbidden('Only employers have escrow accounts.');
        }

        $balance = $this->walletService->getEmployerBalance($user->id);

        return $this->success($balance, 'Escrow balance retrieved successfully');
    }

    /**
     * Get wallet statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $stats = [
            'total_employer_wallets' => EmployerWallet::count(),
            'total_maid_wallets' => MaidWallet::count(),
            'total_employer_balance' => EmployerWallet::sum('balance'),
            'total_maid_balance' => MaidWallet::sum('balance'),
            'total_escrow' => EmployerWallet::sum('escrow_balance'),
            'total_transactions' => WalletTransaction::count(),
            'total_deposits' => WalletTransaction::where('type', 'deposit')
                ->where('status', 'completed')
                ->sum('amount'),
            'total_withdrawals' => WalletTransaction::where('type', 'withdrawal')
                ->where('status', 'completed')
                ->sum('amount'),
            'pending_withdrawals' => WalletTransaction::where('type', 'withdrawal')
                ->where('status', 'pending')
                ->count(),
            'pending_withdrawals_amount' => WalletTransaction::where('type', 'withdrawal')
                ->where('status', 'pending')
                ->sum('amount'),
        ];

        return $this->success($stats, 'Wallet statistics retrieved successfully');
    }
}
