<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutSetting extends Model
{
    protected $fillable = [
        'hero_image',
        'title_ar',
        'title_en',
        'description1_ar',
        'description1_en',
        'description2_ar',
        'description2_en',
        'mission_title_ar',
        'mission_title_en',
        'mission_description_ar',
        'mission_description_en',
        'values_title_ar',
        'values_title_en',
        'values_description_ar',
        'values_description_en',
        'vision_title_ar',
        'vision_title_en',
        'vision_description_ar',
        'vision_description_en',
    ];

    /**
     * Get the singleton instance or create default one.
     */
    public static function getInstance(): self
    {
        $instance = self::first();

        if (! $instance) {
            $instance = self::create([
                'title_ar' => 'من نحن',
                'title_en' => 'About Us',
            ]);
        }

        return $instance;
    }
}
