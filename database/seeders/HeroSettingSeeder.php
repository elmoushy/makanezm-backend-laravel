<?php

namespace Database\Seeders;

use App\Models\HeroSetting;
use Illuminate\Database\Seeder;

class HeroSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HeroSetting::firstOrCreate(
            ['id' => 1],
            [
                'title' => 'Run your products now with flexible operation options and guaranteed profit.',
                'title_ar' => 'شغل منتجاتك الآن بخيارات تشغيل مرنة و مكسب مضمون.',
                'description1' => 'We are a modern, organized company that gives confidence to every merchant who displays their products, providing an opportunity to connect merchants and consumers with ease. Boost your sales, display your products to real customers, and enjoy a smooth shopping experience.',
                'description1_ar' => 'هي شركة حديثة منظمة و تمنح الثقة لكل تاجر يعرض منتجاته ، تتيح فرصة للربط بين التجار و المستهليكين بكل سهولة. زوّد مبيعاتك، واعرض منتجاتك لعملاء حقيقيين، واستمتع بتجربة تسوّق سلسة.',
                'description2' => 'Join today and start your journey in the world of e-commerce.',
                'description2_ar' => 'انضم اليوم وابدأ رحلتك في عالم التجارة الإلكترونية.',
                'image' => null, // Will use default image until admin uploads one
                'service_image' => null, // Will use default image until admin uploads one
                'is_active' => true,
            ]
        );
    }
}
