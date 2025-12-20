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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('total_price', 14, 2);
            // For resale orders
            $table->foreignId('resale_plan_id')->nullable()->constrained('product_resale_plans')->onDelete('set null');
            $table->unsignedInteger('resale_months')->nullable();
            $table->decimal('resale_profit_percentage', 5, 2)->nullable();
            $table->decimal('resale_expected_return', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
