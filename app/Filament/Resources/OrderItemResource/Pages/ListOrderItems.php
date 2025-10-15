<?php

namespace App\Filament\Resources\OrderItemResource\Pages;

use App\Filament\Exports\OrderItemExporter;
use App\Filament\Resources\OrderItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrderItems extends ListRecords
{
    protected static string $resource = OrderItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->label(__('filament.export_order_items'))
                ->exporter(OrderItemExporter::class)
                ->formats([
                    Actions\Exports\Enums\ExportFormat::Xlsx,
                    Actions\Exports\Enums\ExportFormat::Csv,
                ]),
        ];
    }
}
