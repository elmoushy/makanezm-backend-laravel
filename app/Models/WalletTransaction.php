<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletTransaction Model
 *
 * Tracks all wallet transactions: deposits, withdrawals, payments, resale returns, refunds.
 */
class WalletTransaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Check if transaction is a credit (increases balance).
     */
    public function isCredit(): bool
    {
        return in_array($this->type, ['deposit', 'resale_return', 'refund']);
    }

    /**
     * Check if transaction is a debit (decreases balance).
     */
    public function isDebit(): bool
    {
        return in_array($this->type, ['withdrawal', 'payment']);
    }

    /**
     * Get the signed amount (positive for credit, negative for debit).
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isCredit() ? $this->amount : -$this->amount;
    }
}
