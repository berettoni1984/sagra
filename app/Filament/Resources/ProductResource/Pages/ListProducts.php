<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\ExportAction::make()
                ->label(__('filament.export_products'))
                ->exporter(ProductExporter::class)

                ->formats([
                    Actions\Exports\Enums\ExportFormat::Xlsx,
                    Actions\Exports\Enums\ExportFormat::Csv,
                ]),
            Actions\ImportAction::make()
                ->label(__('filament.import_products'))
                ->importer(ProductImporter::class),

        ];
    }
}
