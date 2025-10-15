<?php

namespace App\Filament\Exports;

use App\Models\Config;
use App\Models\OrderItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderItemExporter extends Exporter
{
    protected static ?string $model = OrderItem::class;

    public static function getCsvDelimiter(): string
    {
        return ';';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order.number')
                ->label(__('filament.Order Number')),
            ExportColumn::make('order.queue.label')
                ->label(__('filament.Order Queue')),
            ExportColumn::make('order.id')
                ->enabledByDefault(false)
                ->label(__('filament.Order ID')),
            ExportColumn::make('id')
                ->enabledByDefault(false)
                ->label(__('filament.Order Item ID')),
            ExportColumn::make('product.id')
                ->enabledByDefault(false)
                ->label(__('filament.Product ID')),
            ExportColumn::make('name')
                ->label(__('filament.Product Name')),
            ExportColumn::make('quantity')
                ->label(__('filament.Qty')),
            ExportColumn::make('amount')
                ->enabledByDefault(false)
                ->label(__('filament.Price €')),
            ExportColumn::make('row_amount')
                ->label(__('filament.Row Total €')),
            ExportColumn::make('note')
                ->enabledByDefault(false)
                ->label(__('filament.Order Item Note')),
            ExportColumn::make('created_at')
                ->label(__('filament.Created At'))
                ->state(function (OrderItem $record): ?string {
                    $timezone = Config::whereCode('timezone')->first()->config_value ?? config('app.timezone');

                    return $record->order->created_at?->timezone($timezone)->format('Y-m-d H:i:s');
                }),
            ExportColumn::make('order.note')
                ->enabledByDefault(false)
                ->label(__('filament.Order Note')),
            ExportColumn::make('order.total_amount')
                ->label(__('filament.Total €')),
            ExportColumn::make('order.total_paid')
                ->label(__('filament.Total Paid €')),
            ExportColumn::make('user_id')
                ->enabledByDefault(false)
                ->formatStateUsing(fn ($record) => $record->order->user->code ?? '')
                ->label(__('filament.user_label')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('filament.Your order item export has completed and :count :row exported.', [
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
