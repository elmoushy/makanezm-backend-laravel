<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\UserMobileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Routes
|--------------------------------------------------------------------------
|
| Note: A User with role 'USER' IS a Customer - they are the same entity.
| Customer profile fields (city, national_id, bank info) are
| part of the User model. Mobile numbers are in a separate table.
|
| Difference between /register and /users:
| - /register: Self-registration, returns token (user is logged in)
| - /users: Admin creates user account, no token returned
|
*/

// Protected user routes
Route::middleware('auth:sanctum')->group(function () {

    // User profile routes (for customers to manage their own profile)
    Route::prefix('me')->group(function () {
        Route::get('/profile', [UserController::class, 'getMyProfile']);
        Route::match(['put', 'patch'], '/profile', [UserController::class, 'updateMyProfile']);
        Route::post('/change-password', [UserController::class, 'changePassword']);

        // User mobile numbers management
        Route::get('/mobiles', [UserMobileController::class, 'index']);
        Route::post('/mobiles', [UserMobileController::class, 'store']);
        Route::get('/mobiles/{id}', [UserMobileController::class, 'show']);
        Route::match(['put', 'patch'], '/mobiles/{id}', [UserMobileController::class, 'update']);
        Route::delete('/mobiles/{id}', [UserMobileController::class, 'destroy']);
        Route::post('/mobiles/{id}/set-primary', [UserMobileController::class, 'setPrimary']);
    });

    // Admin user management
    Route::prefix('admin')->group(function () {
        Route::post('/users', [UserController::class, 'createUser']); // Admin creates user (can specify role USER or ADMIN)
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::match(['put', 'patch'], '/users/{id}', [UserController::class, 'updateUser']);
        Route::delete('/users/{id}', [UserController::class, 'deleteUser']);
    });
});
