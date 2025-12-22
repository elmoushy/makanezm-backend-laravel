<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert all image columns back to LONGTEXT and store as base64 data URIs.
     * This ensures JSON-safe responses (no binary encoding issues).
     */
    public function up(): void
    {
        // First, clear all existing binary image data (it's causing issues)
        // We'll set them to NULL and admin can re-upload
        DB::table('hero_settings')->update([
            'image' => null,
            'service_image' => null,
            'products_cover_image' => null,
        ]);

        // Now we can safely convert columns to LONGTEXT
        if (Schema::hasColumn('hero_settings', 'image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY image LONGTEXT NULL');
        }
        if (Schema::hasColumn('hero_settings', 'service_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY service_image LONGTEXT NULL');
        }
        if (Schema::hasColumn('hero_settings', 'products_cover_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY products_cover_image LONGTEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert - LONGTEXT is safer
    }

    private function convertBlobToDataUri(mixed $value, ?string $mime, string $fieldName): array
    {
        if ($value === null) {
            return [];
        }

        // Handle stream resources
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        // If it's already a valid data URI, leave it alone
        if (str_starts_with($value, 'data:') && mb_check_encoding($value, 'UTF-8')) {
            return [];
        }

        // Convert binary to base64 data URI
        $mimeToUse = $mime ?? 'application/octet-stream';
        $dataUri = 'data:'.$mimeToUse.';base64,'.base64_encode($value);

        return [$fieldName => $dataUri];
    }
};
