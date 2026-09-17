<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

// Group for Payment related routes
Route::prefix('payment')->group(function() {
    
    // Initiate payment
    Route::get('/checkout', [PaymentController::class, 'checkout'])->name('payment.checkout');

    // Callbacks from generic Gateway
    // Exclude these routes from CSRF token verification in App\Http\Middleware\VerifyCsrfToken
    Route::post('/{gateway}/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::post('/{gateway}/fail', [PaymentController::class, 'fail'])->name('payment.fail');
    Route::post('/{gateway}/cancel', [PaymentController::class, 'fail'])->name('payment.cancel');

    // Server-to-server IPN / Webhooks
    Route::post('/{gateway}/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');

});
