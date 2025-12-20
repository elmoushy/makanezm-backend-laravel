<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet Routes
|--------------------------------------------------------------------------
|
| Routes for wallet management.
| All routes require authentication (auth:sanctum).
|
| Base URL: /api/v1/wallet
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // Get wallet with balance and recent transactions
    Route::get('/wallet', [WalletController::class, 'index']);

    // Get wallet balance only
    Route::get('/wallet/balance', [WalletController::class, 'balance']);

    // Get wallet transactions history
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    // Deposit money (fake endpoint - will integrate with PayMob later)
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);

    // Withdraw money (fake endpoint - will integrate with PayMob later)
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);

});
