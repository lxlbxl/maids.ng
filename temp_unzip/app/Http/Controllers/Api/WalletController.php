<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployerWallet;
use App\Models\MaidWallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
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
            return response()->json([
                'success' => false,
                'message' => 'Wallet not available for this user type.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $wallet,
                'balance' => $balance,
                'escrow_balance' => $escrow,
                'available_balance' => $available,
                'balance_formatted' => '₦' . number_format($balance, 2),
            ],
        ]);
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
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Credit wallet (deposit).
     */
    public function credit(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers can deposit funds.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|string|in:bank_transfer,card,ussd',
            'reference' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->walletService->creditEmployerWallet(
            $user->id,
            $request->amount,
            'Deposit via ' . $request->payment_method,
            null,
            $request->payment_method
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Wallet credited successfully.',
                'data' => [
                    'transaction' => $result,
                    'new_balance' => $this->walletService->getEmployerBalance($user->id),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to credit wallet.',
        ], 400);
    }

    /**
     * Request withdrawal.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'maid') {
            return response()->json([
                'success' => false,
                'message' => 'Only maids can request withdrawals.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $maidWallet = $this->walletService->getOrCreateMaidWallet($user->id);

        if (!$maidWallet->hasSufficientBalance($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance.',
                'data' => [
                    'requested' => $request->amount,
                    'available' => $maidWallet->balance,
                ],
            ], 422);
        }

        $result = $this->walletService->requestMaidWithdrawal(
            $user->id,
            $request->amount,
            'Withdrawal request'
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully. Pending admin approval.',
                'data' => [
                    'transaction' => $result,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to process withdrawal request.',
        ], 400);
    }

    /**
     * Get withdrawal requests (for maids).
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'maid') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $query = WalletTransaction::forMaid($user->id)
            ->where('type', 'withdrawal');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
        ]);
    }

    /**
     * Get escrow balance (for employers).
     */
    public function escrowBalance(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Only employers have escrow accounts.',
            ], 403);
        }

        $balance = $this->walletService->getEmployerBalance($user->id);

        return response()->json([
            'success' => true,
            'data' => $balance,
        ]);
    }

    /**
     * Get wallet statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
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

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
