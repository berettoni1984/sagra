<?php

namespace App\Services;

use App\Models\Product;

/**
 * Facade service che aggrega tutti i servizi necessari per la gestione degli ordini
 */
class OrderManagementService
{
    public function __construct(
        private CartService $cartService,
        private StockService $stockService,
        private ProductEnrichmentService $enrichmentService
    ) {}

    // ==================== Cart Operations ====================

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function addProduct(array $items, int $productId): array
    {
        return $this->cartService->addProduct($items, $productId);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function removeProduct(array $items, int $index): array
    {
        return $this->cartService->removeProduct($items, $index);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function increaseQuantity(array $items, int $index): array
    {
        return $this->cartService->increaseQuantity($items, $index);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function decreaseQuantity(array $items, int $index): array
    {
        return $this->cartService->decreaseQuantity($items, $index);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>
     */
    public function splitItem(array $items, int $index): array
    {
        return $this->cartService->splitItem($items, $index);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getTotalItemsCount(array $items): int
    {
        return $this->cartService->getTotalItemsCount($items);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getOrderTotal(array $items): float
    {
        return $this->cartService->getOrderTotal($items);
    }

    // ==================== Stock Operations ====================

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function hasOutOfStockItems(array $items): bool
    {
        return $this->stockService->hasOutOfStockItems($items);
    }

    // ==================== Product Enrichment ====================

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array{id: int, name: string, price: string, stock: int, backorder: bool, number: int, total_in_cart: int, remaining_stock: int, is_out_of_stock: bool, has_insufficient_ingredients: bool}
     */
    public function getEnrichedProduct(array $items, Product $product, int $index): array
    {
        return $this->enrichmentService->getEnrichedProduct($items, $product, $index);
    }

    /**
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @param  array<int, int>  $productNumbers
     * @return array<int, array{item: mixed, item_id: string, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, has_insufficient_ingredients: bool, remaining_stock: int}>
     */
    public function getSortedEnrichedItems(array $items, array $productNumbers): array
    {
        return $this->enrichmentService->getSortedEnrichedItems($items, $productNumbers);
    }
}
