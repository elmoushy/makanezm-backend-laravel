<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can get their wallet.
     */
    public function test_user_can_get_wallet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/wallet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'wallet' => [
                        'id',
                        'balance',
                    ],
                    'transactions',
                    'pagination',
                ],
            ]);
    }

    /**
     * Test user can get wallet balance.
     */
    public function test_user_can_get_wallet_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 1000]);

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/balance');

        $response->assertStatus(200)
            ->assertJsonPath('data.balance', '1000.00');
    }

    /**
     * Test user can deposit money to wallet.
     */
    public function test_user_can_deposit_money(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/deposit', [
            'amount' => 500,
            'description' => 'Test deposit',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.wallet.balance', '500.00')
            ->assertJsonPath('data.transaction.type', 'deposit')
            ->assertJsonPath('data.transaction.amount', '500.00');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 500,
        ]);
    }

    /**
     * Test user can withdraw money from wallet.
     */
    public function test_user_can_withdraw_money(): void
    {
        $user = User::factory()->create([
            'bank_iban' => 'SA0380000000608010167519',
            'bank_name' => 'Al Rajhi Bank',
        ]);
        Wallet::create(['user_id' => $user->id, 'balance' => 1000]);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 300,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.wallet.balance', '700.00')
            ->assertJsonPath('data.transaction.type', 'withdrawal')
            ->assertJsonPath('data.transaction.amount', '300.00');
    }

    /**
     * Test cannot withdraw more than balance.
     */
    public function test_cannot_withdraw_more_than_balance(): void
    {
        $user = User::factory()->create([
            'bank_iban' => 'SA0380000000608010167519',
            'bank_name' => 'Al Rajhi Bank',
        ]);
        Wallet::create(['user_id' => $user->id, 'balance' => 100]);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 500,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('data.error.code', 'INSUFFICIENT_BALANCE');
    }

    /**
     * Test cannot withdraw without bank info.
     */
    public function test_cannot_withdraw_without_bank_info(): void
    {
        $user = User::factory()->create([
            'bank_iban' => null,
            'bank_name' => null,
        ]);
        Wallet::create(['user_id' => $user->id, 'balance' => 1000]);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/withdraw', [
            'amount' => 100,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('data.error.code', 'BANK_INFO_REQUIRED');
    }

    /**
     * Test user can get transactions history.
     */
    public function test_user_can_get_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        // Create some transactions
        $wallet->deposit(500, 'First deposit');
        $wallet->deposit(300, 'Second deposit');

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.transactions');
    }

    /**
     * Test unauthenticated user cannot access wallet.
     */
    public function test_unauthenticated_user_cannot_access_wallet(): void
    {
        $response = $this->getJson('/api/v1/wallet');

        $response->assertStatus(401);
    }
}
