<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Updates order type to include 'mixed' and status to include 'invested'.
     */
    public function up(): void
    {
        // Update type enum to include 'mixed'
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('sale', 'resale', 'mixed') DEFAULT 'sale'");

        // Update status enum to include 'invested'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'invested') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert type enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('sale', 'resale') DEFAULT 'sale'");

        // Revert status enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded') DEFAULT 'pending'");
    }
};
