<?php

use App\Http\Controllers\OrderInvoiceController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/order/invoice/{orderId}', [
    OrderInvoiceController::class, 'show',
])
    ->name('order.invoice')
    ->middleware([
        Authenticate::class,
    ]);
