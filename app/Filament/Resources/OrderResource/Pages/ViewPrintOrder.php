<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPrintOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.view-record';

    protected function getHeaderActions(): array
    {
        return [
            // Order::max('id') è globale: se un'altra cassa registra un ordine dopo,
            // il pulsante spariva anche sull'ordine che l'utente può ancora correggere.
            // canEdit() è la regola vera (ultimo ordine dell'utente corrente).
            Actions\EditAction::make()
                ->hidden(fn ($record) => ! OrderResource::canEdit($record))
                ->url(fn ($record) => OrderResource::getUrl('edit', ['record' => $record])),
            Actions\Action::make('print')
                ->label(__('filament.Print'))
                ->url(fn () => OrderResource::getUrl('print', ['record' => $this->record, 'print' => true]))
                ->icon('heroicon-o-printer'),
            Actions\CreateAction::make('quickCreate')
                ->label(__('filament.Quick Create'))
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->url(fn () => OrderResource::getUrl('quick-create')),

        ];
    }
}
