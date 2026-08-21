<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductEnrichmentService
{
    public function __construct(
        private CartService $cartService,
        private StockService $stockService
    ) {}

    /**
     * Quantità vendute per prodotto sulla coda indicata, dal suo ultimo reset.
     *
     * Il conteggio è per coda: gli ordini di un'altra fila non entrano, perché
     * anche l'azzeramento è per fila e sommandole il reset di una sola coda non
     * avrebbe mai riportato il contatore a zero. Una sola query aggregata per
     * tutti i prodotti: viene invocata a ogni render della cassa.
     *
     * Una coda senza reset_at (mai azzerata) conta dall'inizio; gli ordini
     * annullati non contano, perché la merce è già rientrata a magazzino. Gli
     * ordini senza coda (storico di un'edizione precedente, quando queue_id era
     * nullo) restano fuori: non hanno un reset a cui riferirsi e resterebbero
     * nel conteggio per sempre.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    public function getSoldSinceQueueReset(array $productIds, int $queueId): array
    {
        if ($productIds === []) {
            return [];
        }

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('queues', 'queues.id', '=', 'orders.queue_id')
            ->whereIn('order_items.product_id', $productIds)
            ->where('orders.queue_id', $queueId)
            ->whereNull('orders.deleted_at')
            ->where(static function (Builder $query): void {
                $query->whereNull('queues.reset_at')
                    ->orWhereColumn('orders.created_at', '>=', 'queues.reset_at');
            })
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as product_id, SUM(order_items.quantity) as sold')
            ->pluck('sold', 'product_id')
            ->map(static fn ($sold): int => (int) $sold)
            ->all();
    }

    /**
     * Ottiene i dati arricchiti di un prodotto per la visualizzazione
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array{id: int, name: string, price: string, stock: int, backorder: bool, number: int, total_in_cart: int, remaining_stock: int, remaining_units: int|null, is_out_of_stock: bool, is_low_stock: bool, has_insufficient_ingredients: bool, has_low_ingredients: bool, sold: int}
     */
    public function getEnrichedProduct(array $items, Product $product, int $index, int $sold = 0): array
    {
        $totalInCart = $this->cartService->getTotalInCart($items, $product->id);
        $remainingStock = $this->stockService->getRemainingStock($items, $product->id, $product->stock);
        $hasInsufficientIngredients = $this->stockService->hasInsufficientIngredients($items, $product);
        $isOutOfStock = $this->stockService->isProductOutOfStock($items, $product);
        $isLowStock = $this->stockService->isProductLowStock($items, $product);
        $hasLowIngredients = $this->stockService->hasLowIngredients($items, $product);
        // Unità davvero vendibili: remaining_stock guarda solo la giacenza del
        // prodotto, questo tiene conto anche degli ingredienti.
        $remainingUnits = $this->stockService->getRemainingUnits($items, $product);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock' => $product->stock,
            'backorder' => (bool) $product->backorder,
            'number' => $index + 1,
            'total_in_cart' => $totalInCart,
            'remaining_stock' => $remainingStock,
            'remaining_units' => $remainingUnits,
            'is_out_of_stock' => $isOutOfStock,
            'is_low_stock' => $isLowStock,
            'has_insufficient_ingredients' => $hasInsufficientIngredients && ! $product->backorder,
            'has_low_ingredients' => $hasLowIngredients,
            'sold' => $sold,
        ];
    }

    /**
     * Ottiene i dati arricchiti di un item del carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @param  array{item_id: string, product_id: int, quantity: int, note: string|null}  $item
     * @param  array<int, int>  $productNumbers
     * @return array{item: mixed, item_id: string, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, is_low_stock: bool, has_insufficient_ingredients: bool, has_low_ingredients: bool, remaining_stock: int, remaining_units: int|null}
     */
    public function getEnrichedItem(array $items, array $item, int $originalIndex, array $productNumbers): array
    {
        $product = Product::with('ingredients')->find($item['product_id']);

        $rowTotal = 0;
        $remainingStock = 0;
        $remainingUnits = null;
        $isOutOfStock = false;
        $isLowStock = false;
        $hasInsufficientIngredients = false;
        $hasLowIngredients = false;

        if ($product) {
            $rowTotal = ((float) $product->price) * $item['quantity'];
            $remainingStock = $this->stockService->getRemainingStock($items, $item['product_id'], $product->stock);
            $remainingUnits = $this->stockService->getRemainingUnits($items, $product);
            $hasInsufficientIngredients = $this->stockService->hasInsufficientIngredients($items, $product);
            $isOutOfStock = ! $product->backorder && ($remainingStock < 0 || $hasInsufficientIngredients);
            $isLowStock = $this->stockService->isProductLowStock($items, $product);
            $hasLowIngredients = $this->stockService->hasLowIngredients($items, $product);
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
            'is_low_stock' => $isLowStock,
            'has_insufficient_ingredients' => $hasInsufficientIngredients && $product && ! $product->backorder,
            'has_low_ingredients' => $hasLowIngredients,
            'remaining_stock' => $remainingStock,
            'remaining_units' => $remainingUnits,
        ];
    }

    /**
     * Ottiene tutti gli item del carrello ordinati e arricchiti
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @param  array<int, int>  $productNumbers
     * @return array<int, array{item: mixed, item_id: string, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, is_low_stock: bool, has_insufficient_ingredients: bool, has_low_ingredients: bool, remaining_stock: int, remaining_units: int|null}>
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
