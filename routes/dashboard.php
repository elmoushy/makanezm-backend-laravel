<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeferredSaleController;
use App\Http\Controllers\PartnershipController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Routes for dashboard, partnerships, and deferred sales.
| All routes require authentication (auth:sanctum).
|
*/

// =========================
// Public Routes (No authentication required)
// =========================
Route::prefix('reports')->group(function () {
    // Get public sales report for homepage chart
    Route::get('/public-sales', [ReportsController::class, 'getPublicSalesReport']);
});

// Public overview stats (products, users, companies counts)
Route::get('/overview-stats', [DashboardController::class, 'getOverviewStats']);

Route::middleware('auth:sanctum')->group(function () {

    // ==================== Dashboard Endpoints ====================

    Route::prefix('dashboard')->group(function () {
        // Get dashboard statistics
        Route::get('/stats', [DashboardController::class, 'getStats']);

        // Get recent orders
        Route::get('/recent-orders', [DashboardController::class, 'getRecentOrders']);

        // Get sales chart data
        Route::get('/chart/sales', [DashboardController::class, 'getSalesChart']);

        // Get alerts/notifications
        Route::get('/alerts', [DashboardController::class, 'getAlerts']);
    });

    // ==================== Reports Endpoints (Admin Only) ====================

    Route::prefix('reports')->group(function () {
        // Get comprehensive reports data
        Route::get('/', [ReportsController::class, 'index']);

        // Export reports data
        Route::get('/export', [ReportsController::class, 'export']);
    });

    // ==================== Partnership Endpoints ====================

    // User endpoints
    Route::get('/partnerships', [PartnershipController::class, 'index']);
    Route::post('/partnerships', [PartnershipController::class, 'store']);
    Route::get('/partnerships/{id}', [PartnershipController::class, 'show']);

    // Admin endpoints
    Route::prefix('admin')->group(function () {
        Route::match(['put', 'patch'], '/partnerships/{id}', [PartnershipController::class, 'update']);
        Route::delete('/partnerships/{id}', [PartnershipController::class, 'destroy']);
    });

    // ==================== Deferred Sale Endpoints ====================

    // User endpoints
    Route::get('/deferred-sales', [DeferredSaleController::class, 'index']);
    Route::post('/deferred-sales', [DeferredSaleController::class, 'store']);
    Route::get('/deferred-sales/{id}', [DeferredSaleController::class, 'show']);
    Route::delete('/deferred-sales/{id}', [DeferredSaleController::class, 'destroy']);

    // Admin endpoints
    Route::prefix('admin')->group(function () {
        Route::get('/deferred-sales/stats', [DeferredSaleController::class, 'stats']);
        Route::match(['put', 'patch'], '/deferred-sales/{id}', [DeferredSaleController::class, 'update']);
    });
});
