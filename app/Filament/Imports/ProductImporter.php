<?php

namespace App\Filament\Imports;

use App\Models\Product;
use App\Models\Queue;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->requiredMapping()
                ->numeric()
                ->label(__('filament.ID')),
            ImportColumn::make('name')
                ->label(__('filament.Product Name')),
            ImportColumn::make('price')
                ->numeric()
                ->label(__('filament.Price')),
            ImportColumn::make('stock')
                ->numeric()
                ->label(__('filament.Stock')),
            ImportColumn::make('backorder')
                ->boolean()
                ->label(__('filament.Backorder')),
            ImportColumn::make('is_disabled')
                ->boolean()
                ->label(__('filament.Disabled')),
            ImportColumn::make('queues')
                ->fillRecordUsing(fn ($record) => $record)
                ->label(__('filament.queue_label_plural')),
            ImportColumn::make('order')
                ->numeric()
                ->label(__('filament.product_order')),

        ];
    }

    public function resolveRecord(): ?Model
    {
        $keyName = app(static::getModel())->getKeyName();
        $keyColumnId = $this->columnMap[$keyName] ?? $keyName;

        $product = static::getModel()::find($this->data[$keyColumnId] ?? null);
        if ($product instanceof Model) {
            return $product;
        }

        // remapData() + castData() girano prima di resolveRecord() e scrivono i
        // valori GIÀ castati sulla chiave col nome canonico della colonna.
        // Leggendo invece $this->columnMap[...] (l'header del CSV) si otteneva la
        // stringa grezza: (bool) 'FALSE' e (bool) 'no' valgono true, quindi un
        // export con backorder = FALSE creava prodotti in backorder, disattivando
        // ogni controllo di giacenza.
        $queues = $this->data['queues'] ?? [];
        if (is_string($queues)) {
            $queues = explode(',', $queues);
        }
        $queuesSelected = Queue::whereIn('comment', $queues)->pluck('id')->toArray();

        if ($this->data['name'] ?? null) {
            $product = Product::create([
                'name' => $this->data['name'],
                'price' => $this->data['price'] ?? 0,
                // Default false: con true un listino name;price creava tutti i
                // prodotti disattivati e invisibili in cassa.
                'is_disabled' => $this->data['is_disabled'] ?? false,
                'order' => (int) ($this->data['order'] ?? (Product::max('order') ?? 0) + 1),
                'stock' => $this->data['stock'] ?? 0,
                'backorder' => $this->data['backorder'] ?? false,
            ]);
            $product->queues()->sync($queuesSelected);
        }

        return null;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('filament.Your product import has completed and :count :row imported.', [
            'count' => number_format($import->successful_rows),
            'row' => str('row')->plural($import->successful_rows),
        ]);
        $failedRowsCount = $import->getFailedRowsCount();
        if ($failedRowsCount) {

            $body .= __('filament. :count :row failed to import.', [
                'count' => number_format($failedRowsCount),
                'row' => str('row')->plural($failedRowsCount),
            ]);
        }

        return $body;
    }

    public function afterSave(): void
    {
        $raw = $this->data['queues'] ?? null;

        // Colonna 'queues' non mappata: qui $raw era [] ed explode() sollevava un
        // TypeError, così ogni riga veniva contata come fallita pur essendo già
        // stata salvata ("0 importati, 40 falliti" con 40 prodotti aggiornati).
        if (! is_string($raw)) {
            return;
        }

        $codes = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $code): bool => $code !== '',
        ));

        // Cella vuota o nessuna coda corrispondente: sync([]) staccherebbe il
        // prodotto da TUTTE le code, facendolo sparire da ogni fila del POS senza
        // alcun avviso e senza possibilità di annullare. Meglio non toccare nulla.
        if ($codes === []) {
            return;
        }

        $queuesSelected = Queue::whereIn('comment', $codes)->pluck('id')->toArray();
        if ($queuesSelected === []) {
            return;
        }

        /** @var Product $record */
        $record = $this->record;
        $record->queues()->sync($queuesSelected);
    }
}
