<?php

use Illuminate\Support\Facades\Route;
use PayBridge\Payment\Http\Controllers\PaymentGatewaySettingController;

$adminPrefix     = config('payment.admin.prefix', 'admin/payment-gateways');
$adminMiddleware = config('payment.admin.middleware', ['web', 'auth']);

// Protected Admin Control Panel Routes (Requires Authentication)
Route::middleware($adminMiddleware)->prefix($adminPrefix)->name('paybridge.admin.')->group(function () {
    Route::get('/', [PaymentGatewaySettingController::class, 'index'])->name('index');
    Route::post('/{code}', [PaymentGatewaySettingController::class, 'update'])->name('update');
    Route::post('/{code}/toggle', [PaymentGatewaySettingController::class, 'toggle'])->name('toggle');
    Route::post('/{code}/default', [PaymentGatewaySettingController::class, 'setDefault'])->name('default');
});

// Public API endpoint for customer checkout (Returns active gateway names/badges)
Route::middleware(['web'])->get('/api/payment-gateways/active', [PaymentGatewaySettingController::class, 'activeGateways'])->name('paybridge.active');
