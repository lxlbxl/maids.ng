<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\EmployerWallet;
use App\Models\MaidWallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletModelTest extends TestCase
{
    use RefreshDatabase;

    protected $employer;
    protected $maid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRoles();

        $this->employer = User::factory()->create(['role' => 'employer']);
        $this->employer->assignRole('employer');

        $this->maid = User::factory()->create(['role' => 'maid']);
        $this->maid->assignRole('maid');
    }

    // Employer Wallet Tests

    public function test_employer_wallet_can_be_created()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $this->assertDatabaseHas('employer_wallets', [
            'employer_id' => $this->employer->id,
            'balance' => 50000,
        ]);

        $this->assertInstanceOf(EmployerWallet::class, $wallet);
    }

    public function test_employer_wallet_has_sufficient_balance()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $this->assertTrue($wallet->hasSufficientBalance(30000));
        $this->assertTrue($wallet->hasSufficientBalance(50000));
        $this->assertFalse($wallet->hasSufficientBalance(60000));
    }

    public function test_employer_wallet_get_available_balance()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'escrow_balance' => 20000,
            'currency' => 'NGN',
        ]);

        $this->assertEquals(30000, $wallet->getAvailableBalance());
    }

    public function test_employer_wallet_credit_creates_transaction()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 0,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->credit(50000, 'Initial deposit', 1, 'payment');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('credit', $transaction->transaction_type);
        $this->assertEquals(50000, $transaction->amount);

        $wallet->refresh();
        $this->assertEquals(50000, $wallet->balance);
        $this->assertEquals(50000, $wallet->total_deposited);
    }

    public function test_employer_wallet_debit_creates_transaction()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->debit(20000, 'Payment for service', 1, 'service');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('debit', $transaction->transaction_type);
        $this->assertEquals(20000, $transaction->amount);

        $wallet->refresh();
        $this->assertEquals(30000, $wallet->balance);
        $this->assertEquals(20000, $wallet->total_spent);
    }

    public function test_employer_wallet_debit_returns_null_on_insufficient_balance()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 10000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->debit(20000, 'Payment for service');

        $this->assertNull($transaction);
        $wallet->refresh();
        $this->assertEquals(10000, $wallet->balance);
    }

    public function test_employer_wallet_hold_in_escrow()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->holdInEscrow(30000, 'Salary escrow', 1, 'salary');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('escrow_hold', $transaction->transaction_type);

        $wallet->refresh();
        $this->assertEquals(20000, $wallet->balance);
        $this->assertEquals(30000, $wallet->escrow_balance);
    }

    public function test_employer_wallet_release_from_escrow()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 20000,
            'escrow_balance' => 30000,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->releaseFromEscrow(30000, 'Refund from escrow', 1, 'salary');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('escrow_release', $transaction->transaction_type);

        $wallet->refresh();
        $this->assertEquals(50000, $wallet->balance);
        $this->assertEquals(0, $wallet->escrow_balance);
        $this->assertEquals(30000, $wallet->total_refunded);
    }

    public function test_employer_wallet_release_escrow_to_maid()
    {
        $employerWallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 20000,
            'escrow_balance' => 50000,
            'currency' => 'NGN',
        ]);

        $maidWallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 0,
            'currency' => 'NGN',
        ]);

        $transaction = $employerWallet->releaseEscrowToMaid(50000, $this->maid->id, 'Salary payment', 1);

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('escrow_transfer', $transaction->transaction_type);

        $employerWallet->refresh();
        $maidWallet->refresh();

        $this->assertEquals(20000, $employerWallet->balance);
        $this->assertEquals(0, $employerWallet->escrow_balance);
        $this->assertEquals(50000, $maidWallet->balance);
        $this->assertEquals(50000, $maidWallet->total_earned);
    }

    // Maid Wallet Tests

    public function test_maid_wallet_can_be_created()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 30000,
            'currency' => 'NGN',
        ]);

        $this->assertDatabaseHas('maid_wallets', [
            'maid_id' => $this->maid->id,
            'balance' => 30000,
        ]);

        $this->assertInstanceOf(MaidWallet::class, $wallet);
    }

    public function test_maid_wallet_has_sufficient_balance()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 30000,
            'currency' => 'NGN',
        ]);

        $this->assertTrue($wallet->hasSufficientBalance(20000));
        $this->assertFalse($wallet->hasSufficientBalance(40000));
    }

    public function test_maid_wallet_get_available_balance()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 50000,
            'pending_withdrawal' => 20000,
            'currency' => 'NGN',
        ]);

        $this->assertEquals(30000, $wallet->getAvailableBalance());
    }

    public function test_maid_wallet_credit_creates_transaction()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 0,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->credit(50000, 'Salary payment', 1, 'salary_payment');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('credit', $transaction->transaction_type);

        $wallet->refresh();
        $this->assertEquals(50000, $wallet->balance);
        $this->assertEquals(50000, $wallet->total_earned);
    }

    public function test_maid_wallet_debit_creates_transaction()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 50000,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->debit(20000, 'Withdrawal', 1, 'withdrawal');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('withdrawal', $transaction->transaction_type);

        $wallet->refresh();
        $this->assertEquals(30000, $wallet->balance);
        $this->assertEquals(20000, $wallet->total_withdrawn);
    }

    public function test_maid_wallet_request_withdrawal()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 50000,
            'currency' => 'NGN',
        ]);

        $transaction = $wallet->requestWithdrawal(20000, 'Bank transfer request');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('withdrawal_request', $transaction->transaction_type);
        $this->assertEquals('pending', $transaction->status);

        $wallet->refresh();
        $this->assertEquals(20000, $wallet->pending_withdrawal);
    }

    public function test_maid_wallet_approve_withdrawal()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 50000,
            'pending_withdrawal' => 20000,
            'currency' => 'NGN',
        ]);

        $requestTransaction = $wallet->requestWithdrawal(20000, 'Bank transfer request');

        $transaction = $wallet->approveWithdrawal($requestTransaction->id, 'TRX123456');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('withdrawal', $transaction->transaction_type);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals('TRX123456', $transaction->payment_reference);

        $wallet->refresh();
        $this->assertEquals(30000, $wallet->balance);
        $this->assertEquals(0, $wallet->pending_withdrawal);
        $this->assertEquals(20000, $wallet->total_withdrawn);
    }

    public function test_maid_wallet_reject_withdrawal()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 50000,
            'currency' => 'NGN',
        ]);

        $requestTransaction = $wallet->requestWithdrawal(20000, 'Bank transfer request');
        $wallet->refresh();

        $transaction = $wallet->rejectWithdrawal($requestTransaction->id, 'Invalid bank details');

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('failed', $transaction->status);
        $this->assertEquals('Invalid bank details', $transaction->failure_reason);

        $wallet->refresh();
        $this->assertEquals(50000, $wallet->balance);
        $this->assertEquals(0, $wallet->pending_withdrawal);
    }

    public function test_maid_wallet_calculate_next_salary_date()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'salary_day' => 28,
            'currency' => 'NGN',
        ]);

        $nextDate = $wallet->calculateNextSalaryDate();

        $this->assertInstanceOf(\Carbon\Carbon::class, $nextDate);
        $this->assertEquals(28, $nextDate->day);
    }

    public function test_maid_wallet_update_next_salary_date()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'salary_day' => 28,
            'currency' => 'NGN',
        ]);

        $wallet->updateNextSalaryDate();
        $wallet->refresh();

        $this->assertNotNull($wallet->next_salary_due_date);
        $this->assertEquals(28, $wallet->next_salary_due_date->day);
    }

    public function test_maid_wallet_has_complete_bank_details()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'bank_name' => 'First Bank',
            'account_number' => '1234567890',
            'account_name' => 'Jane Doe',
            'currency' => 'NGN',
        ]);

        $this->assertTrue($wallet->hasCompleteBankDetails());
    }

    public function test_maid_wallet_incomplete_bank_details()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'bank_name' => 'First Bank',
            'account_number' => null,
            'account_name' => 'Jane Doe',
            'currency' => 'NGN',
        ]);

        $this->assertFalse($wallet->hasCompleteBankDetails());
    }

    public function test_maid_wallet_get_bank_details_formatted()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'bank_name' => 'First Bank',
            'account_number' => '1234567890',
            'account_name' => 'Jane Doe',
            'currency' => 'NGN',
        ]);

        $this->assertEquals('First Bank - 1234567890 (Jane Doe)', $wallet->getBankDetailsFormatted());
    }

    public function test_maid_wallet_get_bank_details_formatted_when_empty()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'currency' => 'NGN',
        ]);

        $this->assertEquals('No bank details provided', $wallet->getBankDetailsFormatted());
    }

    // Wallet Relationships

    public function test_employer_wallet_has_employer_relationship()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'currency' => 'NGN',
        ]);

        $this->assertInstanceOf(User::class, $wallet->employer);
        $this->assertEquals($this->employer->id, $wallet->employer->id);
    }

    public function test_maid_wallet_has_maid_relationship()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 30000,
            'currency' => 'NGN',
        ]);

        $this->assertInstanceOf(User::class, $wallet->maid);
        $this->assertEquals($this->maid->id, $wallet->maid->id);
    }

    public function test_employer_wallet_has_transactions_relationship()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 50000,
            'currency' => 'NGN',
        ]);

        $wallet->credit(10000, 'Test deposit');
        $wallet->credit(20000, 'Another deposit');

        $this->assertEquals(2, $wallet->transactions()->count());
    }

    public function test_maid_wallet_has_transactions_relationship()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 30000,
            'currency' => 'NGN',
        ]);

        $wallet->credit(15000, 'Salary payment');
        $wallet->credit(15000, 'Bonus payment');

        $this->assertEquals(2, $wallet->transactions()->count());
    }
}
