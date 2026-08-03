<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Logo;
use App\Models\Order;
use Illuminate\View\View;

class OrderInvoiceController extends Controller
{
    public function show(int $orderId): View
    {
        $order = Order::findOrFail($orderId);
        $print = (bool) request()->query('print', '0');
        $name = Config::value('name') ?? '';
        $logoPath = Logo::whereIsDefault(true)->first()->path ?? null;
        if ($logoPath) {
            $logoPath = asset('storage/'.$logoPath);
        }
        // invoice_print è testo libero inserito da un operatore: 'a5', ' A5' o il
        // campo svuotato facevano fallire il confronto e sollevavano l'eccezione,
        // rendendo ogni stampa un 500. Si normalizza e si ripiega su A5.
        $invoicePrint = strtoupper(trim(
            (string) (Config::value('invoice_print') ?? '')
        ));

        $data = [
            'order' => $order,
            'print' => $print,
            'name' => $name,
            'logoPath' => $logoPath,
            'userSel' => $order->user?->code ? ' - '.__('filament.User: ').$order->user->code : '',
        ];

        return $invoicePrint === 'A4'
            ? view('order.invoiceA4', $data)
            : view('order.invoice', $data);
    }
}
