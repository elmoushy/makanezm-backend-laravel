<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('order_number')->unique();
            $table->enum('type', ['sale', 'resale'])->default('sale');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total_amount', 14, 2);
            // Shipping address for sale orders
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_city')->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('notes')->nullable();
            // For resale orders - expected return date and amount
            $table->date('resale_return_date')->nullable();
            $table->decimal('resale_expected_return', 14, 2)->nullable();
            $table->boolean('resale_returned')->default(false);
            $table->timestamp('resale_returned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['status']);
            $table->index(['resale_return_date', 'resale_returned']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
