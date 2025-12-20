<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The original schema used `binary`, which only stores up to 255 bytes.
        // Switch to LONGBLOB so full image data can be persisted.
        DB::statement('ALTER TABLE products MODIFY main_image LONGBLOB NULL');
        DB::statement('ALTER TABLE product_images MODIFY image LONGBLOB NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the previous (limited) type. This may truncate data if images exceed 255 bytes.
        DB::statement('ALTER TABLE products MODIFY main_image BINARY(255) NULL');
        DB::statement('ALTER TABLE product_images MODIFY image BINARY(255) NOT NULL');
    }
};
