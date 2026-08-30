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
            // Azzera la numerazione di TUTTE le code: a metà servizio fa ripartire
            // i numeri da 1 mentre gli ordini già emessi li mantengono, e al
            // ritiro due scontrini diventano indistinguibili. Solo admin.
            Actions\Action::make('resetNumber')
                ->label(__('filament.reset_number'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->action(function () {
                    abort_unless(auth()->user()?->isAdmin() ?? false, 403);

                    Queue::query()->update([
                        'order_number' => 0,
                        'reset_at' => Carbon::now(),
                    ]);
                })
                ->color('danger')
                ->requiresConfirmation(),
            Actions\ExportAction::make()
                ->visible(fn (): bool => ! (auth()->user()?->isCameriere() ?? false))
                ->label(__('filament.export_orders'))
                ->exporter(OrderExporter::class)
                ->formats([
                    Actions\Exports\Enums\ExportFormat::Xlsx,
                    Actions\Exports\Enums\ExportFormat::Csv,
                ]),
            // Unica via per creare un ordine: la cassa rapida. Il form classico
            // di creazione non esiste più.
            Actions\Action::make('quickCreate')
                ->visible(fn (): bool => ! (auth()->user()?->isCameriere() ?? false))
                ->label(__('filament.Quick Create'))
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->url(fn () => OrderResource::getUrl('quick-create')),
        ];
    }
}
