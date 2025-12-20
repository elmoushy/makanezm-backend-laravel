<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Order Routes
|--------------------------------------------------------------------------
|
| Routes for order management.
| All routes require authentication (auth:sanctum).
|
| Base URL: /api/v1/orders
|
| Order Types:
| - sale: Product is delivered to user's address
| - resale: Money is invested, user receives principal + profit after period ends
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ==================== Checkout Endpoints ====================

    // Process checkout (handles both wallet and resale purchases)
    Route::post('/checkout', [CheckoutController::class, 'checkout']);

    // Get user's investments
    Route::get('/investments', [CheckoutController::class, 'getInvestments']);

    // ==================== User Order Endpoints ====================

    // Get all user's orders
    Route::get('/orders', [OrderController::class, 'index']);

    // Get order details
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    // Place a new order (sale or resale) - legacy endpoint
    Route::post('/orders', [OrderController::class, 'store']);

    // Cancel an order
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // ==================== Admin Order Endpoints ====================

    // Get all orders (Admin)
    Route::get('/admin/orders', [OrderController::class, 'adminIndex']);

    // Update order status (Admin)
    Route::put('/admin/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Process pending resale returns (Admin)
    Route::post('/admin/orders/process-resale-returns', [OrderController::class, 'processResaleReturns']);

});
