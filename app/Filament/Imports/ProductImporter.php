<?php

namespace App\Filament\Imports;

use App\Models\Product;
use App\Models\Queue;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

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
            // Il nome è l'unica chiave dell'import: la colonna id non c'è più,
            // perché un id proveniente da un altro ambiente vinceva sul nome e
            // faceva aggiornare il prodotto sbagliato. Il confronto ignora
            // maiuscole/minuscole e spazi ai bordi (Product::findByName()).
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
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
        // castStateItem() applica già trim(), qui si toglie anche lo spazio
        // insecabile dei CSV di Excel.
        $name = Product::normalizeName((string) ($this->data['name'] ?? ''));

        if ($name === '') {
            // Restituendo null Filament salterebbe validazione, fill, save e
            // afterSave() contando però la riga fra quelle importate: una riga
            // senza nome sparirebbe in silenzio. Con l'eccezione finisce nel CSV
            // degli errori con un messaggio leggibile.
            throw new RowImportFailedException(__('filament.import_row_without_product_name'));
        }

        // Il nome normalizzato è anche quello che verrà scritto sul model: il
        // listino del CSV fa da riferimento anche per maiuscole e spaziatura.
        $this->data['name'] = $name;

        $product = Product::findByName($name);

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

    /**
     * I chunk dell'import girano in job paralleli: due righe con lo stesso nome
     * in chunk diversi possono passare entrambe da resolveRecord() senza vedersi
     * (nessuna delle due transazioni è ancora committata) e la seconda sbatte
     * sull'indice unico di products.name. Senza questa conversione la riga
     * risultava fallita con l'errore SQL grezzo 1062.
     */
    public function saveRecord(): void
    {
        try {
            parent::saveRecord();
        } catch (UniqueConstraintViolationException) {
            throw new RowImportFailedException(__('filament.import_duplicate_product_name', [
                'name' => (string) ($this->data['name'] ?? ''),
            ]));
        }
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
