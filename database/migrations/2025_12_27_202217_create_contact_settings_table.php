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
        Schema::create('contact_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar')->default('تواصل معنا');
            $table->string('title_en')->default('Contact Us');
            $table->text('description1_ar')->nullable();
            $table->text('description1_en')->nullable();
            $table->text('description2_ar')->nullable();
            $table->text('description2_en')->nullable();
            $table->timestamps();
        });

        // Insert default record
        DB::table('contact_settings')->insert([
            'title_ar' => 'تواصل معنا',
            'title_en' => 'Contact Us',
            'description1_ar' => 'نحن هنا لمساعدتك! إذا كان لديك أي استفسار أو تحتاج إلى دعم، لا تتردد في التواصل معنا.',
            'description1_en' => 'We are here to help! If you have any questions or need support, don\'t hesitate to contact us.',
            'description2_ar' => 'فريقنا متاح للرد على جميع استفساراتك وتقديم أفضل تجربة لك.',
            'description2_en' => 'Our team is available to answer all your inquiries and provide you with the best experience.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_settings');
    }
};
