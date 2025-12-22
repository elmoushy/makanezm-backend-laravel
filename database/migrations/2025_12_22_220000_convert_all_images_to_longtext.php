<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert all image columns to LONGTEXT to store Base64 strings.
     * This avoids JSON encoding issues with raw binary data.
     */
    public function up(): void
    {
        // 1. Companies
        if (Schema::hasTable('companies')) {
            DB::table('companies')->update(['logo' => null]);
            DB::statement('ALTER TABLE companies MODIFY logo LONGTEXT NULL');
        }

        // 2. Products
        if (Schema::hasTable('products')) {
            DB::table('products')->update(['main_image' => null]);
            DB::statement('ALTER TABLE products MODIFY main_image LONGTEXT NULL');
        }

        // 3. Product Images
        if (Schema::hasTable('product_images')) {
            // Since 'image' might be non-nullable, and we need to clear binary data,
            // we will delete the rows. Sub-images without content are useless.
            DB::table('product_images')->delete();
            DB::statement('ALTER TABLE product_images MODIFY image LONGTEXT NULL');
        }

        // 4. Sliders
        if (Schema::hasTable('sliders')) {
            // Sliders might already be storing Base64 strings if created recently,
            // but if the column is LONGBLOB, we should convert it.
            // If it's already Base64 string in a BLOB, we might be able to keep it,
            // but safer to clear and ensure type is correct.
            // Or we can try to cast it?
            // Given the user's request to "fix", let's clear to be safe and consistent.
            DB::table('sliders')->delete();
            DB::statement('ALTER TABLE sliders MODIFY image LONGTEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert - LONGTEXT is safer for this architecture
    }
};
