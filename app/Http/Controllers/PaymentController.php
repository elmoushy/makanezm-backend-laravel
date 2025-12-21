<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Cart;
use App\Models\Investment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PendingPayment;
use App\Models\Product;
use App\Models\ProductResalePlan;
use App\Services\MyFatoorahService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentController
 *
 * Handles MyFatoorah payment integration:
 * 1. initiatePayment - Creates pending payment and returns MyFatoorah URL
 * 2. paymentCallback - Verifies payment and creates order if successful
 * 3. paymentError - Handles payment errors/cancellations
 *
 * Flow:
 * Frontend -> initiatePayment -> MyFatoorah -> paymentCallback -> Complete Order
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly MyFatoorahService $myFatoorah
    ) {}

    /**
     * Initiate payment with MyFatoorah.
     *
     * Stores checkout data in pending_payments table and returns payment URL.
     * User is redirected to MyFatoorah to complete payment.
     */
    public function initiatePayment(InitiatePaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            // Calculate totals
            $totals = $this->calculateTotals($validated['items'], $validated['discount_percent'] ?? 0);

            // Validate stock availability before initiating payment
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                if (! $product) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product not found: {$item['product_id']}",
                    ], 404);
                }
                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for: {$product->title_en}",
                        'data' => [
                            'product_id' => $product->id,
                            'available' => $product->stock_quantity,
                            'requested' => $item['quantity'],
                        ],
                    ], 422);
                }
            }

            // Build invoice items for MyFatoorah
            $invoiceItems = [];
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $invoiceItems[] = [
                    'ItemName' => $product->title_en,
                    'Quantity' => $item['quantity'],
                    'UnitPrice' => $product->price,
                ];
            }

            // Get frontend URL for callbacks
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            // Create pending payment record
            $pendingPayment = PendingPayment::create([
                'user_id' => $user->id,
                'payment_id' => 'pending_'.uniqid(), // Temporary, will be updated with MyFatoorah ID
                'amount' => $totals['total_to_pay'],
                'checkout_data' => $validated,
                'status' => PendingPayment::STATUS_PENDING,
                'expires_at' => Carbon::now()->addHours(1), // 1 hour expiry
            ]);

            // Initiate payment with MyFatoorah
            $paymentResult = $this->myFatoorah->initiatePayment([
                'InvoiceValue' => $totals['total_to_pay'],
                'CustomerName' => $user->name ?? 'Customer',
                'CustomerEmail' => $user->email ?? '',
                'CustomerMobile' => $validated['shipping_phone'] ?? '',
                'CallBackUrl' => url("/api/v1/payment/callback?pending_id={$pendingPayment->id}"),
                'ErrorUrl' => url("/api/v1/payment/error?pending_id={$pendingPayment->id}"),
                'Language' => 'ar',
                'CustomerReference' => "PENDING-{$pendingPayment->id}",
                'InvoiceItems' => $invoiceItems,
            ]);

            if (! $paymentResult['success']) {
                // Delete pending payment on failure
                $pendingPayment->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initiate payment',
                    'error' => $paymentResult['error'] ?? 'Unknown error',
                ], 500);
            }

            // Update pending payment with MyFatoorah invoice ID and URL
            $pendingPayment->update([
                'payment_id' => (string) ($paymentResult['data']['InvoiceId'] ?? $pendingPayment->payment_id),
                'invoice_url' => $paymentResult['data']['InvoiceURL'] ?? null,
            ]);

            Log::info('Payment initiated', [
                'user_id' => $user->id,
                'pending_id' => $pendingPayment->id,
                'amount' => $totals['total_to_pay'],
                'invoice_id' => $paymentResult['data']['InvoiceId'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'pending_payment_id' => $pendingPayment->id,
                    'payment_url' => $paymentResult['data']['InvoiceURL'],
                    'invoice_id' => $paymentResult['data']['InvoiceId'] ?? null,
                    'amount' => $totals['total_to_pay'],
                    'expires_at' => $pendingPayment->expires_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Handle successful payment callback from MyFatoorah.
     *
     * Verifies payment status and creates the order if payment is successful.
     * Redirects user to frontend with result.
     */
    public function paymentCallback(Request $request): RedirectResponse
    {
        $pendingId = $request->query('pending_id');
        $paymentId = $request->query('paymentId');
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        Log::info('Payment callback received', [
            'pending_id' => $pendingId,
            'payment_id' => $paymentId,
            'query' => $request->query(),
        ]);

        // Find pending payment
        $pendingPayment = PendingPayment::find($pendingId);

        if (! $pendingPayment) {
            Log::error('Pending payment not found', ['pending_id' => $pendingId]);

            return redirect("{$frontendUrl}/payment/result?status=error&message=Payment+not+found");
        }

        if ($pendingPayment->status !== PendingPayment::STATUS_PENDING) {
            Log::warning('Pending payment already processed', [
                'pending_id' => $pendingId,
                'status' => $pendingPayment->status,
            ]);

            return redirect("{$frontendUrl}/payment/result?status=already_processed");
        }

        // Verify payment with MyFatoorah
        $statusResult = $this->myFatoorah->getPaymentStatus($paymentId, 'PaymentId');

        if (! $statusResult['success']) {
            $pendingPayment->markAsFailed(['error' => $statusResult['error']]);

            return redirect("{$frontendUrl}/payment/result?status=error&message=Verification+failed");
        }

        if (! $statusResult['is_paid']) {
            $pendingPayment->markAsFailed($statusResult['data'] ?? []);
            $invoiceStatus = $statusResult['invoice_status'] ?? 'Unknown';

            return redirect("{$frontendUrl}/payment/result?status=failed&invoice_status={$invoiceStatus}");
        }

        // Payment successful - create the order
        try {
            $order = $this->createOrderFromPendingPayment($pendingPayment, $statusResult['data']);
            $pendingPayment->markAsCompleted($statusResult['data']);

            Log::info('Payment completed and order created', [
                'pending_id' => $pendingId,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return redirect("{$frontendUrl}/payment/result?status=success&order_id={$order->id}&order_number={$order->order_number}");

        } catch (\Exception $e) {
            Log::error('Failed to create order after payment', [
                'pending_id' => $pendingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Payment was successful but order creation failed - this needs manual intervention
            $pendingPayment->update([
                'status' => 'completed',
                'payment_response' => array_merge($statusResult['data'] ?? [], [
                    'order_creation_error' => $e->getMessage(),
                ]),
            ]);

            return redirect("{$frontendUrl}/payment/result?status=partial&message=Payment+successful+but+order+creation+failed.+Please+contact+support.");
        }
    }

    /**
     * Handle payment error callback from MyFatoorah.
     */
    public function paymentError(Request $request): RedirectResponse
    {
        $pendingId = $request->query('pending_id');
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        Log::info('Payment error callback', [
            'pending_id' => $pendingId,
            'query' => $request->query(),
        ]);

        $pendingPayment = PendingPayment::find($pendingId);
        if ($pendingPayment && $pendingPayment->status === PendingPayment::STATUS_PENDING) {
            $pendingPayment->markAsFailed(['error_callback' => true, 'query' => $request->query()]);
        }

        return redirect("{$frontendUrl}/payment/result?status=cancelled");
    }

    /**
     * Create order from pending payment data.
     * This is essentially the same logic as CheckoutController::checkout but using stored data.
     */
    private function createOrderFromPendingPayment(PendingPayment $pendingPayment, array $paymentData): Order
    {
        $user = $pendingPayment->user;
        $checkoutData = $pendingPayment->checkout_data;

        DB::beginTransaction();

        try {
            // Calculate totals
            $totals = $this->calculateTotals($checkoutData['items'], $checkoutData['discount_percent'] ?? 0);

            // Determine order type
            $orderType = $this->determineOrderType($checkoutData['items']);

            // Build shipping info
            $shippingName = $checkoutData['shipping_name'] ?? $user->name;
            $shippingCity = $checkoutData['shipping_city'] ?? $user->city;
            $allPhones = [$checkoutData['shipping_phone'] ?? null];
            if (! empty($checkoutData['shipping_phones'])) {
                $allPhones = array_merge($allPhones, $checkoutData['shipping_phones']);
            }
            $combinedPhone = implode(', ', array_filter($allPhones));

            // Create order with payment info
            $order = Order::create([
                'user_id' => $user->id,
                'type' => $orderType,
                'status' => $orderType === 'resale' ? 'invested' : 'pending',
                'subtotal' => $totals['subtotal'],
                'total_amount' => $totals['total_to_pay'],
                'shipping_name' => $shippingName,
                'shipping_phone' => $combinedPhone,
                'shipping_city' => $shippingCity,
                'shipping_address' => $checkoutData['shipping_address'] ?? null,
                'payment_method' => 'myfatoorah',
                'payment_reference' => $paymentData['InvoiceId'] ?? $pendingPayment->payment_id,
                'paid_at' => now(),
                'notes' => $this->hasResaleItems($checkoutData['items'])
                    ? 'Order contains investment items. Returns will be processed after maturity. Paid via MyFatoorah.'
                    : 'Paid via MyFatoorah.',
            ]);

            // Create order items and investments
            foreach ($checkoutData['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $purchaseType = $itemData['purchase_type'];
                $quantity = $itemData['quantity'];
                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $quantity;

                // Validate stock again
                if ($product->stock_quantity < $quantity) {
                    throw new \Exception("Insufficient stock for: {$product->title_en}");
                }

                // Prepare order item
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
                $resalePlan = null;
                if ($purchaseType === 'resale' && ! empty($itemData['resale_plan_id'])) {
                    $resalePlan = ProductResalePlan::findOrFail($itemData['resale_plan_id']);

                    $planSnapshot = [
                        'id' => $resalePlan->id,
                        'months' => $resalePlan->months,
                        'profit_percentage' => (float) $resalePlan->profit_percentage,
                        'label' => $resalePlan->label,
                        'snapshot_at' => Carbon::now()->toIso8601String(),
                    ];

                    $expectedReturn = $totalPrice * (1 + ($resalePlan->profit_percentage / 100));

                    $orderItemData = array_merge($orderItemData, [
                        'resale_plan_id' => $resalePlan->id,
                        'resale_months' => $resalePlan->months,
                        'resale_profit_percentage' => $resalePlan->profit_percentage,
                        'resale_expected_return' => $expectedReturn,
                        'resale_plan_snapshot' => $planSnapshot,
                        'investment_status' => 'pending',
                    ]);
                }

                // Create order item
                $orderItem = OrderItem::create($orderItemData);

                // Decrement stock
                $product->decrement('stock_quantity', $quantity);
                if ($product->stock_quantity <= 0) {
                    $product->update(['in_stock' => false]);
                }

                // Create investment for resale items
                if ($purchaseType === 'resale' && $resalePlan) {
                    Investment::create([
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

                    $orderItem->update(['investment_status' => 'active']);
                }
            }

            // Clear user's cart
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
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
        $hasSale = collect($items)->contains(fn ($item) => $item['purchase_type'] === 'wallet' || $item['purchase_type'] === 'sale'
        );
        $hasResale = collect($items)->contains(fn ($item) => $item['purchase_type'] === 'resale'
        );

        if ($hasSale && $hasResale) {
            return 'mixed';
        }

        return $hasResale ? 'resale' : 'sale';
    }

    /**
     * Check if any items are resale type.
     */
    private function hasResaleItems(array $items): bool
    {
        return collect($items)->contains(fn ($item) => $item['purchase_type'] === 'resale');
    }

    /**
     * Get payment status (for frontend polling if needed).
     */
    public function getPaymentStatus(Request $request, int $pendingId): JsonResponse
    {
        $user = $request->user();
        $pendingPayment = PendingPayment::where('id', $pendingId)
            ->where('user_id', $user->id)
            ->first();

        if (! $pendingPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $pendingPayment->id,
                'status' => $pendingPayment->status,
                'amount' => $pendingPayment->amount,
                'created_at' => $pendingPayment->created_at,
                'expires_at' => $pendingPayment->expires_at,
                'paid_at' => $pendingPayment->paid_at,
            ],
        ]);
    }
}
