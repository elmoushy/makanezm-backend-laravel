<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Routes
|--------------------------------------------------------------------------
|
| Routes for MyFatoorah payment integration.
|
| Flow:
| 1. POST /payment/initiate - Initiate payment, returns MyFatoorah URL
| 2. GET /payment/callback - MyFatoorah redirects here after payment
| 3. GET /payment/error - MyFatoorah redirects here on error/cancel
| 4. GET /payment/status/{id} - Check payment status (optional polling)
|
*/

// Payment initiation (requires auth)
Route::middleware('auth:sanctum')->group(function () {
    // Initiate payment - returns MyFatoorah payment URL
    Route::post('/payment/initiate', [PaymentController::class, 'initiatePayment']);

    // Check payment status (for frontend polling)
    Route::get('/payment/status/{pendingId}', [PaymentController::class, 'getPaymentStatus']);
});

// Callback routes (no auth - user comes back from MyFatoorah)
// These redirect to frontend
Route::get('/payment/callback', [PaymentController::class, 'paymentCallback']);
Route::get('/payment/error', [PaymentController::class, 'paymentError']);
