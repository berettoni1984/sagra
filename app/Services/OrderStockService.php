<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Movimenti di giacenza legati agli ordini (prodotti + ingredienti).
 *
 * Tutte le scritture passano da increment()/decrement(), cioè da un
 * "stock = stock ± ?" eseguito dal database: con più casse attive un
 * read-modify-write perderebbe silenziosamente uno dei due movimenti.
 */
class OrderStockService
{
    /**
     * Scarica la giacenza per tutte le righe dell'ordine (vendita).
     */
    public function deduct(Order $order): void
    {
        $this->apply($order, -1);
    }

    /**
     * Ripristina la giacenza per tutte le righe dell'ordine (annullamento).
     */
    public function restore(Order $order): void
    {
        $this->apply($order, 1);
    }

    /**
     * Ripristina la giacenza per un insieme di ordini (cancellazione multipla).
     *
     * @param  Collection<int, Order>  $orders
     */
    public function restoreMany(Collection $orders): void
    {
        foreach ($orders as $order) {
            $this->restore($order);
        }
    }

    /**
     * Applica un delta con segno alla colonna stock, sempre lato SQL.
     */
    public function applyDelta(Product|Ingredient $model, int $delta): void
    {
        if ($delta > 0) {
            $model->increment('stock', $delta);
        } elseif ($delta < 0) {
            $model->decrement('stock', -$delta);
        }
    }

    /**
     * @param  int  $sign  -1 per scaricare, 1 per ripristinare
     */
    private function apply(Order $order, int $sign): void
    {
        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            if (! $product) {
                continue;
            }

            $this->applyDelta($product, $sign * $orderItem->quantity);

            foreach ($product->ingredients as $ingredient) {
                if ($ingredient->is_disabled) {
                    continue;
                }
                $qty = (int) ($ingredient->pivot?->getAttributeValue('qty') ?? 0);
                if ($qty === 0) {
                    continue;
                }
                $this->applyDelta($ingredient, $sign * $orderItem->quantity * $qty);
            }
        }
    }
}
