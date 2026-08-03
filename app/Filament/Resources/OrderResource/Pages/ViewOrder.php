<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStockService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Prima la cancellazione da qui non restituiva la giacenza, mentre
            // quella dalla pagina Modifica sì: annullare un ordine da questa
            // pagina lasciava i prodotti falsamente esauriti.
            // Un'azione aggiunta a mano non consulta da sola OrderResource::canDelete(),
            // quindi l'autorizzazione va collegata esplicitamente: senza questo una
            // cassa poteva annullare gli ordini di altri operatori.
            Actions\DeleteAction::make()
                ->visible(fn ($record) => OrderResource::canDelete($record))
                ->before(static function (Order $record) {
                    app(OrderStockService::class)->restore($record);
                }),
            // La condizione era invertita (=== invece di !==): il pulsante compariva
            // proprio sugli ordini che canEdit() non permette di modificare.
            Actions\EditAction::make()
                ->hidden(fn ($record) => ! OrderResource::canEdit($record))
                ->url(fn ($record) => OrderResource::getUrl('edit', ['record' => $record])),
        ];
    }
}
