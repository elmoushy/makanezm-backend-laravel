<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order Model
 *
 * Represents an order placed by a user.
 * Two types:
 * - sale: Product is delivered to user
 * - resale: Money is invested, user receives money + profit after period ends
 */
class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'type',
        'status',
        'subtotal',
        'total_amount',
        'shipping_name',
        'shipping_phone',
        'shipping_city',
        'shipping_address',
        'notes',
        'resale_return_date',
        'resale_expected_return',
        'resale_returned',
        'resale_returned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'resale_expected_return' => 'decimal:2',
            'resale_return_date' => 'date',
            'resale_returned' => 'boolean',
            'resale_returned_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    /**
     * Generate a unique order number.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = date('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "ORD-{$prefix}-{$random}";
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Check if order is a sale type (includes wallet items in mixed orders).
     */
    public function isSale(): bool
    {
        return $this->type === 'sale' || $this->type === 'mixed';
    }

    /**
     * Check if order is a resale type (includes resale items in mixed orders).
     */
    public function isResale(): bool
    {
        return $this->type === 'resale' || $this->type === 'mixed';
    }

    /**
     * Check if order is purely sale type (no resale items).
     */
    public function isPureSale(): bool
    {
        return $this->type === 'sale';
    }

    /**
     * Check if order is purely resale type (no sale items).
     */
    public function isPureResale(): bool
    {
        return $this->type === 'resale';
    }

    /**
     * Check if order is mixed type (has both sale and resale items).
     */
    public function isMixed(): bool
    {
        return $this->type === 'mixed';
    }

    /**
     * Check if order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Check if resale return is due.
     */
    public function isResaleReturnDue(): bool
    {
        if (! $this->isResale() || $this->resale_returned) {
            return false;
        }

        return $this->resale_return_date && $this->resale_return_date->isPast();
    }

    /**
     * Mark resale as returned.
     */
    public function markResaleReturned(): void
    {
        $this->update([
            'resale_returned' => true,
            'resale_returned_at' => now(),
            'status' => 'completed',
        ]);
    }

    /**
     * Scope for sale orders.
     */
    public function scopeSale($query)
    {
        return $query->where('type', 'sale');
    }

    /**
     * Scope for resale orders.
     */
    public function scopeResale($query)
    {
        return $query->where('type', 'resale');
    }

    /**
     * Scope for pending resale returns.
     */
    public function scopePendingResaleReturns($query)
    {
        return $query->where('type', 'resale')
            ->where('resale_returned', false)
            ->where('resale_return_date', '<=', now());
    }
}
