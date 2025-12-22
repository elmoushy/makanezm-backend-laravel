<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add mime_type columns for hero images (matching Company/Product pattern).
     */
    public function up(): void
    {
        Schema::table('hero_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('hero_settings', 'image_mime_type')) {
                $table->string('image_mime_type')->nullable()->after('image');
            }
            if (! Schema::hasColumn('hero_settings', 'service_image_mime_type')) {
                $table->string('service_image_mime_type')->nullable()->after('service_image');
            }
            if (! Schema::hasColumn('hero_settings', 'products_cover_image_mime_type')) {
                $table->string('products_cover_image_mime_type')->nullable()->after('products_cover_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hero_settings', function (Blueprint $table) {
            $table->dropColumn(['image_mime_type', 'service_image_mime_type', 'products_cover_image_mime_type']);
        });
    }
};
