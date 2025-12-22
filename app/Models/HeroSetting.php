<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_ar',
        'description1',
        'description1_ar',
        'description2',
        'description2_ar',
        'image',
        'image_mime_type',
        'service_image',
        'service_image_mime_type',
        'products_cover_image',
        'products_cover_image_mime_type',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Binary image data must be hidden to prevent UTF-8 encoding errors in JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'image',
        'service_image',
        'products_cover_image',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active hero settings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the current active hero setting.
     */
    public static function getActive()
    {
        return static::active()->first();
    }
}
