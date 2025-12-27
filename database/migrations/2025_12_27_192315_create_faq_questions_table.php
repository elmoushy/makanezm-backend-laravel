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
        Schema::create('faq_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->onDelete('cascade');
            $table->text('question_ar'); // Arabic question
            $table->text('question_en'); // English question
            $table->text('answer_ar'); // Arabic answer
            $table->text('answer_en'); // English answer
            $table->integer('order')->default(0); // For sorting questions within FAQ
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default FAQ questions
        $defaultQuestions = [
            [
                'faq_id' => 1,
                'question_ar' => 'كيف يمكنني تقديم طلب؟',
                'question_en' => 'How can I place an order?',
                'answer_ar' => 'يمكنك تصفح المنتجات واختيار ما يناسبك، ثم إضافتها إلى السلة والذهاب لإتمام عملية الدفع. يمكنك الاختيار بين الدفع النقدي أو التقسيط.',
                'answer_en' => 'You can browse products and choose what suits you, then add them to the cart and proceed to checkout. You can choose between cash payment or installment plans.',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'ما هي طرق الدفع المتاحة؟',
                'question_en' => 'What payment methods are available?',
                'answer_ar' => 'نوفر خيارات الدفع النقدي والتقسيط (3، 6، أو 12 شهر). يمكنك اختيار الطريقة الأنسب لك عند إتمام الطلب.',
                'answer_en' => 'We offer cash payment and installment options (3, 6, or 12 months). You can choose the most suitable method when completing your order.',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'كيف تعمل خطط التقسيط؟',
                'question_en' => 'How do installment plans work?',
                'answer_ar' => 'نقدم خطط تقسيط مرنة: 3 أشهر (+10%)، 6 أشهر (+15%)، أو 12 شهر (+20%). يتم إضافة رسوم التقسيط إلى سعر المنتج الأساسي.',
                'answer_en' => 'We offer flexible installment plans: 3 months (+10%), 6 months (+15%), or 12 months (+20%). Installment fees are added to the base product price.',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'هل يمكنني إرجاع أو استبدال المنتج؟',
                'question_en' => 'Can I return or exchange a product?',
                'answer_ar' => 'نعم، يمكنك إرجاع أو استبدال المنتجات وفقاً لسياسة الإرجاع الخاصة بنا. يرجى التواصل مع خدمة العملاء للحصول على المساعدة.',
                'answer_en' => 'Yes, you can return or exchange products according to our return policy. Please contact customer service for assistance.',
                'order' => 4,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'كم تستغرق عملية الشحن؟',
                'question_en' => 'How long does shipping take?',
                'answer_ar' => 'عادة ما تستغرق عملية الشحن من 3 إلى 7 أيام عمل حسب موقعك في المملكة العربية السعودية.',
                'answer_en' => 'Shipping usually takes 3 to 7 business days depending on your location in Saudi Arabia.',
                'order' => 5,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'كيف يمكنني تتبع طلبي؟',
                'question_en' => 'How can I track my order?',
                'answer_ar' => 'يمكنك تتبع طلبك من خلال لوحة التحكم الخاصة بك في قسم "الطلبات". ستجد تفاصيل الحالة وتاريخ التسليم المتوقع.',
                'answer_en' => 'You can track your order through your dashboard in the "Orders" section. You will find status details and expected delivery date.',
                'order' => 6,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'كيف أقوم بإنشاء حساب تاجر؟',
                'question_en' => 'How do I create a merchant account?',
                'answer_ar' => 'يمكنك إنشاء حساب تاجر من خلال لوحة التحكم. انتقل إلى قسم "حساب التاجر" واتبع التعليمات لتقديم معلومات عملك.',
                'answer_en' => 'You can create a merchant account through the dashboard. Go to the "Merchant Account" section and follow the instructions to submit your business information.',
                'order' => 7,
                'is_active' => true,
            ],
            [
                'faq_id' => 1,
                'question_ar' => 'هل هناك رسوم لبيع المنتجات؟',
                'question_en' => 'Are there fees for selling products?',
                'answer_ar' => 'نعم، هناك عمولة بسيطة على المبيعات. يمكنك الاطلاع على تفاصيل العمولات في قسم الشراكات.',
                'answer_en' => 'Yes, there is a small commission on sales. You can view commission details in the Partnerships section.',
                'order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($defaultQuestions as $question) {
            DB::table('faq_questions')->insert(array_merge($question, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_questions');
    }
};
