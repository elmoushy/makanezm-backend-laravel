<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert image columns to LONGBLOB for storing large Base64 image binaries.
     */
    public function up(): void
    {
        // Convert service_image and products_cover_image to LONGBLOB (guarded for fresh installs).
        if (Schema::hasColumn('hero_settings', 'service_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY service_image LONGBLOB NULL');
        }
        if (Schema::hasColumn('hero_settings', 'products_cover_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY products_cover_image LONGBLOB NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to LONGTEXT
        if (Schema::hasColumn('hero_settings', 'service_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY service_image LONGTEXT NULL');
        }
        if (Schema::hasColumn('hero_settings', 'products_cover_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY products_cover_image LONGTEXT NULL');
        }
    }
};
