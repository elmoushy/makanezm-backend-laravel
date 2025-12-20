<?php

use App\Http\Controllers\UserInvestmentController;
use Illuminate\Support\Facades\Route;

/**
 * User Investment Routes
 *
 * Allows users to view their own investments and track payout status.
 * All routes require authentication via Sanctum.
 */
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    // Get user's investments with optional status filter
    Route::get('/investments', [UserInvestmentController::class, 'index']);
});
