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
        // For SQLite, we need a different approach since it doesn't support dropping columns easily
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support dropping columns in older versions
            // The columns months and percentage may not exist in fresh migrations
            // Skip index operations for SQLite as they may not exist
            return;
        }

        // Disable foreign key checks for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        // Check if the index exists before trying to drop it
        $indexExists = false;
        if (DB::getDriverName() === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM product_payment_options WHERE Key_name = 'product_payment_options_product_id_type_months_unique'");
            $indexExists = count($indexes) > 0;
        }

        if ($indexExists) {
            Schema::table('product_payment_options', function (Blueprint $table) {
                $table->dropIndex('product_payment_options_product_id_type_months_unique');
            });
        }

        // Check if columns exist before dropping
        if (Schema::hasColumn('product_payment_options', 'months')) {
            Schema::table('product_payment_options', function (Blueprint $table) {
                $table->dropColumn('months');
            });
        }

        if (Schema::hasColumn('product_payment_options', 'percentage')) {
            Schema::table('product_payment_options', function (Blueprint $table) {
                $table->dropColumn('percentage');
            });
        }

        // Re-enable foreign key checks
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');
        }

        Schema::table('product_payment_options', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique(['product_id', 'type']);
        });

        Schema::table('product_payment_options', function (Blueprint $table) {
            // Re-add the columns
            $table->unsignedInteger('months')->nullable()->after('type');
            $table->decimal('percentage', 5, 2)->default(0)->after('months');
        });

        Schema::table('product_payment_options', function (Blueprint $table) {
            // Re-add original unique constraint
            $table->unique(['product_id', 'type', 'months'], 'product_payment_options_product_id_type_months_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
