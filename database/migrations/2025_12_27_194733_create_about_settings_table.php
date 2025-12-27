<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('about_settings', function (Blueprint $table) {
            $table->id();

            // Hero Image
            $table->longText('hero_image')->nullable();

            // About Section
            $table->string('title_ar')->default('من نحن');
            $table->string('title_en')->default('About Us');
            $table->text('description1_ar')->nullable();
            $table->text('description1_en')->nullable();
            $table->text('description2_ar')->nullable();
            $table->text('description2_en')->nullable();

            // Mission Card
            $table->string('mission_title_ar')->default('مهمتنا');
            $table->string('mission_title_en')->default('Our Mission');
            $table->text('mission_description_ar')->nullable();
            $table->text('mission_description_en')->nullable();

            // Values Card
            $table->string('values_title_ar')->default('قيمنا');
            $table->string('values_title_en')->default('Our Values');
            $table->text('values_description_ar')->nullable();
            $table->text('values_description_en')->nullable();

            // Vision Card
            $table->string('vision_title_ar')->default('رؤيتنا');
            $table->string('vision_title_en')->default('Our Vision');
            $table->text('vision_description_ar')->nullable();
            $table->text('vision_description_en')->nullable();

            $table->timestamps();
        });

        // Convert hero_image to LONGBLOB for base64 storage
        DB::statement('ALTER TABLE about_settings MODIFY hero_image LONGBLOB NULL');

        // Insert default record
        DB::table('about_settings')->insert([
            'title_ar' => 'من نحن',
            'title_en' => 'About Us',
            'description1_ar' => 'مكانيزم بريدج هي منصة تجارة إلكترونية مبتكرة تربط التجار والمستهلكين في المملكة العربية السعودية.',
            'description1_en' => 'Makanizm Bridge is an innovative e-commerce platform connecting merchants and consumers in Saudi Arabia.',
            'description2_ar' => 'نحن نوفر حلول دفع مرنة تشمل خيارات التقسيط لجعل التسوق أسهل وأكثر ملاءمة للجميع.',
            'description2_en' => 'We provide flexible payment solutions including installment options to make shopping easier and more convenient for everyone.',
            'mission_title_ar' => 'مهمتنا',
            'mission_title_en' => 'Our Mission',
            'mission_description_ar' => 'تمكين التجار والمستهلكين من خلال توفير منصة تجارة إلكترونية موثوقة وسهلة الاستخدام مع خيارات دفع مرنة.',
            'mission_description_en' => 'Empowering merchants and consumers by providing a reliable and user-friendly e-commerce platform with flexible payment options.',
            'values_title_ar' => 'قيمنا',
            'values_title_en' => 'Our Values',
            'values_description_ar' => 'الثقة، الشفافية، الابتكار، وخدمة العملاء المتميزة هي القيم الأساسية التي نلتزم بها في كل ما نقوم به.',
            'values_description_en' => 'Trust, transparency, innovation, and excellent customer service are the core values we uphold in everything we do.',
            'vision_title_ar' => 'رؤيتنا',
            'vision_title_en' => 'Our Vision',
            'vision_description_ar' => 'أن نكون المنصة الرائدة للتجارة الإلكترونية في المملكة العربية السعودية، مع توفير أفضل تجربة تسوق للعملاء.',
            'vision_description_en' => 'To be the leading e-commerce platform in Saudi Arabia, providing the best shopping experience for customers.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_settings');
    }
};
