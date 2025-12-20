<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * OrderItem Model
 *
 * Represents an item in an order.
 * Supports two purchase types:
 * - wallet: Direct purchase with shipping
 * - resale: Investment that returns with profit after specified period
 *
 * For resale orders, stores a snapshot of the resale plan at checkout time
 * to ensure admin changes don't affect existing user investments.
 */
class OrderItem extends Model
{
    /**
     * Purchase type constants
     */
    public const PURCHASE_TYPE_WALLET = 'wallet';

    public const PURCHASE_TYPE_RESALE = 'resale';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'company_id',
        'quantity',
        'unit_price',
        'total_price',
        'purchase_type',
        'resale_plan_id',
        'resale_months',
        'resale_profit_percentage',
        'resale_expected_return',
        'resale_plan_snapshot',
        'investment_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'resale_months' => 'integer',
            'resale_profit_percentage' => 'decimal:2',
            'resale_expected_return' => 'decimal:2',
            'resale_plan_snapshot' => 'array',
        ];
    }

    /**
     * Get the order that owns the item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for the item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the company associated with the item.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the resale plan for the item (reference only - use snapshot for calculations).
     */
    public function resalePlan(): BelongsTo
    {
        return $this->belongsTo(ProductResalePlan::class, 'resale_plan_id');
    }

    /**
     * Get the investment record for this item.
     */
    public function investment(): HasOne
    {
        return $this->hasOne(Investment::class);
    }

    /**
     * Check if item is a resale/investment item.
     */
    public function isResale(): bool
    {
        return $this->purchase_type === self::PURCHASE_TYPE_RESALE;
    }

    /**
     * Check if item is a direct wallet purchase.
     */
    public function isWalletPurchase(): bool
    {
        return $this->purchase_type === self::PURCHASE_TYPE_WALLET;
    }

    /**
     * Calculate expected return for resale item.
     * Uses the snapshot percentage to ensure consistency.
     */
    public function calculateResaleReturn(): float
    {
        if (! $this->isResale()) {
            return 0;
        }

        // Use snapshot percentage if available, fallback to stored percentage
        $profitPercentage = $this->resale_plan_snapshot['profit_percentage']
            ?? $this->resale_profit_percentage
            ?? 0;

        return $this->total_price + ($this->total_price * $profitPercentage / 100);
    }

    /**
     * Get the profit amount for resale item.
     */
    public function getProfitAmount(): float
    {
        if (! $this->isResale()) {
            return 0;
        }

        return $this->calculateResaleReturn() - $this->total_price;
    }
}
