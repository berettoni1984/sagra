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
            size: A5 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 12pt;
        }

        .table-page {
            page-break-after: always;
            page-break-inside: avoid;
            width: 100%;
            max-width: 14.8cm;
            margin: 0 auto;
            padding: 2mm 0;
        }

        .table-page:last-child {
            page-break-after: auto;
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
        }

        th, td {
            border: 1px solid #000;
            padding: 3mm 2mm;
            vertical-align: top;
        }

        thead th {
            font-weight: bold;
        }

        .logo-img {
            max-width: 70px;
            max-height: 70px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .header-title {
            font-size: 20pt;
            font-weight: bold;
            line-height: 1.2;
            margin: 2mm 0;
        }

        .header-subtitle {
            font-size: 10pt;
            margin: 1mm 0;
            font-weight: normal;
        }

        .item-name {
            font-size: 11pt;
            text-align: left;
        }

        .item-qty {
            font-size: 11pt;
            text-align: center;
            font-weight: bold;
        }

        .item-total {
            font-size: 12pt;
            text-align: center;
            font-weight: bold;
        }

        .note-row {
            font-size: 9pt;
            font-style: italic;
            padding: 2mm !important;
            border-top: none !important;
        }

        .has-note {
            border-bottom: none !important;
        }

        .footer-label {
            font-size: 12pt;
            font-weight: bold;
            text-align: left;
        }

        .footer-value {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
        }

        .copy-label {
            font-size: 11pt;
            text-align: center;
            padding: 2mm 0;
            font-weight: bold;
            border-top: 2px solid #000;
        }

        .note-header {
            font-weight: bold;
            font-size: 10pt;
            text-decoration: underline;
        }
    </style>
</head>
<body>
@foreach(['Cucina','Cliente'] as $type)
    <div class="table-page">
        <table>
            <thead>
            <tr>
                @if($logoPath)
                <th style="text-align: center; padding: 8px;">
                    <img src="{{ $logoPath }}" alt="{{$name}}" class="logo-img">
                </th>
                @endif
                <th @if($logoPath) colspan="2" @else colspan="3" @endif style="text-align: center; padding: 8px;">
                    <h2 class="header-title">Ordine #{{ $order->number_queue }}</h2>
                    <h5 class="header-subtitle">{{$name}}</h5>
                </th>
            </tr>
            <tr>
                <th style="width: 50%; text-align: left;">Nome</th>
                <th style="width: 20%; text-align: center;">Qta</th>
                <th style="width: 30%; text-align: center;">Totale</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($order->orderItems as $item)
                <tr>
                    <td class="item-name @if($item->note) has-note @endif">{{ $item->name }}</td>
                    <td class="item-qty @if($item->note) has-note @endif">{{ $item->quantity }}</td>
                    <td class="item-total @if($item->note) has-note @endif">{{ $item->row_amount }} €</td>
                </tr>
                @if($item->note)
                    <tr>
                        <td class="note-row" colspan="3"><strong>Nota:</strong> {{ $item->note }}</td>
                    </tr>
                @endif
            @endforeach
            @if($order->note)
                <tr>
                    <td class="note-header" colspan="3">Note Ordine</td>
                </tr>
                <tr>
                    <td class="note-row" colspan="3">{{ $order->note }}</td>
                </tr>
            @endif
            </tbody>
            <tfoot>
            <tr>
                <td class="footer-label" colspan="2">Totale</td>
                <td class="footer-value">{{ $order->total_amount }} €</td>
            </tr>
            <tr>
                <td class="footer-label" colspan="2">Quantità</td>
                <td class="footer-value">{{ $order->getOrderItemsQty() }}</td>
            </tr>
            <tr>
                <td class="copy-label" colspan="3">Copia {{$type}} {{$userSel}}</td>
            </tr>
            </tfoot>
        </table>
    </div>
@endforeach
</body>
</html>
