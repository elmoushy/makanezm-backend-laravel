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
        Schema::table('carts', function (Blueprint $table) {
            // Purchase type: 'wallet' for direct purchase, 'resale' for investment
            $table->string('purchase_type', 20)->default('wallet')->after('quantity');

            // Selected resale plan (null if purchase_type is 'wallet')
            $table->foreignId('resale_plan_id')->nullable()->after('purchase_type')
                ->constrained('product_resale_plans')->nullOnDelete();

            // Selected company for delivery
            $table->foreignId('company_id')->nullable()->after('resale_plan_id')
                ->constrained('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['resale_plan_id']);
            $table->dropForeign(['company_id']);
            $table->dropColumn(['purchase_type', 'resale_plan_id', 'company_id']);
        });
    }
};
