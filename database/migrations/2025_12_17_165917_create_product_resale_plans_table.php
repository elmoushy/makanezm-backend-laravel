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
        Schema::create('product_resale_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('months')->comment('Duration in months: 3, 6, 12, etc.');
            $table->decimal('profit_percentage', 5, 2)->comment('Profit percentage: 20, 40, 80, etc.');
            $table->string('label')->nullable()->comment('Display label e.g. 6 Months (+40%)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'months']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_resale_plans');
    }
};
