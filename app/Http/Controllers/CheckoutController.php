<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Investment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductResalePlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CheckoutController
 *
 * Handles the checkout process for both direct purchase (sale) and resale (investment) items.
 * Uses database transactions to ensure data integrity - critical for order operations.
 *
 * Flow:
 * 1. Validate request items and stock availability
 * 2. Create order with proper type (sale, resale, or mixed)
 * 3. Create order items with resale plan snapshots for investment items
 * 4. Create investment records for resale items
 * 5. Clear user's cart
 *
 * Note: Payment is handled externally (outside this system).
 * All operations are wrapped in a transaction - if any step fails, everything rolls back.
 */
class CheckoutController extends Controller
{
    /**
     * Process checkout.
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Start database transaction - CRITICAL for order operations
        DB::beginTransaction();

        try {
            // Step 1: Calculate totals
            $totals = $this->calculateTotals($validated['items'], $validated['discount_percent'] ?? 0);

            // Step 2: Determine order type
            $orderType = $this->determineOrderType($validated['items']);

            // Step 3: Create order
            // Use shipping_name from request or fallback to user's name from profile
            $shippingName = $validated['shipping_name'] ?? $user->name;
            // Use shipping_city from request or fallback to user's city from profile
            $shippingCity = $validated['shipping_city'] ?? $user->city;

            // Combine primary phone with additional phones if provided
            $allPhones = [$validated['shipping_phone'] ?? null];
            if (! empty($validated['shipping_phones'])) {
                $allPhones = array_merge($allPhones, $validated['shipping_phones']);
            }
            $allPhones = array_filter($allPhones); // Remove nulls
            $combinedPhone = implode(', ', $allPhones);

            $order = Order::create([
                'user_id' => $user->id,
                'type' => $orderType,
                'status' => $orderType === 'resale' ? 'invested' : 'pending',
                'subtotal' => $totals['subtotal'],
                'total_amount' => $totals['total_to_pay'],
                'shipping_name' => $shippingName,
                'shipping_phone' => $combinedPhone,
                'shipping_city' => $shippingCity,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'notes' => $request->hasResaleItems()
                    ? 'Order contains investment items. Returns will be processed after maturity.'
                    : null,
            ]);

            // Step 4: Create order items and investments
            $investments = [];
            $saleItems = [];
            $resaleItems = [];

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $purchaseType = $itemData['purchase_type'];
                $quantity = $itemData['quantity'];
                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $quantity;

                // Validate stock availability
                if ($product->stock_quantity < $quantity) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product: {$product->title_en}",
                        'data' => [
                            'product_id' => $product->id,
                            'product_name' => $product->title_en,
                            'requested' => $quantity,
                            'available' => $product->stock_quantity,
                        ],
                    ], 422);
                }

                // Prepare order item data
                $orderItemData = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'company_id' => $itemData['company_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'purchase_type' => $purchaseType,
                ];

                // Handle resale items
                if ($purchaseType === 'resale' && ! empty($itemData['resale_plan_id'])) {
                    $resalePlan = ProductResalePlan::findOrFail($itemData['resale_plan_id']);

                    // Create snapshot of resale plan at checkout time
                    // This ensures admin changes won't affect this order
                    $planSnapshot = [
                        'id' => $resalePlan->id,
                        'months' => $resalePlan->months,
                        'profit_percentage' => (float) $resalePlan->profit_percentage,
                        'label' => $resalePlan->label,
                        'snapshot_at' => Carbon::now()->toIso8601String(),
                    ];

                    $expectedReturn = $totalPrice * (1 + ($resalePlan->profit_percentage / 100));
                    $profitAmount = $expectedReturn - $totalPrice;

                    $orderItemData = array_merge($orderItemData, [
                        'resale_plan_id' => $resalePlan->id,
                        'resale_months' => $resalePlan->months,
                        'resale_profit_percentage' => $resalePlan->profit_percentage,
                        'resale_expected_return' => $expectedReturn,
                        'resale_plan_snapshot' => $planSnapshot,
                        'investment_status' => 'pending',
                    ]);

                    $resaleItems[] = [
                        'product' => $product,
                        'plan' => $resalePlan,
                        'snapshot' => $planSnapshot,
                        'total_price' => $totalPrice,
                        'expected_return' => $expectedReturn,
                        'profit_amount' => $profitAmount,
                    ];
                } else {
                    $saleItems[] = [
                        'product' => $product,
                        'total_price' => $totalPrice,
                    ];
                }

                // Create order item
                $orderItem = OrderItem::create($orderItemData);

                // Decrement stock quantity
                $product->decrement('stock_quantity', $quantity);

                // Update in_stock status if stock runs out
                if ($product->stock_quantity <= 0) {
                    $product->update(['in_stock' => false]);
                }

                // Create investment record for resale items
                if ($purchaseType === 'resale' && ! empty($itemData['resale_plan_id'])) {
                    $investment = Investment::create([
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'product_id' => $product->id,
                        'invested_amount' => $totalPrice,
                        'expected_return' => $orderItemData['resale_expected_return'],
                        'profit_amount' => $orderItemData['resale_expected_return'] - $totalPrice,
                        'plan_months' => $orderItemData['resale_months'],
                        'plan_profit_percentage' => $orderItemData['resale_profit_percentage'],
                        'plan_label' => $resalePlan->label ?? null,
                        'investment_date' => Carbon::now(),
                        'maturity_date' => Carbon::now()->addMonths($orderItemData['resale_months']),
                        'status' => Investment::STATUS_ACTIVE,
                    ]);

                    // Update order item with active status
                    $orderItem->update(['investment_status' => 'active']);

                    $investments[] = $investment;
                }
            }

            // Step 5: Clear user's cart
            Cart::where('user_id', $user->id)->delete();

            // Commit transaction
            DB::commit();

            // Log successful checkout
            Log::info('Checkout completed', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $orderType,
                'total_amount' => $totals['total_to_pay'],
                'sale_items_count' => count($saleItems),
                'resale_items_count' => count($resaleItems),
                'investments_count' => count($investments),
            ]);

            // Prepare response
            $response = [
                'success' => true,
                'message' => $this->getSuccessMessage($orderType),
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'type' => $orderType,
                        'status' => $order->status,
                        'total_amount' => $totals['total_to_pay'],
                        'created_at' => $order->created_at,
                    ],
                    'summary' => [
                        'sale_items' => count($saleItems),
                        'resale_items' => count($resaleItems),
                        'requires_shipping' => count($saleItems) > 0,
                        'subtotal' => $totals['subtotal'],
                        'discount_amount' => $totals['discount_amount'],
                        'total_to_pay' => $totals['total_to_pay'],
                    ],
                ],
            ];

            // Add investment details if any
            if (count($investments) > 0) {
                $response['data']['investments'] = collect($investments)->map(fn ($inv) => [
                    'id' => $inv->id,
                    'invested_amount' => $inv->invested_amount,
                    'expected_return' => $inv->expected_return,
                    'profit_amount' => $inv->profit_amount,
                    'maturity_date' => $inv->maturity_date->format('Y-m-d'),
                    'plan_months' => $inv->plan_months,
                    'plan_profit_percentage' => $inv->plan_profit_percentage,
                ]);

                $response['data']['total_expected_return'] = collect($investments)->sum('expected_return');
                $response['data']['total_expected_profit'] = collect($investments)->sum('profit_amount');
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            // Rollback on any error
            DB::rollBack();

            Log::error('Checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Checkout failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during checkout.',
            ], 500);
        }
    }

    /**
     * Calculate totals for checkout.
     */
    private function calculateTotals(array $items, float $discountPercent = 0): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $subtotal += $product->price * $item['quantity'];
            }
        }

        $discountAmount = $subtotal * ($discountPercent / 100);
        $totalToPay = $subtotal - $discountAmount;

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total_to_pay' => $totalToPay,
        ];
    }

    /**
     * Determine order type based on items.
     */
    private function determineOrderType(array $items): string
    {
        $hasSale = collect($items)->contains(fn ($item) => $item['purchase_type'] === 'wallet' || $item['purchase_type'] === 'sale');
        $hasResale = collect($items)->contains(fn ($item) => $item['purchase_type'] === 'resale');

        if ($hasSale && $hasResale) {
            return 'mixed';
        }

        return $hasResale ? 'resale' : 'sale';
    }

    /**
     * Get success message based on order type.
     */
    private function getSuccessMessage(string $orderType): string
    {
        return match ($orderType) {
            'sale' => 'Order placed successfully! Your items will be shipped soon.',
            'resale' => 'Investment completed successfully! You will receive returns after the maturity period.',
            'mixed' => 'Order placed successfully! Direct purchase items will be shipped, and investments will mature on their scheduled dates.',
            default => 'Order completed successfully!',
        };
    }

    /**
     * Get user's active investments.
     */
    public function getInvestments(): JsonResponse
    {
        $user = request()->user();

        $investments = Investment::where('user_id', $user->id)
            ->with(['product:id,title_en,title_ar', 'order:id,order_number'])
            ->orderBy('maturity_date')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'product_name' => $inv->product?->title_en,
                'product_name_ar' => $inv->product?->title_ar,
                'order_number' => $inv->order?->order_number,
                'invested_amount' => $inv->invested_amount,
                'expected_return' => $inv->expected_return,
                'profit_amount' => $inv->profit_amount,
                'profit_percentage' => $inv->plan_profit_percentage,
                'investment_date' => $inv->investment_date->format('Y-m-d'),
                'maturity_date' => $inv->maturity_date->format('Y-m-d'),
                'days_until_maturity' => $inv->daysUntilMaturity(),
                'status' => $inv->status,
                'status_label' => $this->getInvestmentStatusLabel($inv),
                'is_matured' => $inv->hasMatured(),
                'paid_out_at' => $inv->paid_out_at?->format('Y-m-d'),
            ]);

        $summary = [
            'total_invested' => $investments->sum('invested_amount'),
            'total_expected_return' => $investments->sum('expected_return'),
            'total_expected_profit' => $investments->sum('profit_amount'),
            'active_count' => $investments->where('status', 'active')->count(),
            'matured_count' => $investments->where('status', 'matured')->count(),
            'paid_out_count' => $investments->where('status', 'paid_out')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'investments' => $investments,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Get human-readable status label for investment.
     */
    private function getInvestmentStatusLabel(Investment $investment): string
    {
        return match ($investment->status) {
            Investment::STATUS_PENDING => 'Pending activation',
            Investment::STATUS_ACTIVE => 'Active - Maturing on '.$investment->maturity_date->format('M d, Y'),
            Investment::STATUS_MATURED => 'Matured - Awaiting payout',
            Investment::STATUS_PAID_OUT => 'Paid out on '.$investment->paid_out_at?->format('M d, Y'),
            Investment::STATUS_CANCELLED => 'Cancelled',
            default => $investment->status,
        };
    }
}
