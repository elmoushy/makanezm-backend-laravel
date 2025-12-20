<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    /** @use HasFactory<\Database\Factories\DiscountCodeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'discount_percent',
        'is_active',
        'valid_from',
        'valid_until',
        'usage_limit',
        'times_used',
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
            'valid_from' => 'date',
            'valid_until' => 'date',
            'discount_percent' => 'integer',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
        ];
    }

    /**
     * Check if the discount code is currently valid.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now()->toDateString();

        if ($this->valid_from && $now < $this->valid_from->toDateString()) {
            return false;
        }

        if ($this->valid_until && $now > $this->valid_until->toDateString()) {
            return false;
        }

        if ($this->usage_limit && $this->times_used >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Increment the usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }
}
