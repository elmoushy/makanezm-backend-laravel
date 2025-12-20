<?php

namespace Database\Seeders;

use App\Models\Investment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Test Investment Seeder
 *
 * Creates test investments for user ID 1 to test all investment payout scenarios.
 * This covers all edge cases for the investment lifecycle.
 */
class TestInvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = 1;

        // Get first user or fail
        $user = User::find($userId);
        if (! $user) {
            $this->command->error("User with ID {$userId} not found. Please create a user first.");

            return;
        }

        // Get first order and order item (or create dummy references)
        $order = Order::where('user_id', $userId)->first();
        $orderItem = $order ? OrderItem::where('order_id', $order->id)->first() : null;

        // Use defaults if no real order exists
        $orderId = $order?->id ?? 1;
        $orderItemId = $orderItem?->id ?? 1;
        $productId = $orderItem?->product_id ?? 1;

        $this->command->info("Creating test investments for User ID: {$userId}");

        // ============================================
        // CASE 1: Matured investment - Ready for payout (PAST maturity)
        // This should appear in admin pending payouts
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 1000.00,
            'expected_return' => 1100.00,
            'profit_amount' => 100.00,
            'plan_months' => 3,
            'plan_profit_percentage' => 10.00,
            'plan_label' => 'Test: 3 Months +10%',
            'investment_date' => Carbon::now()->subMonths(4), // Invested 4 months ago
            'maturity_date' => Carbon::now()->subMonth(), // Matured 1 month ago
            'status' => Investment::STATUS_ACTIVE, // Still active, should be auto-matured
            'notes' => 'TEST CASE 1: Should be auto-matured and appear in pending payouts',
        ]);
        $this->command->info('✓ Case 1: Created PAST maturity investment (should auto-mature)');

        // ============================================
        // CASE 2: Matured investment - Already marked as matured
        // This should appear in admin pending payouts
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 2000.00,
            'expected_return' => 2300.00,
            'profit_amount' => 300.00,
            'plan_months' => 6,
            'plan_profit_percentage' => 15.00,
            'plan_label' => 'Test: 6 Months +15%',
            'investment_date' => Carbon::now()->subMonths(7), // Invested 7 months ago
            'maturity_date' => Carbon::now()->subDays(10), // Matured 10 days ago
            'status' => Investment::STATUS_MATURED, // Already matured status
            'notes' => 'TEST CASE 2: Already matured, pending payout',
        ]);
        $this->command->info('✓ Case 2: Created MATURED investment (pending payout)');

        // ============================================
        // CASE 3: Matured TODAY - Edge case
        // This should appear in admin pending payouts
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 500.00,
            'expected_return' => 550.00,
            'profit_amount' => 50.00,
            'plan_months' => 3,
            'plan_profit_percentage' => 10.00,
            'plan_label' => 'Test: Matured Today',
            'investment_date' => Carbon::now()->subMonths(3), // Invested 3 months ago
            'maturity_date' => Carbon::today(), // Matures TODAY
            'status' => Investment::STATUS_ACTIVE, // Should be auto-matured
            'notes' => 'TEST CASE 3: Matures TODAY - edge case',
        ]);
        $this->command->info('✓ Case 3: Created TODAY maturity investment (edge case)');

        // ============================================
        // CASE 4: Active investment - NOT yet matured
        // This should NOT appear in pending payouts
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 3000.00,
            'expected_return' => 3600.00,
            'profit_amount' => 600.00,
            'plan_months' => 12,
            'plan_profit_percentage' => 20.00,
            'plan_label' => 'Test: 12 Months +20%',
            'investment_date' => Carbon::now(), // Invested today
            'maturity_date' => Carbon::now()->addMonths(12), // Matures in 12 months
            'status' => Investment::STATUS_ACTIVE,
            'notes' => 'TEST CASE 4: Active investment, NOT matured yet',
        ]);
        $this->command->info('✓ Case 4: Created FUTURE maturity investment (should NOT appear)');

        // ============================================
        // CASE 5: Active investment - Matures tomorrow
        // This should NOT appear in pending payouts yet
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 750.00,
            'expected_return' => 825.00,
            'profit_amount' => 75.00,
            'plan_months' => 3,
            'plan_profit_percentage' => 10.00,
            'plan_label' => 'Test: Matures Tomorrow',
            'investment_date' => Carbon::now()->subMonths(3)->addDay(),
            'maturity_date' => Carbon::tomorrow(), // Matures TOMORROW
            'status' => Investment::STATUS_ACTIVE,
            'notes' => 'TEST CASE 5: Matures tomorrow - should NOT appear yet',
        ]);
        $this->command->info('✓ Case 5: Created TOMORROW maturity investment (should NOT appear)');

        // ============================================
        // CASE 6: Already paid out - History
        // This should appear ONLY in paid history, NOT in pending
        // ============================================
        $adminUser = User::where('role', 'ADMIN')->first();
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 1500.00,
            'expected_return' => 1725.00,
            'profit_amount' => 225.00,
            'plan_months' => 6,
            'plan_profit_percentage' => 15.00,
            'plan_label' => 'Test: Already Paid',
            'investment_date' => Carbon::now()->subMonths(8),
            'maturity_date' => Carbon::now()->subMonths(2),
            'paid_out_at' => Carbon::now()->subMonth(), // Paid 1 month ago
            'paid_by' => $adminUser?->id,
            'status' => Investment::STATUS_PAID_OUT,
            'notes' => 'TEST CASE 6: Already paid out - should appear in history only',
        ]);
        $this->command->info('✓ Case 6: Created PAID OUT investment (history only)');

        // ============================================
        // CASE 7: Cancelled investment
        // This should NOT appear anywhere in payouts
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 800.00,
            'expected_return' => 880.00,
            'profit_amount' => 80.00,
            'plan_months' => 3,
            'plan_profit_percentage' => 10.00,
            'plan_label' => 'Test: Cancelled',
            'investment_date' => Carbon::now()->subMonths(2),
            'maturity_date' => Carbon::now()->addMonth(),
            'status' => Investment::STATUS_CANCELLED,
            'cancellation_reason' => 'Order was refunded - test case',
            'cancelled_at' => Carbon::now()->subWeek(),
            'notes' => 'TEST CASE 7: Cancelled investment - should NOT appear',
        ]);
        $this->command->info('✓ Case 7: Created CANCELLED investment (should NOT appear)');

        // ============================================
        // CASE 8: Pending investment (order not confirmed)
        // This should NOT appear in payouts
        // ============================================
        Investment::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'invested_amount' => 1200.00,
            'expected_return' => 1320.00,
            'profit_amount' => 120.00,
            'plan_months' => 3,
            'plan_profit_percentage' => 10.00,
            'plan_label' => 'Test: Pending Status',
            'investment_date' => Carbon::now(),
            'maturity_date' => Carbon::now()->addMonths(3),
            'status' => Investment::STATUS_PENDING, // Order not yet confirmed
            'notes' => 'TEST CASE 8: Pending status - order not confirmed',
        ]);
        $this->command->info('✓ Case 8: Created PENDING investment (order not confirmed)');

        // Summary
        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('Test Investment Summary:');
        $this->command->info('========================================');

        $shouldMatureCount = Investment::shouldMature()->count();
        $pendingPayoutCount = Investment::pendingPayout()->count();
        $paidOutCount = Investment::paidOut()->count();
        $activeCount = Investment::active()->count();

        $this->command->info("• Investments that should auto-mature: {$shouldMatureCount}");
        $this->command->info("• Pending payouts (matured, not paid): {$pendingPayoutCount}");
        $this->command->info("• Already paid out (history): {$paidOutCount}");
        $this->command->info("• Active (not yet matured): {$activeCount}");
        $this->command->newLine();
        $this->command->info('Run the API endpoint to test auto-maturation:');
        $this->command->info('GET /api/v1/admin/investment-payouts');
    }
}
