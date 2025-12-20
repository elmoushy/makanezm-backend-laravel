<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds customer-specific fields to the users table.
     * A User with role 'USER' IS a Customer - they are the same entity.
     * Admins don't need these fields but can have them if needed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable()->after('role');
            $table->string('city')->nullable()->after('mobile');
            $table->string('national_id')->nullable()->unique()->after('city');
            $table->string('bank_iban')->nullable()->after('national_id');
            $table->string('bank_name')->nullable()->after('bank_iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mobile', 'city', 'national_id', 'bank_iban', 'bank_name']);
        });
    }
};
