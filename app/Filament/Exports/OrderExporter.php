<?php

namespace App\Filament\Exports;

use App\Models\Config;
use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    /**
     * Le colonne leggono queue e user: senza eager loading ogni riga esportata
     * le caricava una a una.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Order>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    public static function modifyQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->with(['queue', 'user']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label(__('filament.Order Number')),
            ExportColumn::make('queue.label')
                ->label(__('filament.Order Queue')),
            ExportColumn::make('id')
                ->enabledByDefault(false)
                ->label(__('filament.Order ID')),
            ExportColumn::make('created_at')
                ->label(__('filament.Created At'))
                ->state(function (Order $record): ?string {
                    // Config::value() è memoizzato: prima una query per riga.
                    $timezone = Config::value('timezone') ?: config('app.timezone');

                    return $record->created_at?->timezone($timezone)->format('Y-m-d H:i:s');
                }),
            ExportColumn::make('note')
                ->enabledByDefault(false)
                ->label(__('filament.Order Note')),
            ExportColumn::make('total_amount')
                ->label(__('filament.Total €')),
            ExportColumn::make('total_paid')
                ->label(__('filament.Total Paid €')),
            ExportColumn::make('user_id')
                ->enabledByDefault(false)
                ->formatStateUsing(fn ($record) => $record->user->code ?? '')
                ->label(__('filament.user_label')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('filament.Your order export has completed and :count :row exported.', [
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
