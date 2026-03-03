<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Aggiunge un prodotto al carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function addProduct(array $items, int $productId): array
    {
        foreach ($items as $key => $item) {
            if ($item['product_id'] === $productId) {
                $items[$key]['quantity']++;

                return $items;
            }
        }

        $items[] = [
            'item_id' => (string) Str::orderedUuid(),
            'product_id' => $productId,
            'quantity' => 1,
            'note' => null,
        ];

        return $items;
    }

    /**
     * Rimuove un prodotto dal carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function removeProduct(array $items, int $index): array
    {
        unset($items[$index]);

        return array_values($items);
    }

    /**
     * Aumenta la quantità di un item
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function increaseQuantity(array $items, int $index): array
    {
        if (isset($items[$index])) {
            $items[$index]['quantity']++;
        }

        return $items;
    }

    /**
     * Diminuisce la quantità di un item
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function decreaseQuantity(array $items, int $index): array
    {
        if (! isset($items[$index])) {
            return $items;
        }

        if ($items[$index]['quantity'] > 1) {
            $items[$index]['quantity']--;

            return $items;
        }

        return $this->removeProduct($items, $index);
    }

    /**
     * Divide un item in due righe separate
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function splitItem(array $items, int $index): array
    {
        if (! isset($items[$index]) || $items[$index]['quantity'] <= 1) {
            return $items;
        }

        $items[$index]['quantity']--;

        $items[] = [
            'item_id' => (string) Str::orderedUuid(),
            'product_id' => $items[$index]['product_id'],
            'quantity' => 1,
            'note' => null,
        ];

        return $items;
    }

    /**
     * Calcola la quantità totale di un prodotto nel carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getTotalInCart(array $items, int $productId): int
    {
        $total = 0;
        foreach ($items as $item) {
            if ($item['product_id'] === $productId) {
                $total += $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Calcola il numero totale di articoli nel carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getTotalItemsCount(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'];
        }

        return $total;
    }

    /**
     * Calcola il totale dell'ordine
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getOrderTotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $total += ((float) $product->price) * $item['quantity'];
            }
        }

        return $total;
    }
}
