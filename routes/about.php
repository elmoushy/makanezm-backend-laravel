<?php

use App\Http\Controllers\AboutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| About Routes
|--------------------------------------------------------------------------
|
| These routes handle the About page settings functionality.
|
*/

// Public routes - accessible without authentication
Route::get('/about', [AboutController::class, 'index']);

// Admin routes - require authentication
Route::middleware('auth:sanctum')->prefix('about')->group(function () {
    Route::put('/', [AboutController::class, 'update']);
    Route::post('/', [AboutController::class, 'update']); // For FormData submissions
    Route::delete('/image', [AboutController::class, 'removeImage']);
});
