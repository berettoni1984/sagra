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

    /**
     * Riempie l'attributo solo se il CSV porta effettivamente un valore.
     *
     * Tutte le colonne di products sono NOT NULL, ma il cast di Filament
     * trasforma una cella vuota in NULL: senza questo filtro un prezzo vuoto
     * faceva fallire l'insert e una giacenza vuota azzerava a NULL la colonna
     * di un prodotto esistente (SQL 1048).
     */
    private static function fillIfPresent(string $attribute): \Closure
    {
        return static function (?Model $record, mixed $state) use ($attribute): void {
            if ($record !== null && $state !== null) {
                $record->setAttribute($attribute, $state);
            }
        };
    }

    public static function getColumns(): array
    {
        return [
            // L'id serve solo a ritrovare il record: non va scritto sul model,
            // altrimenti una cella vuota produce "set id = NULL" (SQL 1048)
            // quando il prodotto viene ritrovato per nome.
            ImportColumn::make('id')
                ->requiredMapping()
                ->numeric()
                ->fillRecordUsing(fn ($record) => $record)
                ->label(__('filament.ID')),
            // Le regole validano solo i limiti, senza 'required': un import di
            // solo aggiornamento (id + giacenza) non mappa name e price, e
            // renderli obbligatori farebbe fallire ogni riga.
            ImportColumn::make('name')
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(self::fillIfPresent('name'))
                ->label(__('filament.Product Name')),
            ImportColumn::make('price')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0', 'max:999999.99'])
                ->fillRecordUsing(self::fillIfPresent('price'))
                ->label(__('filament.Price')),
            // products.stock è smallint: oltre 32767 il database dava un errore
            // grezzo (SQL 22003) invece di un errore di validazione leggibile
            ImportColumn::make('stock')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:-32768', 'max:32767'])
                ->fillRecordUsing(self::fillIfPresent('stock'))
                ->label(__('filament.Stock')),
            ImportColumn::make('backorder')
                ->boolean()
                ->fillRecordUsing(self::fillIfPresent('backorder'))
                ->label(__('filament.Backorder')),
            ImportColumn::make('is_disabled')
                ->boolean()
                ->fillRecordUsing(self::fillIfPresent('is_disabled'))
                ->label(__('filament.Disabled')),
            ImportColumn::make('queues')
                ->fillRecordUsing(fn ($record) => $record)
                ->label(__('filament.queue_label_plural')),
            ImportColumn::make('order')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0'])
                ->fillRecordUsing(self::fillIfPresent('order'))
                ->label(__('filament.product_order')),

        ];
    }

    public function resolveRecord(): ?Model
    {
        // remapData() + castData() girano prima di resolveRecord() e scrivono i
        // valori GIÀ castati sulla chiave col nome canonico della colonna.
        // Leggendo invece $this->columnMap[...] (l'header del CSV) si otterrebbe
        // la stringa grezza, e (bool) 'FALSE' vale true.
        $product = Product::find($this->data['id'] ?? null);

        if ($product instanceof Model) {
            return $product;
        }

        // Fallback sul nome: senza questo una riga con la colonna id vuota, o con
        // un id proveniente da un altro ambiente, creava un doppione del prodotto
        // invece di aggiornarlo.
        $name = $this->data['name'] ?? null;

        if (blank($name)) {
            return null;
        }

        $product = Product::firstWhere('name', $name);

        if ($product instanceof Model) {
            return $product;
        }

        // Si restituisce un model NON salvato: così Filament esegue validazione,
        // fill e save, e fa girare afterSave() anche per i nuovi prodotti.
        // Restituendo null (come prima) quei passaggi venivano tutti saltati, le
        // regole delle colonne non venivano mai applicate e la riga risultava
        // "importata" pur non avendo cambiato nulla.
        return new Product([
            // La posizione va in coda al listino se il CSV non la indica.
            'order' => (int) ($this->data['order'] ?? ((int) Product::max('order') + 1)),
            // products.price è NOT NULL e senza default: fillRecord() lo
            // sovrascrive se la colonna è mappata.
            'price' => 0,
        ]);
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
