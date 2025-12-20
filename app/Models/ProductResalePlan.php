<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductResalePlan Model
 *
 * Represents resale plans for a product after purchase.
 * Users can resell products for profit after a certain period.
 */
class ProductResalePlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'months',
        'profit_percentage',
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
            'months' => 'integer',
            'profit_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the product that owns the resale plan.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the expected return amount.
     */
    public function calculateExpectedReturn(float $basePrice): float
    {
        return $basePrice * (1 + ($this->profit_percentage / 100));
    }

    /**
     * Calculate the profit amount.
     */
    public function calculateProfit(float $basePrice): float
    {
        return $basePrice * ($this->profit_percentage / 100);
    }

    /**
     * Get the maturity date from a given start date.
     */
    public function getMaturityDate(?\DateTimeInterface $startDate = null): \Carbon\Carbon
    {
        $start = $startDate ? \Carbon\Carbon::parse($startDate) : now();

        return $start->addMonths($this->months);
    }

    /**
     * Generate a display label.
     */
    public function getDisplayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return sprintf('%d Months (+%s%%)', $this->months, rtrim(rtrim($this->profit_percentage, '0'), '.'));
    }
}
