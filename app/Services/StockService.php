<?php

namespace App\Services;

use App\Models\Product;

class StockService
{
    /**
     * Calcola il totale di un ingrediente usato nel carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getTotalIngredientUsedInCart(array $items, int $ingredientId): float
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::with('ingredients')->find($item['product_id']);
            if (! $product) {
                continue;
            }
            $ingredient = $product->ingredients->firstWhere('id', $ingredientId);
            if (! $ingredient || $ingredient->is_disabled) {
                continue;
            }
            $qtyNeeded = $ingredient->pivot->qty ?? 0;
            $total += $qtyNeeded * $item['quantity'];
        }

        return $total;
    }

    /**
     * Verifica se un prodotto ha ingredienti insufficienti considerando il carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function hasInsufficientIngredients(array $items, Product $product): bool
    {
        if ($product->backorder) {
            return false;
        }

        if ($product->ingredients->isEmpty()) {
            return false;
        }

        foreach ($product->ingredients as $ingredient) {
            if ($ingredient->is_disabled) {
                continue;
            }

            $totalUsed = $this->getTotalIngredientUsedInCart($items, $ingredient->id);
            if ($ingredient->stock - $totalUsed < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcola lo stock rimanente di un prodotto considerando il carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getRemainingStock(array $items, int $productId, int $currentStock): int
    {
        $totalInCart = 0;
        foreach ($items as $item) {
            if ($item['product_id'] === $productId) {
                $totalInCart += $item['quantity'];
            }
        }

        return $currentStock - $totalInCart;
    }

    /**
     * Verifica se un prodotto è fuori stock considerando sia lo stock che gli ingredienti
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function isProductOutOfStock(array $items, Product $product): bool
    {
        if ($product->backorder) {
            return false;
        }

        $remainingStock = $this->getRemainingStock($items, $product->id, $product->stock);
        if ($remainingStock < 0) {
            return true;
        }

        return $this->hasInsufficientIngredients($items, $product);
    }

    /**
     * Verifica se ci sono prodotti fuori stock nel carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function hasOutOfStockItems(array $items): bool
    {
        foreach ($items as $item) {
            $product = Product::with('ingredients')->find($item['product_id']);
            if (! $product || $product->backorder) {
                continue;
            }

            $remainingStock = $this->getRemainingStock($items, $item['product_id'], $product->stock);

            if ($remainingStock < 0) {
                return true;
            }

            if ($this->hasInsufficientIngredients($items, $product)) {
                return true;
            }
        }

        return false;
    }
}
