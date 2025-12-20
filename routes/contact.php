<?php

use App\Http\Controllers\ContactMessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contact Message Routes
|--------------------------------------------------------------------------
*/

// Public route - anyone can submit a contact message
Route::post('/contact', [ContactMessageController::class, 'store']);

// Admin routes - requires authentication and admin role
Route::middleware('auth:sanctum')->prefix('admin/contact-messages')->group(function () {
    Route::get('/', [ContactMessageController::class, 'index']);
    Route::get('/unread-count', [ContactMessageController::class, 'unreadCount']);
    Route::get('/{contactMessage}', [ContactMessageController::class, 'show']);
    Route::patch('/{contactMessage}/mark-read', [ContactMessageController::class, 'markAsRead']);
    Route::patch('/{contactMessage}/mark-unread', [ContactMessageController::class, 'markAsUnread']);
    Route::delete('/{contactMessage}', [ContactMessageController::class, 'destroy']);
});
