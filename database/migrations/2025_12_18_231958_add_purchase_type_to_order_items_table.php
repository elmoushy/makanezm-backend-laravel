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
        Schema::table('order_items', function (Blueprint $table) {
            // Purchase type: wallet (direct purchase with shipping) or resale (investment)
            $table->enum('purchase_type', ['wallet', 'resale'])->default('wallet')->after('total_price');

            // Snapshot of resale plan at checkout (JSON to preserve exact state)
            // This ensures admin changes to product plans don't affect existing orders
            $table->json('resale_plan_snapshot')->nullable()->after('resale_expected_return');

            // Investment status for this item
            $table->enum('investment_status', ['pending', 'active', 'matured', 'paid_out', 'cancelled'])
                ->nullable()
                ->after('resale_plan_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_type', 'resale_plan_snapshot', 'investment_status']);
        });
    }
};
