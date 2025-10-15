<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Ingredient;
use App\Models\Queue;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var \App\Models\Queue|null $queue */
        $queue = Queue::find($data['queue_id']);
        if ($queue) {

            $number = $queue->order_number;
            $data['number'] = ++$number;
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

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            ...(static::canCreateAnother() ? [$this->getCreateAnotherFormAction()] : []),
            $this->getCancelFormAction(),
        ];
    }

    public function afterCreate(): void
    {
        /** @var \App\Models\Order|null $record */
        $record = $this->getRecord();
        if (! $record) {
            return;
        }
        foreach ($record->orderItems as $orderItem) {
            $product = $orderItem->product;
            if (! $product) {
                continue;
            }
            $product->stock -= $orderItem->quantity;
            $product->save();
            $product->ingredients->each(function (Ingredient $ingredient) use ($orderItem) {
                if ($ingredient->is_disabled) {
                    return;
                }
                $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                $ingredient->stock -= ($orderItem->quantity * $qty);
                $ingredient->save();
            });
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->keyBindings(['alt+s'])
            ->label(__('filament.Save - Alt + s'));
    }
}
