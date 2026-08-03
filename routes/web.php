<?php

use App\Http\Controllers\OrderInvoiceController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

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
