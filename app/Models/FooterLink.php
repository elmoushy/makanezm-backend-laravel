<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FooterLink extends Model
{
    /** @use HasFactory<\Database\Factories\FooterLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'platform',
        'url',
    ];

    /**
     * Get all links as a key-value map (platform => url).
     */
    public static function getLinksMap(): array
    {
        return self::pluck('url', 'platform')->toArray();
    }
}
