<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Wallet Model
 *
 * Represents a user's wallet for storing money.
 * Used for payments and receiving resale returns.
 */
class Wallet extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'balance',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions for the wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Deposit money into the wallet.
     */
    public function deposit(float $amount, string $description = null, string $referenceType = null, int $referenceId = null, array $metadata = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $metadata) {
            $this->increment('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'deposit',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description ?? 'Deposit',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Withdraw money from the wallet.
     *
     * @throws \Exception if insufficient balance
     */
    public function withdraw(float $amount, string $description = null, string $referenceType = null, int $referenceId = null, array $metadata = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $metadata) {
            if ($this->balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            $this->decrement('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'withdrawal',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description ?? 'Withdrawal',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Pay for an order from the wallet.
     *
     * @throws \Exception if insufficient balance
     */
    public function payForOrder(float $amount, int $orderId, string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $orderId, $description) {
            if ($this->balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            $this->decrement('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'payment',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description ?? 'Order payment',
                'reference_type' => 'Order',
                'reference_id' => $orderId,
            ]);
        });
    }

    /**
     * Receive resale return to the wallet.
     */
    public function receiveResaleReturn(float $amount, int $orderId, string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $orderId, $description) {
            $this->increment('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'resale_return',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description ?? 'Resale return',
                'reference_type' => 'Order',
                'reference_id' => $orderId,
            ]);
        });
    }

    /**
     * Refund money to the wallet.
     */
    public function refund(float $amount, int $orderId, string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $orderId, $description) {
            $this->increment('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'refund',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description ?? 'Refund',
                'reference_type' => 'Order',
                'reference_id' => $orderId,
            ]);
        });
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
