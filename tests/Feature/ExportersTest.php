<?php

use App\Filament\Exports\OrderExporter;
use App\Filament\Exports\OrderItemExporter;
use App\Filament\Exports\ProductExporter;
use App\Models\Config;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;

/**
 * Gli exporter vengono pilotati come fa Filament: si costruisce l'exporter con
 * una mappa di colonne e si invoca su un record, ottenendo la riga esportata.
 */
function exportRow(string $exporterClass, array $columns, $record): array
{
    $export = Export::create([
        'file_disk' => 'local',
        'file_name' => 'export.csv',
        'exporter' => $exporterClass,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => admin()->id,
    ]);

    $exporter = new $exporterClass($export, array_combine($columns, $columns), []);

    return $exporter($record);
}

beforeEach(function () {
    setConfig('timezone', 'Europe/Rome');

    $this->utente = User::factory()->create(['code' => 'C7']);
    $this->coda = Queue::factory()->create(['name' => 'PIZZA', 'comment' => 'pizza']);
    $this->prodotto = Product::factory()->create(['name' => 'PIZZA MARGHERITA', 'price' => '5.00']);

    $this->ordine = Order::factory()->create([
        'queue_id' => $this->coda->id,
        'user_id' => $this->utente->id,
        'number' => 42,
        'total_amount' => '10.00',
        'total_paid' => '10.00',
    ]);
    $this->riga = OrderItem::factory()->of($this->prodotto, 2)->create(['order_id' => $this->ordine->id]);
});

it('esporta la riga di un ordine con i valori attesi', function () {
    $row = exportRow(OrderExporter::class, ['number', 'queue.label', 'total_amount', 'user_id'], $this->ordine);

    expect($row)->toBe(['42', 'PIZZA pizza', '10.00', 'C7']);
});

it('esporta la riga d ordine con i dati dell ordine collegato', function () {
    // fresh(): un export reale legge dal database, dove row_amount e' decimal(8,2)
    $row = exportRow(OrderItemExporter::class, ['order.number', 'name', 'quantity', 'row_amount'], $this->riga->fresh());

    expect($row)->toBe(['42', 'PIZZA MARGHERITA', '2', '10.00']);
});

it('esporta la data nel fuso configurato tenendo conto dell ora legale', function () {
    $this->ordine->forceFill(['created_at' => '2026-01-15 10:00:00'])->saveQuietly();

    $row = exportRow(OrderExporter::class, ['created_at'], $this->ordine->fresh());

    // 'Europe/Rome' a gennaio e' +01:00; l'abbreviazione CEST dava 12:00 tutto l anno
    expect($row)->toBe(['2026-01-15 11:00:00']);
});

it('esporta il codice operatore vuoto per un ordine senza utente', function () {
    $senzaUtente = Order::factory()->create(['user_id' => null]);

    expect(exportRow(OrderExporter::class, ['user_id'], $senzaUtente))->toBe(['']);
});

it('esporta una riga il cui prodotto e stato cancellato conservando lo storico', function () {
    Product::whereKey($this->prodotto->id)->delete();

    $row = exportRow(OrderItemExporter::class, ['name', 'quantity', 'amount'], $this->riga->fresh());

    expect($this->riga->fresh()->product_id)->toBeNull()
        ->and($row)->toBe(['PIZZA MARGHERITA', '2', '5.00']);
});

it('l export degli ordini carica coda e utente in eager loading', function () {
    Order::factory()->count(15)->create(['queue_id' => $this->coda->id, 'user_id' => $this->utente->id]);

    $records = OrderExporter::modifyQuery(Order::query())->get();

    $queries = countQueries(function () use ($records) {
        foreach ($records as $record) {
            $record->queue?->label;
            $record->user->code ?? '';
        }
    });

    // erano due query per riga esportata
    expect($records)->toHaveCount(16)->and($queries)->toBe(0);
});

it('l export delle righe carica ordine coda utente e prodotto in eager loading', function () {
    OrderItem::factory()->count(15)->create(['order_id' => $this->ordine->id]);

    $records = OrderItemExporter::modifyQuery(OrderItem::query())->get();

    $queries = countQueries(function () use ($records) {
        foreach ($records as $record) {
            $record->order->number;
            $record->order->queue?->label;
            $record->order->user->code ?? '';
            $record->product?->id;
        }
    });

    expect($records)->toHaveCount(16)->and($queries)->toBe(0);
});

it('legge il fuso orario una sola volta e non per riga esportata', function () {
    Config::flushValueCache();

    $queries = countQueries(function () {
        foreach (range(1, 21) as $ignored) {
            Config::value('timezone');
        }
    });

    expect($queries)->toBe(1);
});

it('usa il punto e virgola come separatore per le righe d ordine', function () {
    expect(OrderItemExporter::getCsvDelimiter())->toBe(';');
});

it('espone le colonne attese', function (string $exporter, array $attese) {
    $nomi = collect($exporter::getColumns())->map(fn ($c) => $c->getName())->all();

    expect($nomi)->toContain(...$attese);
})->with([
    'ordini' => [OrderExporter::class, ['number', 'queue.label', 'created_at', 'total_amount', 'total_paid', 'user_id']],
    'righe ordine' => [OrderItemExporter::class, ['order.number', 'name', 'quantity', 'row_amount', 'created_at']],
    'prodotti' => [ProductExporter::class, ['name', 'price', 'stock']],
]);
