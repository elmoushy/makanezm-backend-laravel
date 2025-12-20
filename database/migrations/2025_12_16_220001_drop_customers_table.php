<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop the customers table as customer info is now part of users table.
     * A User with role 'USER' IS a Customer.
     */
    public function up(): void
    {
        Schema::dropIfExists('customers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('mobile');
            $table->string('email');
            $table->string('city');
            $table->string('national_id')->unique();
            $table->string('bank_iban')->nullable();
            $table->string('bank_name')->nullable();
            $table->enum('created_by_role', ['ADMIN', 'USER']);
            $table->timestamps();
        });
    }
};
