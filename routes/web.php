<?php

use App\Http\Controllers\OrderInvoiceController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/order/invoice/{orderId}', [
    OrderInvoiceController::class, 'show',
])
    ->name('order.invoice')
    // Senza vincolo numerico un id non numerico non è convertibile in int e
    // produce un TypeError (500) invece del 404 di findOrFail.
    ->whereNumber('orderId')
    ->middleware([
        Authenticate::class,
    ]);

/*
 * Stato dell'applicazione.
 *
 * Riservato agli admin: la pagina elenca versioni, spazio su disco, stato di
 * Horizon e dipendenze con vulnerabilità note, cioè materiale da non esporre
 * pubblicamente né ai cassieri.
 */
Route::middleware([Authenticate::class, EnsureUserIsAdmin::class])->group(function () {
    Route::get('/health', HealthCheckResultsController::class)->name('health');
    Route::get('/health/json', HealthCheckJsonResultsController::class)->name('health.json');
});
