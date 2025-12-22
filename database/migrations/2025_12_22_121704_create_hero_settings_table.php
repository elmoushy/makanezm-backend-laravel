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
        Schema::create('hero_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ar');
            $table->text('description1')->nullable();
            $table->text('description1_ar')->nullable();
            $table->text('description2')->nullable();
            $table->text('description2_ar')->nullable();
            $table->longText('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ensure LONGBLOB for full Base64 image binaries
        DB::statement('ALTER TABLE hero_settings MODIFY image LONGBLOB NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_settings');
    }
};
