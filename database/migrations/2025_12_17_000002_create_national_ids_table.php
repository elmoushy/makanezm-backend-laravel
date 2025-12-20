<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration creates a table for national ID types/formats.
     * Admin can manage which national ID types are supported.
     */
    public function up(): void
    {
        Schema::create('national_ids', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // e.g., "Saudi National ID", "Iqama"
            $table->string('code')->unique();  // e.g., "SA_NID", "IQAMA"
            $table->string('country_code', 3)->default('SA');  // ISO country code
            $table->string('format_regex')->nullable();  // Regex for validation
            $table->string('format_example')->nullable();  // Example format
            $table->integer('length')->nullable();  // Expected length
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add national_id_type to users table (references the national_ids table)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('national_id_type_id')->nullable()->after('city')->constrained('national_ids')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['national_id_type_id']);
            $table->dropColumn('national_id_type_id');
        });

        Schema::dropIfExists('national_ids');
    }
};
