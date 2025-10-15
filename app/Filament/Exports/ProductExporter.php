<?php

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getCsvDelimiter(): string
    {
        return ';';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('filament.ID')),
            ExportColumn::make('name')
                ->label(__('filament.Product Name')),
            ExportColumn::make('price')
                ->enabledByDefault(false)
                ->label(__('filament.Price')),
            ExportColumn::make('stock')
                ->enabledByDefault(false)
                ->label(__('filament.Stock')),
            ExportColumn::make('backorder')
                ->enabledByDefault(false)
                ->label(__('filament.Backorder')),
            ExportColumn::make('is_disabled')
                ->enabledByDefault(false)
                ->label(__('filament.Disabled')),
            ExportColumn::make('order')
                ->enabledByDefault(false)
                ->label(__('filament.product_order')),
            ExportColumn::make('queues')
                ->enabledByDefault(false)
                ->formatStateUsing(static function (Product $record): string {
                    return $record->queues->pluck('comment')->implode(',');
                })
                ->label(__('filament.queue_label_plural')),
            ExportColumn::make('order_items_sum_quantity')
                ->label(__('filament.Items')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {

        $body = __('filament.Your product export has completed and :count :row exported.', [
            'count' => number_format($export->successful_rows),
            'row' => str('row')->plural($export->successful_rows),
        ]);
        $failedRowsCount = $export->getFailedRowsCount();
        if ($failedRowsCount) {
            $body .= __('filament. :count :row failed to export.', [
                'count' => number_format($failedRowsCount),
                'row' => str('row')->plural($failedRowsCount),
            ]);
        }

        return $body;
    }
}
