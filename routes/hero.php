<?php

use App\Http\Controllers\HeroSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hero Routes
|--------------------------------------------------------------------------
|
| Routes for managing hero section content displayed on the home page.
| Images are stored as LONGBLOB (base64).
|
*/

// Public route - get active hero setting for homepage display
Route::get('/hero', [HeroSettingController::class, 'active']);

// Public image routes - serve binary images with proper Content-Type
Route::get('/hero/image', [HeroSettingController::class, 'image'])->name('hero.image');
Route::get('/hero/service-image', [HeroSettingController::class, 'serviceImage'])->name('hero.service-image');
Route::get('/hero/products-cover-image', [HeroSettingController::class, 'productsCoverImage'])->name('hero.products-cover-image');

// Protected admin routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/hero', [HeroSettingController::class, 'index']);
        Route::post('/hero', [HeroSettingController::class, 'store']);
        Route::post('/hero/products-cover', [HeroSettingController::class, 'updateProductsCover']);
        Route::match(['put', 'patch'], '/hero/{id}', [HeroSettingController::class, 'update']);
        Route::post('/hero/{id}/toggle', [HeroSettingController::class, 'toggle']);
    });
});
