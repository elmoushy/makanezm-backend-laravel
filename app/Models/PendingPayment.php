<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PendingPayment Model
 *
 * Stores checkout data temporarily while user completes payment on MyFatoorah.
 * Once payment is confirmed, the actual order is created and this record is marked as completed.
 *
 * @property int $id
 * @property int $user_id
 * @property string $payment_id
 * @property string|null $invoice_url
 * @property float $amount
 * @property array $checkout_data
 * @property string $status
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $paid_at
 * @property array|null $payment_response
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PendingPayment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'payment_id',
        'invoice_url',
        'amount',
        'checkout_data',
        'status',
        'expires_at',
        'paid_at',
        'payment_response',
    ];

    protected function casts(): array
    {
        return [
            'checkout_data' => 'array',
            'payment_response' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the pending payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the payment has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark the payment as completed.
     */
    public function markAsCompleted(array $paymentResponse): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'paid_at' => now(),
            'payment_response' => $paymentResponse,
        ]);
    }

    /**
     * Mark the payment as failed.
     */
    public function markAsFailed(array $paymentResponse = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'payment_response' => $paymentResponse,
        ]);
    }

    /**
     * Scope to get pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get non-expired pending payments.
     */
    public function scopeActive($query)
    {
        return $query->pending()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
