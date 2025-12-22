<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartQuantityRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all cart items for the authenticated user.
     * GET /api/v1/cart
     *
     * Returns cart items with product details:
     * - main_image_base64
     * - title
     * - description
     * - quantity
     * - price
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $cartItems = Cart::with('product')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalItems = $cartItems->sum('quantity');
        $totalPrice = $cartItems->sum(fn ($item) => $item->quantity * ($item->product->price ?? 0));

        return $this->successResponse([
            'cart_items' => CartItemResource::collection($cartItems),
            'summary' => [
                'total_items' => $totalItems,
                'total_price' => number_format($totalPrice, 2, '.', ''),
                'items_count' => $cartItems->count(),
            ],
        ]);
    }

    /**
     * Add product to cart.
     * POST /api/v1/cart
     *
     * Body: { product_id, quantity? }
     */
    public function store(AddToCartRequest $request)
    {
        $user = $request->user();
        $productId = $request->product_id;
        $quantity = $request->quantity ?? 1;

        // Check if product exists and is active
        $product = Product::where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return $this->notFoundResponse('Product not found or is not available.');
        }

        // Check stock availability
        if (! $product->in_stock || $product->stock_quantity < $quantity) {
            return $this->errorResponse(
                'Product is out of stock or insufficient quantity available.',
                'OUT_OF_STOCK',
                400
            );
        }

        // Check if product already in cart
        $existingCartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingCartItem) {
            // Update quantity if already in cart
            $newQuantity = $existingCartItem->quantity + $quantity;

            // Check if new quantity exceeds stock
            if ($product->stock_quantity < $newQuantity) {
                return $this->errorResponse(
                    'Cannot add more items. Only '.$product->stock_quantity.' available in stock.',
                    'EXCEEDS_STOCK',
                    400
                );
            }

            $existingCartItem->update(['quantity' => $newQuantity]);
            $existingCartItem->load('product');

            return $this->successResponse(
                new CartItemResource($existingCartItem),
                'Product quantity updated in cart.'
            );
        }

        // Create new cart item
        $cartItem = Cart::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);

        $cartItem->load('product');

        return $this->createdResponse(
            new CartItemResource($cartItem),
            'Product added to cart successfully.'
        );
    }

    /**
     * Update cart item quantity.
     * PUT /api/v1/cart/{productId}
     *
     * Body: { quantity }
     */
    public function update(UpdateCartQuantityRequest $request, int $productId)
    {
        $user = $request->user();
        $quantity = $request->quantity;

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (! $cartItem) {
            return $this->notFoundResponse('Product not found in cart.');
        }

        // Check stock availability
        $product = $cartItem->product;
        if ($product && $product->stock_quantity < $quantity) {
            return $this->errorResponse(
                'Insufficient stock. Only '.$product->stock_quantity.' available.',
                'EXCEEDS_STOCK',
                400
            );
        }

        $cartItem->update(['quantity' => $quantity]);
        $cartItem->load('product');

        return $this->successResponse(
            new CartItemResource($cartItem),
            'Cart quantity updated successfully.'
        );
    }

    /**
     * Increase product quantity by 1.
     * POST /api/v1/cart/{productId}/increase
     */
    public function increase(Request $request, int $productId)
    {
        $user = $request->user();

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (! $cartItem) {
            return $this->notFoundResponse('Product not found in cart.');
        }

        // Check stock availability
        $product = $cartItem->product;
        $newQuantity = $cartItem->quantity + 1;

        if ($product && $product->stock_quantity < $newQuantity) {
            return $this->errorResponse(
                'Cannot increase quantity. Maximum stock reached.',
                'EXCEEDS_STOCK',
                400
            );
        }

        $cartItem->update(['quantity' => $newQuantity]);
        $cartItem->load('product');

        return $this->successResponse(
            new CartItemResource($cartItem),
            'Quantity increased successfully.'
        );
    }

    /**
     * Decrease product quantity by 1.
     * POST /api/v1/cart/{productId}/decrease
     *
     * Removes item from cart if quantity becomes 0.
     */
    public function decrease(Request $request, int $productId)
    {
        $user = $request->user();

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (! $cartItem) {
            return $this->notFoundResponse('Product not found in cart.');
        }

        $newQuantity = $cartItem->quantity - 1;

        if ($newQuantity <= 0) {
            // Remove item from cart
            $cartItem->delete();

            return $this->successResponse(
                null,
                'Product removed from cart.'
            );
        }

        $cartItem->update(['quantity' => $newQuantity]);
        $cartItem->load('product');

        return $this->successResponse(
            new CartItemResource($cartItem),
            'Quantity decreased successfully.'
        );
    }

    /**
     * Remove product from cart.
     * DELETE /api/v1/cart/{productId}
     */
    public function destroy(Request $request, int $productId)
    {
        $user = $request->user();

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (! $cartItem) {
            return $this->notFoundResponse('Product not found in cart.');
        }

        $cartItem->delete();

        return $this->successResponse(
            null,
            'Product removed from cart successfully.'
        );
    }

    /**
     * Clear all items from cart.
     * DELETE /api/v1/cart
     */
    public function clear(Request $request)
    {
        $user = $request->user();

        $deletedCount = Cart::where('user_id', $user->id)->delete();

        return $this->successResponse(
            ['deleted_count' => $deletedCount],
            'Cart cleared successfully.'
        );
    }

    /**
     * Update cart item purchase options (purchase type, resale plan, company).
     * PATCH /api/v1/cart/{productId}/options
     *
     * Body: { purchase_type?, resale_plan_id?, company_id? }
     */
    public function updateOptions(Request $request, int $productId)
    {
        $user = $request->user();

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (! $cartItem) {
            return $this->notFoundResponse('Product not found in cart.');
        }

        $validated = $request->validate([
            'purchase_type' => 'sometimes|string|in:wallet,resale',
            'resale_plan_id' => 'sometimes|nullable|integer|exists:product_resale_plans,id',
            'company_id' => 'sometimes|nullable|integer|exists:companies,id',
        ]);

        // If purchase_type is 'wallet', clear resale_plan_id
        if (isset($validated['purchase_type']) && $validated['purchase_type'] === 'wallet') {
            $validated['resale_plan_id'] = null;
        }

        // Validate that resale_plan belongs to this product if provided
        if (! empty($validated['resale_plan_id'])) {
            $product = $cartItem->product;
            $planBelongsToProduct = $product->resalePlans()
                ->where('id', $validated['resale_plan_id'])
                ->exists();

            if (! $planBelongsToProduct) {
                return $this->errorResponse(
                    'The selected resale plan does not belong to this product.',
                    'INVALID_RESALE_PLAN',
                    400
                );
            }
        }

        $cartItem->update($validated);
        $cartItem->load('product');

        return $this->successResponse(
            new CartItemResource($cartItem),
            'Cart item options updated successfully.'
        );
    }
}
