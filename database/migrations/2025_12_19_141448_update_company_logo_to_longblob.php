<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allow full logo binaries instead of being limited to 255 bytes.
        DB::statement('ALTER TABLE companies MODIFY logo LONGBLOB NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original binary length (will truncate anything over 255 bytes).
        DB::statement('ALTER TABLE companies MODIFY logo BINARY(255) NULL');
    }
};
