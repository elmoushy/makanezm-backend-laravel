<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove national_ids table and foreign key, add national_id_type as string.
     */
    public function up(): void
    {
        // Remove foreign key and column from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['national_id_type_id']);
            $table->dropColumn('national_id_type_id');
        });

        // Drop national_ids table
        Schema::dropIfExists('national_ids');

        // Add national_id_type as string column with default
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id_type')->default('Saudi Arabian')->after('national_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove national_id_type column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('national_id_type');
        });

        // Recreate national_ids table
        Schema::create('national_ids', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('country_code', 3)->default('SA');
            $table->string('format_regex')->nullable();
            $table->string('format_example')->nullable();
            $table->integer('length')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add back foreign key column
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('national_id_type_id')->nullable()->after('city')->constrained('national_ids')->nullOnDelete();
        });
    }
};
