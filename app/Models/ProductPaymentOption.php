<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductPaymentOption Model
 *
 * Represents payment options for purchasing a product.
 * Types: cash (pay now) or installment (pay later - terms defined separately)
 */
class ProductPaymentOption extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'type',
        'label',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the product that owns the payment option.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if this is a cash payment option.
     */
    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    /**
     * Check if this is an installment payment option.
     */
    public function isInstallment(): bool
    {
        return $this->type === 'installment';
    }

    /**
     * Check if this is a wallet payment option.
     */
    public function isWallet(): bool
    {
        return $this->type === 'wallet';
    }

    /**
     * Get the base price (payment options don't modify price).
     */
    public function getPrice(float $basePrice): float
    {
        return $basePrice;
    }

    /**
     * Generate a display label.
     */
    public function getDisplayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->isCash()) {
            return 'Cash';
        }

        if ($this->isWallet()) {
            return 'Wallet';
        }

        return 'Installment';
    }
}
