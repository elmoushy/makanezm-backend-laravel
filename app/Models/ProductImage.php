<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductImage Model
 *
 * Represents a sub-image for a product.
 */
class ProductImage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'image',
        'mime_type',
        'sort_order',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Binary image data must be hidden to prevent UTF-8 encoding errors in JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the product that owns the image.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
