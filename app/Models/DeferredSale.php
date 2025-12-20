<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeferredSale Model
 *
 * Represents a deferred sale request where a user wants to sell a product
 * at a different price than the original, with the profit difference going to them.
 */
class DeferredSale extends Model
{
    /** @use HasFactory<\Database\Factories\DeferredSaleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'original_price',
        'requested_price',
        'profit_amount',
        'profit_percentage',
        'notes',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'requested_price' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'profit_percentage' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who submitted the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product for this deferred sale.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the admin who reviewed the request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Calculate profit percentage from prices.
     */
    public static function calculateProfit(float $originalPrice, float $requestedPrice): array
    {
        $profitAmount = $originalPrice - $requestedPrice;
        $profitPercentage = $originalPrice > 0 ? ($profitAmount / $originalPrice) * 100 : 0;

        return [
            'profit_amount' => round($profitAmount, 2),
            'profit_percentage' => round($profitPercentage, 2),
        ];
    }
}
