<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderStockService;
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
                    app(OrderStockService::class)->restore($record);
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
        $stock = app(OrderStockService::class);

        foreach ($this->qtyChanges as $productId => $qtyChange) {
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }
            // Delta con segno: scarica se la quantità è aumentata, ripristina se è
            // scesa. Sempre lato SQL per non perdere scarichi concorrenti.
            $quantity = $qtyChange['new'] - $qtyChange['old'];
            $stock->applyDelta($product, -$quantity);
            $product->ingredients->each(function ($ingredient) use ($quantity, $stock) {
                if ($ingredient->is_disabled) {
                    return;
                }
                $qty = (int) ($ingredient->pivot?->getAttributeValue('qty') ?? 0);
                if ($qty === 0) {
                    return;
                }
                $stock->applyDelta($ingredient, -($quantity * $qty));
            });
        }
    }
}
