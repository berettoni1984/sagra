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
