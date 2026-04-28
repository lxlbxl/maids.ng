<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployerWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'balance',
        'escrow_balance',
        'total_deposited',
        'total_spent',
        'total_refunded',
        'currency',
        'timezone',
        'is_active',
        'last_activity_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'escrow_balance' => 'decimal:2',
        'total_deposited' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_refunded' => 'decimal:2',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Get the employer that owns the wallet.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'employer_id', 'employer_id')
            ->where('wallet_type', 'employer');
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get available balance (excluding escrow).
     */
    public function getAvailableBalance(): float
    {
        return $this->balance - $this->escrow_balance;
    }

    /**
     * Credit the wallet.
     */
    public function credit(float $amount, string $description, ?int $referenceId = null, string $referenceType = ''): WalletTransaction
    {
        $balanceBefore = $this->balance;
        $this->balance += $amount;
        $this->total_deposited += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'employer',
            'employer_id' => $this->employer_id,
            'transaction_type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Debit the wallet.
     */
    public function debit(float $amount, string $description, ?int $referenceId = null, string $referenceType = ''): ?WalletTransaction
    {
        if (!$this->hasSufficientBalance($amount)) {
            return null;
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->total_spent += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'employer',
            'employer_id' => $this->employer_id,
            'transaction_type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Hold amount in escrow.
     */
    public function holdInEscrow(float $amount, string $description, ?int $referenceId = null, string $referenceType = ''): ?WalletTransaction
    {
        if (!$this->hasSufficientBalance($amount)) {
            return null;
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->escrow_balance += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'employer',
            'employer_id' => $this->employer_id,
            'transaction_type' => 'escrow_hold',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Release amount from escrow (credit back to balance).
     */
    public function releaseFromEscrow(float $amount, string $description, ?int $referenceId = null, string $referenceType = ''): ?WalletTransaction
    {
        if ($this->escrow_balance < $amount) {
            return null;
        }

        $balanceBefore = $this->balance;
        $this->escrow_balance -= $amount;
        $this->balance += $amount;
        $this->total_refunded += $amount;
        $this->last_activity_at = now();
        $this->save();

        return WalletTransaction::create([
            'wallet_type' => 'employer',
            'employer_id' => $this->employer_id,
            'transaction_type' => 'escrow_release',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Release from escrow and transfer to maid (for salary payments).
     */
    public function releaseEscrowToMaid(float $amount, int $maidId, string $description, ?int $referenceId = null): ?WalletTransaction
    {
        if ($this->escrow_balance < $amount) {
            return null;
        }

        $balanceBefore = $this->balance;
        $this->escrow_balance -= $amount;
        $this->last_activity_at = now();
        $this->save();

        // Credit maid's wallet
        $maidWallet = MaidWallet::firstOrCreate(
            ['maid_id' => $maidId],
            [
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'currency' => $this->currency,
                'is_active' => true,
            ]
        );

        $maidWallet->credit($amount, $description, $referenceId, 'salary_payment');

        return WalletTransaction::create([
            'wallet_type' => 'employer',
            'employer_id' => $this->employer_id,
            'maid_id' => $maidId,
            'transaction_type' => 'escrow_transfer',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => 'salary_payment',
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
