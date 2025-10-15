<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Logo;
use App\Models\Order;
use RuntimeException;

class OrderInvoiceController extends Controller
{
    public function show(int $orderId): \Illuminate\View\View
    {
        $order = Order::findOrFail($orderId);
        $print = (bool) request()->query('print', '0');
        $name = Config::whereCode('name')->first()->config_value ?? '';
        $logoPath = Logo::whereIsDefault(true)->first()->path ?? null;
        if ($logoPath) {
            $logoPath = asset('storage/'.$logoPath);
        }
        $invoicePrint = Config::whereCode('invoice_print')->first()->config_value ?? 'A5';
        if ($invoicePrint === 'A4') {
            return view('order.invoiceA4', [
                'order' => $order,
                'print' => $print,
                'name' => $name,
                'logoPath' => $logoPath,
                'userSel' => $order->user?->code ? ' - '.__('filament.User: ').$order->user->code : '',
            ]);
        }
        if ($invoicePrint === 'A5') {
            return view('order.invoice', [
                'order' => $order,
                'print' => $print,
                'name' => $name,
                'logoPath' => $logoPath,
                'userSel' => $order->user?->code ? ' - '.__('filament.User: ').$order->user->code : '',
            ]);
        }
        throw new RuntimeException(
            'Invalid invoice print format: '.$invoicePrint.'. Expected A4 or A5.'
        );
    }
}
