<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\OrderResource;
use App\Models\Queue;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resetNumber')
                ->label(__('filament.reset_number'))
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    Queue::query()->update([
                        'order_number' => 0,
                        'reset_at' => Carbon::now(),
                    ]);
                })
                ->color('danger')
                ->requiresConfirmation(),
            Actions\ExportAction::make()
                ->label(__('filament.export_orders'))
                ->exporter(OrderExporter::class)
                ->formats([
                    Actions\Exports\Enums\ExportFormat::Xlsx,
                    Actions\Exports\Enums\ExportFormat::Csv,
                ]),
            Actions\CreateAction::make(),

        ];
    }
}
