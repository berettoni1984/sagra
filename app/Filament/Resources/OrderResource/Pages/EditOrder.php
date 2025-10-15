<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @var array<int, array{old: int, new: int}>
     */
    protected array $qtyChanges = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(static function (Order $record) {
                    foreach ($record->orderItems as $orderItem) {
                        $product = $orderItem->product;
                        if (! $product) {
                            continue;
                        }
                        $product->stock += $orderItem->quantity;
                        $product->save();
                        $product->ingredients->each(function (Ingredient $ingredient) use ($orderItem) {
                            if ($ingredient->is_disabled) {
                                return;
                            }
                            $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                            $ingredient->stock += ($orderItem->quantity * $qty);
                            $ingredient->save();
                        });
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        return $resource::getUrl('print', ['record' => $this->getRecord(), 'print' => true]);
    }

    protected function beforeSave(): void
    {
        /** @var Order $order */
        $order = $this->getRecord();
        $data = $this->data;
        $qtyValue = [];
        foreach ($order->orderItems as $orderItem) {
            $productId = (int) $orderItem->product_id;
            $quantity = $orderItem->quantity;
            if (! isset($qtyValue[$productId]['old'])) {
                $qtyValue[$productId] = ['old' => 0, 'new' => 0];
            }
            $qtyValue[$productId]['old'] += $quantity;
        }
        foreach ($data['orderItems'] ?? [] as $orderItem) {
            $productId = (int) $orderItem['product_id'];
            $quantity = (int) $orderItem['quantity'];
            if (! isset($qtyValue[$productId])) {
                $qtyValue[$productId] = ['old' => 0, 'new' => 0];
            }
            $qtyValue[$productId]['new'] += $quantity;
        }
        $this->qtyChanges = $qtyValue;
    }

    protected function afterSave(): void
    {
        foreach ($this->qtyChanges as $productId => $qtyChange) {
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }
            $quantity = $qtyChange['new'] - $qtyChange['old'];
            $product->stock -= $quantity;
            $product->save();
            $product->ingredients->each(function (Ingredient $ingredient) use ($quantity) {
                if ($ingredient->is_disabled) {
                    return;
                }
                $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                $ingredient->stock -= ($quantity * $qty);
                $ingredient->save();
            });
        }

    }
}
