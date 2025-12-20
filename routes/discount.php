<?php

use App\Http\Controllers\DiscountCodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Discount Code Routes
|--------------------------------------------------------------------------
*/

// Public route - validate a discount code (for cart)
Route::post('/discount-codes/validate', [DiscountCodeController::class, 'validate']);

// Admin routes - requires authentication
Route::middleware('auth:sanctum')->prefix('admin/discount-codes')->group(function () {
    Route::get('/', [DiscountCodeController::class, 'index']);
    Route::post('/', [DiscountCodeController::class, 'store']);
    Route::get('/{discountCode}', [DiscountCodeController::class, 'show']);
    Route::put('/{discountCode}', [DiscountCodeController::class, 'update']);
    Route::patch('/{discountCode}', [DiscountCodeController::class, 'update']);
    Route::patch('/{discountCode}/toggle', [DiscountCodeController::class, 'toggle']);
    Route::delete('/{discountCode}', [DiscountCodeController::class, 'destroy']);
});
