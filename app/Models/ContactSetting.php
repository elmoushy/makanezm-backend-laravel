<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSetting extends Model
{
    protected $fillable = [
        'title_ar',
        'title_en',
        'description1_ar',
        'description1_en',
        'description2_ar',
        'description2_en',
    ];

    /**
     * Get the singleton instance of contact settings
     */
    public static function getInstance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'title_ar' => 'تواصل معنا',
                'title_en' => 'Contact Us',
                'description1_ar' => 'نحن هنا لمساعدتك! إذا كان لديك أي استفسار أو تحتاج إلى دعم، لا تتردد في التواصل معنا.',
                'description1_en' => 'We are here to help! If you have any questions or need support, don\'t hesitate to contact us.',
                'description2_ar' => 'فريقنا متاح للرد على جميع استفساراتك وتقديم أفضل تجربة لك.',
                'description2_en' => 'Our team is available to answer all your inquiries and provide you with the best experience.',
            ]
        );
    }
}
