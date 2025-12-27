<?php

use App\Http\Controllers\FaqController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/faqs', [FaqController::class, 'index']); // Get all active FAQs with questions

// Admin routes (protected by auth:sanctum middleware)
Route::middleware('auth:sanctum')->group(function () {
    // FAQ (parent with image) routes
    Route::get('/admin/faqs', [FaqController::class, 'adminIndex']); // Get all FAQs with questions
    Route::post('/admin/faqs', [FaqController::class, 'store']); // Create FAQ with questions
    Route::put('/admin/faqs/{faq}', [FaqController::class, 'update']); // Update FAQ (image, order, active)
    Route::delete('/admin/faqs/{faq}', [FaqController::class, 'destroy']); // Delete FAQ and all questions
    Route::post('/admin/faqs/reorder', [FaqController::class, 'reorder']); // Reorder FAQs

    // FAQ Question routes
    Route::post('/admin/faqs/{faq}/questions', [FaqController::class, 'addQuestion']); // Add question to FAQ
    Route::put('/admin/faq-questions/{question}', [FaqController::class, 'updateQuestion']); // Update question
    Route::delete('/admin/faq-questions/{question}', [FaqController::class, 'deleteQuestion']); // Delete question
    Route::post('/admin/faqs/{faq}/questions/reorder', [FaqController::class, 'reorderQuestions']); // Reorder questions
});
