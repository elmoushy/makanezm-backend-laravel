<?php

use App\Http\Controllers\InvestmentPayoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Investment Payout Routes
|--------------------------------------------------------------------------
|
| Admin-only routes for managing investment payouts.
| All routes require authentication via Sanctum.
|
| Base URL: /api/v1/admin/investment-payouts
|
*/

Route::middleware('auth:sanctum')->prefix('admin/investment-payouts')->group(function () {
    // Get pending payouts (matured investments ready for payout)
    Route::get('/', [InvestmentPayoutController::class, 'pendingPayouts']);

    // Get summary statistics
    Route::get('/summary', [InvestmentPayoutController::class, 'summary']);

    // Get paid payouts history
    Route::get('/history', [InvestmentPayoutController::class, 'paidHistory']);

    // Mark an investment as paid
    Route::post('/{id}/mark-paid', [InvestmentPayoutController::class, 'markAsPaid']);
});
