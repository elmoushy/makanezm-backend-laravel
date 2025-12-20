<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product Routes
|--------------------------------------------------------------------------
|
| Here are the routes for product management.
| Public routes are accessible without authentication.
| Admin routes require admin authentication.
|
*/

// =========================
// Public Routes (with optional authentication via stateless check in controller)
// =========================
Route::prefix('products')->group(function () {
    // List all active products (with search, filter by type, in_stock)
    // Controller will optionally check for authenticated user to return favorite status
    Route::get('/', [ProductController::class, 'index'])->name('products.index');

    // Get featured products for homepage (max 3 products - fast endpoint)
    Route::get('/featured', [ProductController::class, 'getFeaturedProducts'])->name('products.featured');

    // Get available product types
    Route::get('/types', [ProductController::class, 'types'])->name('products.types');

    // Get single product details (with payment options and resale plans)
    // Controller will optionally check for authenticated user to return favorite status
    Route::get('/{id}', [ProductController::class, 'show'])->name('products.show')->where('id', '[0-9]+');

    // Serve product main image
    Route::get('/{id}/main-image', [ProductController::class, 'mainImage'])->name('products.main-image');

    // Serve product sub-image
    Route::get('/{productId}/images/{imageId}', [ProductController::class, 'image'])->name('products.image');

    // Get all product images as Base64 (main + sub)
    Route::get('/{id}/images/base64', [ProductController::class, 'getAllImagesBase64'])->name('products.images.base64');
});

// =========================
// Authenticated User Routes (requires auth)
// =========================
Route::middleware('auth:sanctum')->prefix('products')->group(function () {
    // Get user's favorite products
    Route::get('/favorites', [ProductController::class, 'getFavorites'])->name('products.favorites');

    // Toggle product favorite status
    Route::post('/{id}/favorite', [ProductController::class, 'toggleFavorite'])->name('products.favorite.toggle');
});

// =========================
// Admin Routes (requires auth)
// =========================
Route::middleware('auth:sanctum')->prefix('admin/products')->group(function () {
    // List all products (including inactive)
    Route::get('/', [ProductController::class, 'adminIndex'])->name('admin.products.index');

    // Get product details (including inactive options)
    Route::get('/{id}', [ProductController::class, 'adminShow'])->name('admin.products.show');

    // Create new product
    Route::post('/', [ProductController::class, 'store'])->name('admin.products.store');

    // Update product
    Route::put('/{id}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::patch('/{id}', [ProductController::class, 'update']);

    // Delete product
    Route::delete('/{id}', [ProductController::class, 'destroy'])->name('admin.products.destroy');

    // Add images to product
    Route::post('/{id}/images', [ProductController::class, 'addImages'])->name('admin.products.images.add');

    // Delete product image
    Route::delete('/{productId}/images/{imageId}', [ProductController::class, 'deleteImage'])->name('admin.products.images.delete');
});
