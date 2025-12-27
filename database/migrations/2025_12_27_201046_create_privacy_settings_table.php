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
        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->id();

            // Hero Image
            $table->longText('hero_image')->nullable();

            // Page Title
            $table->string('title_ar')->default('الشروط والخصوصية');
            $table->string('title_en')->default('Terms & Privacy');

            // Intro Section (JSON array of bullet points)
            $table->json('intro_ar')->nullable();
            $table->json('intro_en')->nullable();

            // Terms of Use Section
            $table->string('terms_title_ar')->default('شروط الاستخدام');
            $table->string('terms_title_en')->default('Terms of Use');
            $table->text('terms_content_ar')->nullable();
            $table->text('terms_content_en')->nullable();

            // Privacy Policy Section
            $table->string('privacy_title_ar')->default('سياسة الخصوصية');
            $table->string('privacy_title_en')->default('Privacy Policy');
            $table->text('privacy_content_ar')->nullable();
            $table->text('privacy_content_en')->nullable();

            // Operation Terms Section
            $table->string('operation_title_ar')->default('شروط التشغيل');
            $table->string('operation_title_en')->default('Operation Terms');
            $table->text('operation_content_ar')->nullable();
            $table->text('operation_content_en')->nullable();

            // Copyright Section
            $table->string('copyright_title_ar')->default('حقوق النشر');
            $table->string('copyright_title_en')->default('Copyright');
            $table->text('copyright_content_ar')->nullable();
            $table->text('copyright_content_en')->nullable();

            $table->timestamps();
        });

        // Convert hero_image to LONGBLOB for base64 storage
        DB::statement('ALTER TABLE privacy_settings MODIFY hero_image LONGBLOB NULL');

        // Insert default record
        DB::table('privacy_settings')->insert([
            'title_ar' => 'الشروط والخصوصية',
            'title_en' => 'Terms & Privacy',
            'intro_ar' => json_encode([
                'نحن نلتزم بحماية خصوصيتك وبياناتك الشخصية.',
                'استخدامك للمنصة يعني موافقتك على هذه الشروط.',
                'نحتفظ بحق تعديل هذه الشروط في أي وقت.',
            ]),
            'intro_en' => json_encode([
                'We are committed to protecting your privacy and personal data.',
                'Your use of the platform means you agree to these terms.',
                'We reserve the right to modify these terms at any time.',
            ]),
            'terms_title_ar' => 'شروط الاستخدام',
            'terms_title_en' => 'Terms of Use',
            'terms_content_ar' => 'باستخدامك لمنصة مكانيزم بريدج، فإنك توافق على الالتزام بجميع الشروط والأحكام المنصوص عليها. يجب أن تكون المعلومات المقدمة صحيحة ودقيقة.',
            'terms_content_en' => 'By using the Makanizm Bridge platform, you agree to comply with all the terms and conditions set forth. The information provided must be true and accurate.',
            'privacy_title_ar' => 'سياسة الخصوصية',
            'privacy_title_en' => 'Privacy Policy',
            'privacy_content_ar' => 'نحن نحترم خصوصيتك ونلتزم بحماية بياناتك الشخصية. لن نشارك معلوماتك مع أطراف ثالثة دون موافقتك المسبقة.',
            'privacy_content_en' => 'We respect your privacy and are committed to protecting your personal data. We will not share your information with third parties without your prior consent.',
            'operation_title_ar' => 'شروط التشغيل',
            'operation_title_en' => 'Operation Terms',
            'operation_content_ar' => 'جميع عمليات التشغيل والبيع تخضع للوائح والأنظمة المعمول بها في المملكة العربية السعودية.',
            'operation_content_en' => 'All operations and sales are subject to the regulations in force in the Kingdom of Saudi Arabia.',
            'copyright_title_ar' => 'حقوق النشر',
            'copyright_title_en' => 'Copyright',
            'copyright_content_ar' => 'جميع الحقوق محفوظة لمنصة مكانيزم بريدج. يحظر نسخ أو إعادة نشر أي محتوى دون إذن كتابي مسبق.',
            'copyright_content_en' => 'All rights reserved to Makanizm Bridge platform. Copying or republishing any content without prior written permission is prohibited.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
