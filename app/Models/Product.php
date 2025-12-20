<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product Model
 *
 * Represents a product available for purchase.
 * Each product can have:
 * - Multiple images (one main + sub-images)
 * - Payment options (cash, installments)
 * - Resale plans (for resale after purchase)
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'type',
        'price',
        'in_stock',
        'stock_quantity',
        'max_stock',
        'main_image',
        'main_image_mime_type',
        'is_active',
        'is_featured',
        'display_order',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Binary image data must be hidden to prevent UTF-8 encoding errors in JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'main_image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'in_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'max_stock' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /**
     * Get the images for the product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the payment options for the product.
     */
    public function paymentOptions(): HasMany
    {
        return $this->hasMany(ProductPaymentOption::class)->where('is_active', true);
    }

    /**
     * Get all payment options including inactive.
     */
    public function allPaymentOptions(): HasMany
    {
        return $this->hasMany(ProductPaymentOption::class);
    }

    /**
     * Get the resale plans for the product.
     */
    public function resalePlans(): HasMany
    {
        return $this->hasMany(ProductResalePlan::class)->where('is_active', true)->orderBy('months');
    }

    /**
     * Get all resale plans including inactive.
     */
    public function allResalePlans(): HasMany
    {
        return $this->hasMany(ProductResalePlan::class)->orderBy('months');
    }

    /**
     * Get the favorites for this product.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(ProductFavorite::class);
    }

    /**
     * Get users who favorited this product.
     */
    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'product_favorites')->withTimestamps();
    }

    /**
     * Check if product is favorited by a user.
     */
    public function isFavoritedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->favorites()->where('user_id', $user->id)->exists();
    }

    /**
     * Scope to get only active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only in-stock products.
     */
    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if product is available for purchase.
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->in_stock;
    }

    /**
     * Calculate the total price with installment percentage.
     */
    public function calculateInstallmentPrice(float $percentage): float
    {
        return $this->price * (1 + ($percentage / 100));
    }

    /**
     * Calculate the expected return for a resale plan.
     */
    public function calculateResaleReturn(float $profitPercentage): float
    {
        return $this->price * (1 + ($profitPercentage / 100));
    }

    /**
     * Check if product has stock available.
     */
    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    /**
     * Decrease stock quantity.
     */
    public function decreaseStock(int $quantity = 1): bool
    {
        if (! $this->hasStock($quantity)) {
            return false;
        }

        $this->stock_quantity -= $quantity;
        $this->in_stock = $this->stock_quantity > 0;
        $this->save();

        return true;
    }

    /**
     * Increase stock quantity.
     */
    public function increaseStock(int $quantity = 1): void
    {
        $this->stock_quantity += $quantity;
        $this->in_stock = true;
        $this->save();
    }

    /**
     * Update stock quantity and auto-set in_stock status.
     */
    public function updateStock(int $quantity): void
    {
        $this->stock_quantity = max(0, $quantity);
        $this->in_stock = $this->stock_quantity > 0;
        $this->save();
    }

    /**
     * Boot method to automatically manage in_stock status.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            // Auto-set in_stock based on stock_quantity
            if ($product->stock_quantity <= 0) {
                $product->in_stock = false;
            }
        });
    }
}
