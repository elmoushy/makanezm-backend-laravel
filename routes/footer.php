<?php

use App\Http\Controllers\FooterLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Footer Routes
|--------------------------------------------------------------------------
|
| Routes for managing footer social media links.
|
*/

// Public route - get footer links
Route::get('/footer-links', [FooterLinkController::class, 'index']);

// Protected admin route
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/footer-links', [FooterLinkController::class, 'update']);
});
