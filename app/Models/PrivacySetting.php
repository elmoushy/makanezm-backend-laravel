<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacySetting extends Model
{
    protected $fillable = [
        'hero_image',
        'title_ar',
        'title_en',
        'intro_ar',
        'intro_en',
        'terms_title_ar',
        'terms_title_en',
        'terms_content_ar',
        'terms_content_en',
        'privacy_title_ar',
        'privacy_title_en',
        'privacy_content_ar',
        'privacy_content_en',
        'operation_title_ar',
        'operation_title_en',
        'operation_content_ar',
        'operation_content_en',
        'copyright_title_ar',
        'copyright_title_en',
        'copyright_content_ar',
        'copyright_content_en',
    ];

    protected $casts = [
        'intro_ar' => 'array',
        'intro_en' => 'array',
    ];

    /**
     * Get the singleton instance of privacy settings.
     */
    public static function getInstance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'title_ar' => 'الشروط والخصوصية',
                'title_en' => 'Terms & Privacy',
            ]
        );
    }
}
