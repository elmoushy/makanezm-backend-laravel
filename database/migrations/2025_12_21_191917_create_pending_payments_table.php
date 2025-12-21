<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores checkout data temporarily while user completes payment on MyFatoorah.
     * Once payment is confirmed, the actual order is created and this record is deleted.
     */
    public function up(): void
    {
        Schema::create('pending_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payment_id')->unique()->comment('MyFatoorah InvoiceId');
            $table->string('invoice_url')->nullable()->comment('MyFatoorah payment URL');
            $table->decimal('amount', 12, 2)->comment('Total amount to pay');
            $table->json('checkout_data')->comment('Full checkout payload including items, shipping, discount');
            $table->enum('status', ['pending', 'completed', 'failed', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable()->comment('Payment session expiry');
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_response')->nullable()->comment('MyFatoorah payment response data');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_payments');
    }
};
