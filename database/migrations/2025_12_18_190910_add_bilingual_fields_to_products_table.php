<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('title_ar')->after('title')->nullable();
            $table->string('title_en')->after('title_ar')->nullable();
            $table->text('description_ar')->after('description')->nullable();
            $table->text('description_en')->after('description_ar')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'title_en', 'description_ar', 'description_en']);
        });
    }
};
