<?php

use App\Http\Controllers\PrivacyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Privacy Routes
|--------------------------------------------------------------------------
|
| These routes handle the Privacy page settings functionality.
|
*/

// Public routes - accessible without authentication
Route::get('/privacy', [PrivacyController::class, 'index']);

// Admin routes - require authentication
Route::middleware('auth:sanctum')->prefix('privacy')->group(function () {
    Route::put('/', [PrivacyController::class, 'update']);
    Route::post('/', [PrivacyController::class, 'update']); // For FormData submissions
    Route::delete('/image', [PrivacyController::class, 'removeImage']);
});
