<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Investments table tracks user investments from resale purchases.
     * Stores snapshot of resale plan at checkout time to prevent admin changes affecting existing investments.
     */
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Investment amounts (stored in cents/smallest currency unit for precision)
            $table->decimal('invested_amount', 12, 2); // Amount user paid
            $table->decimal('expected_return', 12, 2); // Amount user will receive
            $table->decimal('profit_amount', 12, 2); // Profit = expected_return - invested_amount

            // Resale plan snapshot at time of purchase (immutable after checkout)
            $table->unsignedInteger('plan_months'); // Investment period in months
            $table->decimal('plan_profit_percentage', 5, 2); // Profit percentage at checkout
            $table->string('plan_label')->nullable(); // Plan label for display

            // Investment lifecycle
            $table->date('investment_date'); // When investment was made
            $table->date('maturity_date'); // When investment matures and can be withdrawn
            $table->timestamp('paid_out_at')->nullable(); // When profit was paid to user

            // Status: pending, active, matured, paid_out, cancelled
            $table->enum('status', ['pending', 'active', 'matured', 'paid_out', 'cancelled'])->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // For audit trail
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['maturity_date', 'status']);
            $table->index('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
