<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Revert image columns to LONGBLOB (binary storage).
     * This matches the Company/Product pattern where images are:
     * 1. Stored as raw binary in LONGBLOB
     * 2. Served via dedicated routes with proper Content-Type headers
     * 3. NOT included in JSON responses (hidden from serialization)
     */
    public function up(): void
    {
        // Allow full logo binaries instead of being limited to 255 bytes.
        if (Schema::hasTable('companies')) {
            DB::statement('ALTER TABLE companies MODIFY logo LONGBLOB NULL');
        }

        // Products main image
        if (Schema::hasTable('products')) {
            DB::statement('ALTER TABLE products MODIFY main_image LONGBLOB NULL');
        }

        // Product sub-images
        if (Schema::hasTable('product_images')) {
            DB::statement('ALTER TABLE product_images MODIFY image LONGBLOB NULL');
        }

        // Sliders
        if (Schema::hasTable('sliders')) {
            DB::statement('ALTER TABLE sliders MODIFY image LONGBLOB NULL');
        }

        // Hero settings
        if (Schema::hasTable('hero_settings')) {
            DB::statement('ALTER TABLE hero_settings MODIFY image LONGBLOB NULL');
            DB::statement('ALTER TABLE hero_settings MODIFY service_image LONGBLOB NULL');
            DB::statement('ALTER TABLE hero_settings MODIFY products_cover_image LONGBLOB NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert needed
    }
};
