<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductResalePlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all orders for the authenticated user.
     * GET /api/v1/orders
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with('items.product')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get order details.
     * GET /api/v1/orders/{id}
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $order = Order::with('items.product')
            ->where('user_id', $user->id)
            ->find($id);

        if (! $order) {
            return $this->notFoundResponse('Order not found.');
        }

        return $this->successResponse(new OrderDetailResource($order));
    }

    /**
     * Place a new order.
     * POST /api/v1/orders
     *
     * Types:
     * - sale: Product is delivered to user's address
     * - resale: Money is invested, user receives principal + profit after period ends
     */
    public function store(PlaceOrderRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        $orderType = $validated['type'];

        try {
            return DB::transaction(function () use ($user, $validated, $orderType) {
                $wallet = $user->getOrCreateWallet();
                $items = $validated['items'];
                $subtotal = 0;
                $orderItems = [];
                $resaleReturnDate = null;
                $resaleExpectedReturn = 0;

                // Validate and prepare items
                foreach ($items as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('is_active', true)
                        ->first();

                    if (! $product) {
                        throw new \Exception("Product with ID {$item['product_id']} not found or inactive.");
                    }

                    // For sale orders, check stock
                    if ($orderType === 'sale') {
                        if (! $product->in_stock || $product->stock_quantity < $item['quantity']) {
                            throw new \Exception("Product '{$product->title}' is out of stock or insufficient quantity.");
                        }
                    }

                    $quantity = $item['quantity'];
                    $unitPrice = $product->price;
                    $totalPrice = $unitPrice * $quantity;
                    $subtotal += $totalPrice;

                    $orderItemData = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                    ];

                    // For resale orders, get resale plan details
                    if ($orderType === 'resale') {
                        $resalePlan = ProductResalePlan::where('id', $item['resale_plan_id'])
                            ->where('product_id', $product->id)
                            ->where('is_active', true)
                            ->first();

                        if (! $resalePlan) {
                            throw new \Exception("Resale plan not found for product '{$product->title}'.");
                        }

                        $expectedReturn = $totalPrice + ($totalPrice * $resalePlan->profit_percentage / 100);

                        $orderItemData['resale_plan_id'] = $resalePlan->id;
                        $orderItemData['resale_months'] = $resalePlan->months;
                        $orderItemData['resale_profit_percentage'] = $resalePlan->profit_percentage;
                        $orderItemData['resale_expected_return'] = $expectedReturn;

                        // Calculate return date (furthest date if multiple items)
                        $itemReturnDate = Carbon::now()->addMonths($resalePlan->months);
                        if (! $resaleReturnDate || $itemReturnDate->gt($resaleReturnDate)) {
                            $resaleReturnDate = $itemReturnDate;
                        }

                        $resaleExpectedReturn += $expectedReturn;
                    }

                    $orderItems[] = $orderItemData;
                }

                $totalAmount = $subtotal; // No discounts for now

                // Check wallet balance
                if (! $wallet->hasSufficientBalance($totalAmount)) {
                    throw new \Exception('Insufficient wallet balance. Please add funds to your wallet.');
                }

                // Create the order
                $orderData = [
                    'user_id' => $user->id,
                    'type' => $orderType,
                    'status' => 'confirmed',
                    'subtotal' => $subtotal,
                    'total_amount' => $totalAmount,
                    'notes' => $validated['notes'] ?? null,
                ];

                // Add shipping info for sale orders
                if ($orderType === 'sale') {
                    $orderData['shipping_name'] = $validated['shipping_name'];
                    $orderData['shipping_phone'] = $validated['shipping_phone'];
                    $orderData['shipping_city'] = $validated['shipping_city'];
                    $orderData['shipping_address'] = $validated['shipping_address'];
                }

                // Add resale info for resale orders
                if ($orderType === 'resale') {
                    $orderData['resale_return_date'] = $resaleReturnDate;
                    $orderData['resale_expected_return'] = $resaleExpectedReturn;
                }

                $order = Order::create($orderData);

                // Create order items
                foreach ($orderItems as $orderItemData) {
                    $order->items()->create($orderItemData);
                }

                // Deduct from wallet
                $wallet->payForOrder(
                    amount: $totalAmount,
                    orderId: $order->id,
                    description: "Payment for order #{$order->order_number}"
                );

                // For sale orders, reduce stock
                if ($orderType === 'sale') {
                    foreach ($items as $item) {
                        $product = Product::find($item['product_id']);
                        $product->decrement('stock_quantity', $item['quantity']);

                        // Update in_stock status
                        if ($product->stock_quantity <= 0) {
                            $product->update(['in_stock' => false]);
                        }
                    }
                }

                $order->load('items.product');

                return $this->createdResponse(
                    new OrderDetailResource($order),
                    $orderType === 'sale'
                        ? 'Order placed successfully. Your order will be delivered soon.'
                        : 'Resale order placed successfully. Your investment will return on '.$resaleReturnDate->format('Y-m-d').'.'
                );
            });
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                'ORDER_FAILED',
                400
            );
        }
    }

    /**
     * Cancel an order.
     * POST /api/v1/orders/{id}/cancel
     */
    public function cancel(Request $request, int $id)
    {
        $user = $request->user();

        $order = Order::with('items')
            ->where('user_id', $user->id)
            ->find($id);

        if (! $order) {
            return $this->notFoundResponse('Order not found.');
        }

        if (! $order->canBeCancelled()) {
            return $this->errorResponse(
                'This order cannot be cancelled.',
                'CANNOT_CANCEL',
                400
            );
        }

        try {
            return DB::transaction(function () use ($user, $order) {
                $wallet = $user->getOrCreateWallet();

                // Refund to wallet
                $wallet->refund(
                    amount: $order->total_amount,
                    orderId: $order->id,
                    description: "Refund for cancelled order #{$order->order_number}"
                );

                // For sale orders, restore stock
                if ($order->isSale()) {
                    foreach ($order->items as $item) {
                        $product = Product::find($item->product_id);
                        if ($product) {
                            $product->increment('stock_quantity', $item->quantity);
                            $product->update(['in_stock' => true]);
                        }
                    }
                }

                // Update order status
                $order->update(['status' => 'cancelled']);

                return $this->successResponse(
                    new OrderDetailResource($order->fresh('items.product')),
                    'Order cancelled and refunded successfully.'
                );
            });
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                'CANCEL_FAILED',
                400
            );
        }
    }

    // ==================== Admin Endpoints ====================

    /**
     * Get all orders (Admin).
     * GET /api/v1/admin/orders
     */
    public function adminIndex(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view all orders.');
        }

        $query = Order::with(['items.product', 'user'])
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $orders = $query->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Update order status (Admin).
     * PUT /api/v1/admin/orders/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update order status.');
        }

        // Simplified status: pending, confirmed (final for wallet), invested (for resale), cancelled
        $request->validate([
            'status' => ['required', 'in:pending,confirmed,invested,cancelled'],
        ]);

        $order = Order::with('items.product')->find($id);

        if (! $order) {
            return $this->notFoundResponse('Order not found.');
        }

        $order->update(['status' => $request->status]);

        return $this->successResponse(
            new OrderDetailResource($order),
            'Order status updated successfully.'
        );
    }

    /**
     * Process pending resale returns (Admin or Scheduled Job).
     * POST /api/v1/admin/orders/process-resale-returns
     *
     * This endpoint processes all resale orders that are due for return.
     */
    public function processResaleReturns(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can process resale returns.');
        }

        $pendingReturns = Order::with('user')
            ->pendingResaleReturns()
            ->get();

        $processed = 0;
        $errors = [];

        foreach ($pendingReturns as $order) {
            try {
                DB::transaction(function () use ($order) {
                    $wallet = $order->user->getOrCreateWallet();

                    // Return money + profit to wallet
                    $wallet->receiveResaleReturn(
                        amount: $order->resale_expected_return,
                        orderId: $order->id,
                        description: "Resale return for order #{$order->order_number}"
                    );

                    // Mark as returned
                    $order->markResaleReturned();
                });
                $processed++;
            } catch (\Exception $e) {
                $errors[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->successResponse([
            'processed' => $processed,
            'total_pending' => $pendingReturns->count(),
            'errors' => $errors,
        ], "Processed {$processed} resale returns.");
    }
}
