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
        Schema::table('hero_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('hero_settings', 'image_mime')) {
                $table->string('image_mime', 100)->nullable()->after('image');
            }
            if (! Schema::hasColumn('hero_settings', 'service_image_mime')) {
                $table->string('service_image_mime', 100)->nullable()->after('service_image');
            }
            if (! Schema::hasColumn('hero_settings', 'products_cover_image_mime')) {
                $table->string('products_cover_image_mime', 100)->nullable()->after('products_cover_image');
            }
        });

        if (Schema::hasColumn('hero_settings', 'products_cover_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY products_cover_image LONGBLOB NULL');
        }

        // Convert legacy data-URI strings (data:mime;base64,...) into (blob + mime) where possible.
        DB::table('hero_settings')->orderBy('id')->chunkById(50, function ($rows) {
            foreach ($rows as $row) {
                $updates = [];

                $parsed = $this->parseDataUri($row->image);
                if ($parsed !== null) {
                    $updates['image'] = $parsed['blob'];
                    $updates['image_mime'] = $parsed['mime'];
                }

                $parsed = $this->parseDataUri($row->service_image);
                if ($parsed !== null) {
                    $updates['service_image'] = $parsed['blob'];
                    $updates['service_image_mime'] = $parsed['mime'];
                }

                $parsed = $this->parseDataUri($row->products_cover_image);
                if ($parsed !== null) {
                    $updates['products_cover_image'] = $parsed['blob'];
                    $updates['products_cover_image_mime'] = $parsed['mime'];
                }

                if ($updates !== []) {
                    DB::table('hero_settings')->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert products_cover_image to LONGTEXT to match old behavior.
        if (Schema::hasColumn('hero_settings', 'products_cover_image')) {
            DB::statement('ALTER TABLE hero_settings MODIFY products_cover_image LONGTEXT NULL');
        }

        Schema::table('hero_settings', function (Blueprint $table) {
            if (Schema::hasColumn('hero_settings', 'image_mime')) {
                $table->dropColumn('image_mime');
            }
            if (Schema::hasColumn('hero_settings', 'service_image_mime')) {
                $table->dropColumn('service_image_mime');
            }
            if (Schema::hasColumn('hero_settings', 'products_cover_image_mime')) {
                $table->dropColumn('products_cover_image_mime');
            }
        });
    }

    /**
     * @return array{mime: string, blob: string}|null
     */
    private function parseDataUri(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        if (! str_starts_with($value, 'data:')) {
            return null;
        }

        if (! preg_match('/^data:(?<mime>[-\\w.]+\\/[-\\w.+]+);base64,(?<data>.+)$/s', $value, $matches)) {
            return null;
        }

        $mime = $matches['mime'] ?? null;
        $data = $matches['data'] ?? null;

        if (! is_string($mime) || $mime === '' || ! is_string($data) || $data === '') {
            return null;
        }

        $blob = base64_decode($data, true);
        if ($blob === false) {
            return null;
        }

        return [
            'mime' => $mime,
            'blob' => $blob,
        ];
    }
};
