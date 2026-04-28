<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\EmployerWallet;
use App\Models\MaidWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
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

    public function test_employer_can_view_wallet_balance()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 5000,
            'escrow_balance' => 0,
            'currency' => 'NGN'
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson('/api/v1/wallets');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 5000
                ]
            ]);
    }

    public function test_maid_can_view_earnings()
    {
        $wallet = MaidWallet::create([
            'maid_id' => $this->maid->id,
            'balance' => 10000,
            'pending_balance' => 2000,
            'currency' => 'NGN'
        ]);

        $response = $this->actingAs($this->maid)
            ->getJson('/api/v1/wallets');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 10000
                ]
            ]);
    }
}
