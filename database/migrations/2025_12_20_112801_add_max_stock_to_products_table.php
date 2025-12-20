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
        Schema::table('products', function (Blueprint $table) {
            // Max stock capacity (used for calculating stock percentage)
            // Default to 100 as a reasonable initial capacity
            $table->integer('max_stock')->default(100)->after('stock_quantity');
        });

        // Set max_stock to max(stock_quantity, 100) for existing products
        DB::statement('UPDATE products SET max_stock = GREATEST(stock_quantity, 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('max_stock');
        });
    }
};
