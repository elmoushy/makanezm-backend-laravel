<?php

use App\Http\Controllers\SliderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Slider Routes
|--------------------------------------------------------------------------
|
| Routes for managing hero sliders displayed on the home page.
| Images are stored as LONGBLOB (base64).
|
*/

// Public route - get active sliders for hero display
Route::get('/sliders', [SliderController::class, 'active']);

// Protected admin routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/sliders', [SliderController::class, 'index']);
        Route::post('/sliders', [SliderController::class, 'store']);
        Route::match(['put', 'patch'], '/sliders/{id}', [SliderController::class, 'update']);
        Route::post('/sliders/{id}/toggle', [SliderController::class, 'toggle']);
        Route::post('/sliders/reorder', [SliderController::class, 'reorder']);
        Route::delete('/sliders/{id}', [SliderController::class, 'destroy']);
    });
});
