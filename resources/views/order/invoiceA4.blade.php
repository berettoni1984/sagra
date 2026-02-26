<!DOCTYPE html>
<html>
<head>
    <title>Ordine #{{ $order->id }}</title>
    @vite('resources/css/app.css')
    <script>
        @if($print)
        window.print();
        @endif
    </script>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 12pt;
        }

        .table-page {
            page-break-after: auto;
            page-break-inside: avoid;
            width: 100%;
            max-width: 29.7cm;
            margin: 0 auto;
            padding: 2mm 0;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .table-page {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 3mm 2mm;
            vertical-align: top;
        }

        thead th {
            font-weight: bold;
        }

        .separator-col {
            border: none !important;
            width: 15mm;
            padding: 0;
            background: linear-gradient(to bottom, #999 33%, transparent 33%, transparent 66%, #999 66%) repeat-y center/1px 6px;
        }

        /* Colonne sinistre (Cliente) */
        .col-left-name {
            width: calc((100% - 15mm) / 2 * 0.60);
        }

        .col-left-qty {
            width: calc((100% - 15mm) / 2 * 0.16);
        }

        .col-left-total {
            width: calc((100% - 15mm) / 2 * 0.24);
        }

        /* Colonne destre (Cucina) */
        .col-right-name {
            width: calc((100% - 15mm) / 2 * 0.60);
        }

        .col-right-qty {
            width: calc((100% - 15mm) / 2 * 0.16);
        }

        .col-right-total {
            width: calc((100% - 15mm) / 2 * 0.24);
        }

        .logo-img {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .header-title {
            font-size: 18pt;
            font-weight: bold;
            line-height: 1.2;
            margin: 2mm 0;
        }

        .header-subtitle {
            font-size: 9pt;
            margin: 1mm 0;
            font-weight: normal;
        }

        .item-name {
            font-size: 10pt;
            text-align: left;
        }

        .item-qty {
            font-size: 10pt;
            text-align: center;
            font-weight: bold;
        }

        .item-total {
            font-size: 11pt;
            text-align: center;
            font-weight: bold;
        }

        .note-row {
            font-size: 8pt;
            font-style: italic;
            padding: 2mm !important;
            border-top: 1px dashed #666;
        }

        .footer-label {
            font-size: 11pt;
            font-weight: bold;
            text-align: left;
        }

        .footer-value {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
        }

        .copy-label {
            font-size: 10pt;
            text-align: center;
            padding: 2mm 0;
            font-weight: bold;
            border-top: 2px solid #000;
        }

        .note-header {
            font-weight: bold;
            font-size: 9pt;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="table-page">
        <table>
            <thead>
            <tr>
                @if($logoPath)
                    <th style="text-align: center; padding: 6px;">
                        <img src="{{ $logoPath }}" alt="{{$name}}" class="logo-img">
                    </th>
                @endif
                <th @if($logoPath) colspan="2" @else colspan="3" @endif style="text-align: center; padding: 6px;">
                    <h2 class="header-title">Ordine #{{ $order->number_queue }}</h2>
                    <h5 class="header-subtitle">{{$name}}</h5>
                </th>
                <th class="separator-col">&nbsp;</th>
                @if($logoPath)
                    <th style="text-align: center; padding: 6px;">
                        <img src="{{ $logoPath }}" alt="{{$name}}" class="logo-img">
                    </th>
                @endif
                <th @if($logoPath) colspan="2" @else colspan="3" @endif style="text-align: center; padding: 6px;">
                    <h2 class="header-title">Ordine #{{ $order->number_queue }}</h2>
                    <h5 class="header-subtitle">{{$name}}</h5>
                </th>
            </tr>
            <tr>
                <th class="col-left-name" style="text-align: left;">Nome</th>
                <th class="col-left-qty" style="text-align: center;">Qta</th>
                <th class="col-left-total" style="text-align: center;">Totale</th>
                <th class="separator-col">&nbsp;</th>
                <th class="col-right-name" style="text-align: left;">Nome</th>
                <th class="col-right-qty" style="text-align: center;">Qta</th>
                <th class="col-right-total" style="text-align: center;">Totale</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($order->orderItems as $item)
                <tr>
                    <td class="item-name" @if($item->note) rowspan="2" @endif>{{ $item->name }}</td>
                    <td class="item-qty">{{ $item->quantity }}</td>
                    <td class="item-total">{{ $item->row_amount }} €</td>
                    <td class="separator-col">&nbsp;</td>
                    <td class="item-name" @if($item->note) rowspan="2" @endif>{{ $item->name }}</td>
                    <td class="item-qty">{{ $item->quantity }}</td>
                    <td class="item-total">{{ $item->row_amount }} €</td>
                </tr>
                @if($item->note)
                    <tr>
                        <td class="note-row" colspan="2"><strong>Nota:</strong> {{ $item->note }}</td>
                        <td class="separator-col">&nbsp;</td>
                        <td class="note-row" colspan="2"><strong>Nota:</strong> {{ $item->note }}</td>
                    </tr>
                @endif
            @endforeach
            @if($order->note)
                <tr>
                    <td class="note-header" colspan="3">Note Ordine</td>
                    <td class="separator-col">&nbsp;</td>
                    <td class="note-header" colspan="3">Note Ordine</td>
                </tr>
                <tr>
                    <td class="note-row" colspan="3">{{ $order->note }}</td>
                    <td class="separator-col">&nbsp;</td>
                    <td class="note-row" colspan="3">{{ $order->note }}</td>
                </tr>
            @endif
            </tbody>
            <tfoot>
            <tr>
                <td class="footer-label" colspan="2">Totale</td>
                <td class="footer-value">{{ $order->total_amount }} €</td>
                <td class="separator-col">&nbsp;</td>
                <td class="footer-label" colspan="2">Totale</td>
                <td class="footer-value">{{ $order->total_amount }} €</td>
            </tr>
            <tr>
                <td class="footer-label" colspan="2">Quantità</td>
                <td class="footer-value">{{ $order->getOrderItemsQty() }}</td>
                <td class="separator-col">&nbsp;</td>
                <td class="footer-label" colspan="2">Quantità</td>
                <td class="footer-value">{{ $order->getOrderItemsQty() }}</td>
            </tr>
            <tr>
                <td class="copy-label" colspan="3">Copia Cliente<br>{{$userSel}}</td>
                <td class="separator-col">&nbsp;</td>
                <td class="copy-label" colspan="3">Copia Cucina<br>{{$userSel}}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
