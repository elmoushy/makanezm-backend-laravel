<?php

use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cart Routes
|--------------------------------------------------------------------------
|
| Routes for shopping cart management.
| All routes require authentication (auth:sanctum).
|
| Base URL: /api/v1/cart
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // Get all cart items with product details (base64 image, title, description, quantity, price)
    Route::get('/cart', [CartController::class, 'index']);

    // Add product to cart
    Route::post('/cart', [CartController::class, 'store']);

    // Update cart item quantity
    Route::put('/cart/{productId}', [CartController::class, 'update']);

    // Update cart item purchase options (purchase_type, resale_plan_id, company_id)
    Route::patch('/cart/{productId}/options', [CartController::class, 'updateOptions']);

    // Increase product quantity by 1
    Route::post('/cart/{productId}/increase', [CartController::class, 'increase']);

    // Decrease product quantity by 1 (removes if quantity becomes 0)
    Route::post('/cart/{productId}/decrease', [CartController::class, 'decrease']);

    // Remove product from cart
    Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

    // Clear entire cart
    Route::delete('/cart', [CartController::class, 'clear']);

});
