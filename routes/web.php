<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\GoogleAuthController;


// Mock payment routes for local development
Route::get('/mock-payment/{paymentId}', [PaymentController::class, 'showMockPaymentPage'])->name('mock-payment.show');
Route::post('/mock-payment/{paymentId}/complete', [PaymentController::class, 'completeMockPayment'])->name('mock-payment.complete');

// Fallback route for SPA
Route::fallback(function () {
    if (file_exists(public_path('index.html'))) {
        return file_get_contents(public_path('index.html'));
    }
    abort(404);
});


Route::get('/', function () {
    return view('welcome');
});

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallbackApi'])->name('auth.google.callback');