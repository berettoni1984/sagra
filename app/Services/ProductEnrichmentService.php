<?php

namespace App\Services;

use App\Models\Product;

class ProductEnrichmentService
{
    public function __construct(
        private CartService $cartService,
        private StockService $stockService
    ) {}

    /**
     * Ottiene i dati arricchiti di un prodotto per la visualizzazione
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array{id: int, name: string, price: string, stock: int, backorder: bool, number: int, total_in_cart: int, remaining_stock: int, is_out_of_stock: bool, has_insufficient_ingredients: bool}
     */
    public function getEnrichedProduct(array $items, Product $product, int $index): array
    {
        $totalInCart = $this->cartService->getTotalInCart($items, $product->id);
        $remainingStock = $this->stockService->getRemainingStock($items, $product->id, $product->stock);
        $hasInsufficientIngredients = $this->stockService->hasInsufficientIngredients($items, $product);
        $isOutOfStock = $this->stockService->isProductOutOfStock($items, $product);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock' => $product->stock,
            'backorder' => (bool) $product->backorder,
            'number' => $index + 1,
            'total_in_cart' => $totalInCart,
            'remaining_stock' => $remainingStock,
            'is_out_of_stock' => $isOutOfStock,
            'has_insufficient_ingredients' => $hasInsufficientIngredients && ! $product->backorder,
        ];
    }

    /**
     * Ottiene i dati arricchiti di un item del carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @param  array{item_id: string, product_id: int, quantity: int, note: string|null}  $item
     * @param  array<int, int>  $productNumbers
     * @return array{item: mixed, item_id: string, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, has_insufficient_ingredients: bool, remaining_stock: int}
     */
    public function getEnrichedItem(array $items, array $item, int $originalIndex, array $productNumbers): array
    {
        $product = Product::with('ingredients')->find($item['product_id']);

        $rowTotal = 0;
        $remainingStock = 0;
        $isOutOfStock = false;
        $hasInsufficientIngredients = false;

        if ($product) {
            $rowTotal = ((float) $product->price) * $item['quantity'];
            $remainingStock = $this->stockService->getRemainingStock($items, $item['product_id'], $product->stock);
            $hasInsufficientIngredients = $this->stockService->hasInsufficientIngredients($items, $product);
            $isOutOfStock = ! $product->backorder && ($remainingStock < 0 || $hasInsufficientIngredients);
        }

        return [
            'item' => $item,
            'item_id' => $item['item_id'],
            'original_index' => $originalIndex,
            'sort_order' => $productNumbers[$item['product_id']] ?? 999,
            'product' => $product,
            'row_total' => $rowTotal,
            'product_number' => $productNumbers[$item['product_id']] ?? 0,
            'is_out_of_stock' => $isOutOfStock,
            'has_insufficient_ingredients' => $hasInsufficientIngredients && $product && ! $product->backorder,
            'remaining_stock' => $remainingStock,
        ];
    }

    /**
     * Ottiene tutti gli item del carrello ordinati e arricchiti
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @param  array<int, int>  $productNumbers
     * @return array<int, array{item: mixed, item_id: string, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, has_insufficient_ingredients: bool, remaining_stock: int}>
     */
    public function getSortedEnrichedItems(array $items, array $productNumbers): array
    {
        $enrichedItems = [];

        foreach ($items as $index => $item) {
            $enrichedItems[] = $this->getEnrichedItem($items, $item, $index, $productNumbers);
        }

        // Ordina per sort_order, se pari usa original_index
        usort($enrichedItems, function ($a, $b) {
            $sortOrderComparison = $a['sort_order'] <=> $b['sort_order'];
            if ($sortOrderComparison === 0) {
                return $a['original_index'] <=> $b['original_index'];
            }

            return $sortOrderComparison;
        });

        return $enrichedItems;
    }
}
