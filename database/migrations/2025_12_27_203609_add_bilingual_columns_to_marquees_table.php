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
        Schema::table('marquees', function (Blueprint $table) {
            $table->string('text_ar')->nullable()->after('id');
            $table->string('text_en')->nullable()->after('text_ar');
        });

        // Migrate existing text data to text_ar (assuming existing data is Arabic)
        DB::table('marquees')->update([
            'text_ar' => DB::raw('text'),
            'text_en' => DB::raw('text'),
        ]);

        // Drop the old text column
        Schema::table('marquees', function (Blueprint $table) {
            $table->dropColumn('text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marquees', function (Blueprint $table) {
            $table->string('text')->nullable()->after('text_en');
        });

        // Restore data from text_ar
        DB::table('marquees')->update([
            'text' => DB::raw('text_ar'),
        ]);

        Schema::table('marquees', function (Blueprint $table) {
            $table->dropColumn(['text_ar', 'text_en']);
        });
    }
};
