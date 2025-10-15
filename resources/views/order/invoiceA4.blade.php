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
        .table-page {
            min-height: 12cm;
        }
        /* style sheet for "A4" printing */
        @media print and (width: 29.7cm) and (height: 21cm) {
            @page {
                margin: 10mm;

            .table-page {
                min-height: 17cm;
            }
            }
        }
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 10mm 10mm;
        .table-page {
            min-height: 17cm;
        }
    </style>
</head>
<body>
    <div class="break-inside-avoid table-page" >
        <table class="table-fixed w-full mt-2">
            <thead>
            <tr>
                @if($logoPath)
                    <th class="text-center">
                        <img src="{{ $logoPath }}" alt="{{$name}}" class="w-24 h-24">
                    </th>
                @endif
                <th @if($logoPath) colspan="2" @else colspan="3" @endif>
                    <h2 class="text-4xl font-bold">Ordine #{{ $order->number_queue }}</h2>
                    <h5 class="mb-1">{{$name}}</h5>
                </th>
                <th> &nbsp; </th>
                    @if($logoPath)
                        <th class="text-center">
                            <img src="{{ $logoPath }}" alt="{{$name}}" class="w-24 h-24">
                        </th>
                    @endif
                    <th @if($logoPath) colspan="2" @else colspan="3" @endif>
                        <h2 class="text-4xl font-bold">Ordine #{{ $order->number_queue }}</h2>
                        <h5 class="mb-1">{{$name}}</h5>
                    </th>
            </tr>
            <tr>
                <th class="w-1/3 text-left text-md border">Nome</th>
                <th class="w-1/3 text-center text-md border">Qta</th>
                <th class="w-1/3 text-center text-lg border">Totale</th>
                <th> &nbsp; </th>
                <th class="w-1/3 text-left text-md border">Nome</th>
                <th class="w-1/3 text-center text-md border">Qta</th>
                <th class="w-1/3 text-center text-lg border">Totale</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($order->orderItems as $item)
                <tr>
                    <td class="text-left text-md border" @if($item->note)rowspan="2" @endif>{{ $item->name }}</td>
                    <td class="text-center text-md border">{{ $item->quantity }} x {{ $item->amount }} €</td>
                    <td class="text-center text-lg border">{{ $item->row_amount }} €</td>
                    <td> &nbsp; </td>
                    <td class="text-left text-md border" @if($item->note)rowspan="2" @endif>{{ $item->name }}</td>
                    <td class="text-center text-md border">{{ $item->quantity }}</td>
                    <td class="text-center text-lg border">{{ $item->row_amount }} €</td>
                </tr>
                @if($item->note)
                    <tr>
                        <td colspan="2"></td>
                        <td> &nbsp; </td>
                        <td class="text-left text-md border" colspan="2">{{ $item->note }}</td>
                    </tr>
                @endif
            @endforeach
            @if($order->note)
                <tr>
                    <td colspan="3"></td>
                    <td> &nbsp; </td>
                    <td class="text-left border" colspan="3">Note Ordine</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td> &nbsp; </td>
                    <td class="text-left border" colspan="3">{{ $order->note }}</td>
                </tr>
            @endif
            </tbody>
            <tfoot>
            <tr>
                <td class="text-left font-bold text-lg border" colspan="2">Totale</td>
                <td class="text-center font-bold text-lg border">{{ $order->total_amount }} €</td>
                <td> &nbsp; </td>
                <td class="text-left font-bold text-lg border" colspan="2">Totale</td>
                <td class="text-center font-bold text-lg border">{{ $order->total_amount }} €</td>
            </tr>
            <tr>
                <td class="text-left font-bold text-lg border" colspan="2">Quantità</td>
                <td class="text-center font-bold text-lg border">{{ $order->getOrderItemsQty() }}</td>
                <td> &nbsp; </td>
                <td class="text-left font-bold text-lg border" colspan="2">Quantità</td>
                <td class="text-center font-bold text-lg border">{{ $order->getOrderItemsQty() }}</td>
            </tr>
            <tr>
                <td class="text-center text-lg" colspan="3">Copia Cliente <br>{{$userSel}}</td>
                <td> &nbsp; </td>
                <td class="text-center text-lg" colspan="3">Copia Cucina <br>{{$userSel}}</td>
            </tr>
        </table>
    </div>
</body>
</html>
