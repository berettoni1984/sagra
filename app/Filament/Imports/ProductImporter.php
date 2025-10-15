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
        $keyColumnName = $this->columnMap['name'] ?? 'name';
        $keyColumnPrice = $this->columnMap['price'] ?? 'price';
        $keyColumnIsDisabled = $this->columnMap['is_disabled'] ?? 'is_disabled';
        $keyColumnOrder = $this->columnMap['order'] ?? 'order';
        $keyColumnStock = $this->columnMap['stock'] ?? 'stock';
        $keyColumnBackorder = $this->columnMap['backorder'] ?? 'backorder';
        $keyColumnQueue = $this->columnMap['queues'] ?? 'queues';
        $queues = $this->data[$keyColumnQueue] ?? [];
        if (is_string($queues)) {
            $queues = explode(',', $queues);
        }
        $queuesSelected = Queue::whereIn('comment', $queues)->pluck('id')->toArray();

        if ($this->data[$keyColumnName] ?? null) {
            $product = Product::create([
                'name' => $this->data[$keyColumnName],
                'price' => $this->data[$keyColumnPrice] ?? 0,
                'is_disabled' => (bool) ($this->data[$keyColumnIsDisabled] ?? true),
                'order' => (int) ($this->data[$keyColumnOrder] ?? (Product::max('order') ?? 0) + 1),
                'stock' => $this->data[$keyColumnStock] ?? 0,
                'backorder' => (bool) ($this->data[$keyColumnBackorder] ?? false),
            ]);
            $product->queues()->sync($queuesSelected);
            $this->data[$keyColumnId] = $product->id;
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
        $keyColumnQueue = $this->columnMap['queues'] ?? 'queues';
        $queues = $this->data[$keyColumnQueue] ?? [];
        $queues = explode(',', $queues);
        $queuesSelected = Queue::whereIn('comment', $queues)->pluck('id')->toArray();
        /** @var Product $record */
        $record = $this->record;
        $record->queues()->sync($queuesSelected);

    }
}
