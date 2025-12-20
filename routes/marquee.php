<?php

use App\Http\Controllers\MarqueeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marquee Routes
|--------------------------------------------------------------------------
|
| Routes for managing marquee banner texts that scroll at the top of the site.
|
*/

// Public route - get active marquees for banner display
Route::get('/marquees', [MarqueeController::class, 'active']);

// Protected admin routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/marquees', [MarqueeController::class, 'index']);
        Route::post('/marquees', [MarqueeController::class, 'store']);
        Route::match(['put', 'patch'], '/marquees/{id}', [MarqueeController::class, 'update']);
        Route::post('/marquees/{id}/toggle', [MarqueeController::class, 'toggle']);
        Route::delete('/marquees/{id}', [MarqueeController::class, 'destroy']);
    });
});
