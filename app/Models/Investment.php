<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * Investment Model
 *
 * Tracks user investments from resale/investment purchases.
 * Stores snapshot of resale plan at checkout time to ensure admin changes
 * to product resale plans don't affect existing user investments.
 */
class Investment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'order_item_id',
        'product_id',
        'invested_amount',
        'expected_return',
        'profit_amount',
        'plan_months',
        'plan_profit_percentage',
        'plan_label',
        'investment_date',
        'maturity_date',
        'paid_out_at',
        'paid_by',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invested_amount' => 'decimal:2',
            'expected_return' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'plan_months' => 'integer',
            'plan_profit_percentage' => 'decimal:2',
            'investment_date' => 'date',
            'maturity_date' => 'date',
            'paid_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_MATURED = 'matured';
    public const STATUS_PAID_OUT = 'paid_out';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the investment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order associated with the investment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the order item associated with the investment.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the product associated with the investment.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the admin user who marked this investment as paid.
     */
    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Check if investment has matured.
     */
    public function hasMatured(): bool
    {
        return Carbon::now()->gte($this->maturity_date);
    }

    /**
     * Check if investment is active and can mature.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if investment can be paid out.
     */
    public function canBePaidOut(): bool
    {
        return $this->status === self::STATUS_MATURED && $this->paid_out_at === null;
    }

    /**
     * Mark investment as active (after order confirmation).
     */
    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Mark investment as matured.
     */
    public function markAsMatured(): void
    {
        if ($this->hasMatured() && $this->isActive()) {
            $this->update(['status' => self::STATUS_MATURED]);
        }
    }

    /**
     * Mark investment as paid out.
     *
     * @param int|null $adminUserId The admin user ID who marked it paid
     */
    public function markAsPaidOut(?int $adminUserId = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID_OUT,
            'paid_out_at' => Carbon::now(),
            'paid_by' => $adminUserId,
        ]);
    }

    /**
     * Cancel the investment with a reason.
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => Carbon::now(),
        ]);
    }

    /**
     * Get days until maturity.
     */
    public function daysUntilMaturity(): int
    {
        return max(0, Carbon::now()->diffInDays($this->maturity_date, false));
    }

    /**
     * Scope: Active investments
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Matured investments ready for payout
     */
    public function scopeReadyForPayout($query)
    {
        return $query->where('status', self::STATUS_MATURED)
            ->whereNull('paid_out_at');
    }

    /**
     * Scope: Investments that should be marked as matured
     */
    public function scopeShouldMature($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('maturity_date', '<=', Carbon::now());
    }

    /**
     * Scope: Paid out investments
     */
    public function scopePaidOut($query)
    {
        return $query->where('status', self::STATUS_PAID_OUT);
    }

    /**
     * Scope: Pending payout (matured but not yet paid)
     */
    public function scopePendingPayout($query)
    {
        return $query->where('status', self::STATUS_MATURED)
            ->whereNull('paid_out_at');
    }
}
