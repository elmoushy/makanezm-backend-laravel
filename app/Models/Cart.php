<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cart Model
 *
 * Represents a cart item for a user.
 * Each cart entry links a user to a product with a quantity.
 */
class Cart extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'purchase_type',
        'resale_plan_id',
        'company_id',
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
            'resale_plan_id' => 'integer',
            'company_id' => 'integer',
        ];
    }

    /**
     * Get the user that owns the cart item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product in the cart.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the selected resale plan (if purchase type is 'resale').
     */
    public function resalePlan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductResalePlan::class, 'resale_plan_id');
    }

    /**
     * Get the selected company for delivery.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * Calculate the total price for this cart item.
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->quantity * ($this->product->price ?? 0);
    }
}
