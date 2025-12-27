<?php

use App\Http\Controllers\ContactSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contact Settings Routes
|--------------------------------------------------------------------------
|
| These routes handle the Contact page settings functionality.
|
*/

// Public route - get contact page settings
Route::get('/contact-settings', [ContactSettingController::class, 'index']);

// Admin routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::match(['post', 'put'], '/contact-settings', [ContactSettingController::class, 'update']);
});
