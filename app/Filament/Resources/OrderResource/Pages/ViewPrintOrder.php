<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPrintOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.view-record';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->hidden(fn ($record) => $record->id !== Order::max('id')),
            Actions\Action::make('print')
                ->label(__('filament.Print'))
                ->url(fn () => OrderResource::getUrl('print', ['record' => $this->record, 'print' => true]))
                ->icon('heroicon-o-printer'),
            Actions\CreateAction::make('new_order')
                ->label(__('filament.New - Alt + s'))
                ->keyBindings(['alt+s'])
                ->url(fn () => OrderResource::getUrl('create'))
                ->icon('heroicon-o-plus-circle'),

        ];
    }
}
