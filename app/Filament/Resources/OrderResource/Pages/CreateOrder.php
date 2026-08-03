<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Queue;
use App\Services\OrderStockService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Lock della riga coda: senza lock due casse sulla stessa fila leggono
        // lo stesso order_number e stampano due ordini con lo stesso numero.
        /** @var Queue|null $queue */
        $queue = Queue::whereKey($data['queue_id'])->lockForUpdate()->first();
        if ($queue) {
            $number = $queue->order_number + 1;
            $data['number'] = $number;
            $queue->order_number = $number;
            $queue->save();
        }
        if (! $queue) {
            $data['number'] = 0;
        }
        $data['user_id'] = auth()->user()?->id;

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        return $resource::getUrl('print', ['record' => $this->getRecord(), 'print' => true]);
    }

    public function afterCreate(): void
    {
        /** @var Order|null $record */
        $record = $this->getRecord();
        if (! $record) {
            return;
        }
        // Scarico lato SQL: il read-modify-write faceva perdere uno dei due
        // scarichi con ordini concorrenti sullo stesso prodotto.
        app(OrderStockService::class)->deduct($record);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->keyBindings(['alt+s'])
            ->label(__('filament.Save - Alt + s'));
    }
}
