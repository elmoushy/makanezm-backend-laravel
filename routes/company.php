<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Company Routes
|--------------------------------------------------------------------------
|
| Routes for managing resale companies.
| Companies are display options for users during resale checkout.
| They do NOT affect pricing or profit calculations.
|
*/

// Public routes - for resale checkout
Route::get('/companies', [CompanyController::class, 'activeCompanies']);
Route::get('/companies/{id}/logo', [CompanyController::class, 'logo'])->name('companies.logo');

// Admin routes - protected
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::get('/companies/{id}', [CompanyController::class, 'show']);
        Route::match(['put', 'patch'], '/companies/{id}', [CompanyController::class, 'update']);
        Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);
    });
});
