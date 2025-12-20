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
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE product_payment_options MODIFY COLUMN type ENUM('cash', 'installment', 'wallet') NOT NULL DEFAULT 'cash'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Note: This might fail if there are 'wallet' values in the database
            DB::statement("ALTER TABLE product_payment_options MODIFY COLUMN type ENUM('cash', 'installment') NOT NULL DEFAULT 'cash'");
        }
    }
};
