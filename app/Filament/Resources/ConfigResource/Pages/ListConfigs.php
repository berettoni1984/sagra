<?php

namespace App\Filament\Resources\ConfigResource\Pages;

use App\Filament\Resources\ConfigResource;
use App\Models\Queue;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfigs extends ListRecords
{
    protected static string $resource = ConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('resetNumber')
                ->label(__('filament.reset_number'))
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    // La pagina è già riservata agli admin da ConfigResource::canAccess(),
                    // ma l'azione è distruttiva: meglio non dipendere solo da quello.
                    abort_unless(auth()->user()?->isAdmin() ?? false, 403);

                    Queue::query()->update([
                        'order_number' => 0,
                        'reset_at' => Carbon::now(),
                    ]);
                })
                ->color('danger')
                ->requiresConfirmation(),
        ];
    }
}
