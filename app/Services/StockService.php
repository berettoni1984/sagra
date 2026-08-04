<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Product;

class StockService
{
    /**
     * Cache per-richiesta dei prodotti del carrello.
     *
     * getTotalIngredientUsedInCart() viene invocato una volta per ogni ingrediente
     * di ogni prodotto mostrato: senza cache ogni render rifà una find() per ogni
     * riga di carrello, moltiplicata per prodotti x ingredienti.
     *
     * @var array<int, Product|null>
     */
    private array $productCache = [];

    private function findProduct(int $productId): ?Product
    {
        if (! array_key_exists($productId, $this->productCache)) {
            $this->productCache[$productId] = Product::with('ingredients')->find($productId);
        }

        return $this->productCache[$productId];
    }

    /**
     * Calcola il totale di un ingrediente usato nel carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getTotalIngredientUsedInCart(array $items, int $ingredientId): float
    {
        $total = 0;
        foreach ($items as $item) {
            $product = $this->findProduct($item['product_id']);
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
     * Soglia oltre la quale la cassa avvisa che la scorta sta finendo.
     *
     * 0 (valore assente, vuoto o non numerico) disattiva l'avviso; i negativi
     * vengono riportati a 0 perché sotto zero comanda già l'alert di esaurito.
     */
    public function getLowStockThreshold(): int
    {
        return Config::intValue('low_stock_threshold', 0, 0);
    }

    /**
     * Unità di prodotto ancora vendibili, carrello compreso.
     *
     * È il limite più stretto fra la giacenza del prodotto e quella dei suoi
     * ingredienti: 100 panini in giacenza ma salsiccia per 2 fanno 2 unità
     * residue. Un valore negativo significa esaurito, esattamente come
     * isProductOutOfStock(). Per i prodotti in backorder restituisce null: la
     * giacenza non viene applicata, quindi non esiste un residuo.
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function getRemainingUnits(array $items, Product $product): ?int
    {
        if ($product->backorder) {
            return null;
        }

        $remaining = $this->getRemainingStock($items, $product->id, $product->stock);

        foreach ($this->getIngredientUnitLimits($items, $product) as $limit) {
            $remaining = min($remaining, $limit);
        }

        return $remaining;
    }

    /**
     * Unità di prodotto ancora ottenibili da ciascun ingrediente, carrello compreso.
     *
     * Un ingrediente con 7 pezzi in giacenza e un pivot qty di 2 basta per 3
     * unità di prodotto, non per 3,5: la parte frazionaria non è vendibile.
     * Gli ingredienti disabilitati e quelli a qty 0 non limitano nulla, come
     * già in hasInsufficientIngredients().
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     * @return array<int, int>
     */
    private function getIngredientUnitLimits(array $items, Product $product): array
    {
        $limits = [];

        foreach ($product->ingredients as $ingredient) {
            if ($ingredient->is_disabled) {
                continue;
            }

            $qtyNeeded = (float) ($ingredient->pivot->qty ?? 0);
            if ($qtyNeeded <= 0) {
                continue;
            }

            $ingredientLeft = $ingredient->stock - $this->getTotalIngredientUsedInCart($items, $ingredient->id);
            $limits[] = (int) floor($ingredientLeft / $qtyNeeded);
        }

        return $limits;
    }

    /**
     * Verifica se un prodotto è in scorta bassa: disponibile, ma agli ultimi pezzi.
     *
     * Vale solo per i prodotti ancora vendibili. A residuo negativo il prodotto
     * è esaurito e l'alert prevale sull'avviso, così come per il backorder, che
     * ignora del tutto le giacenze.
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function isProductLowStock(array $items, Product $product): bool
    {
        $threshold = $this->getLowStockThreshold();
        if ($threshold <= 0) {
            return false;
        }

        $remaining = $this->getRemainingUnits($items, $product);

        return $remaining !== null && $remaining >= 0 && $remaining <= $threshold;
    }

    /**
     * Verifica se è un ingrediente a portare il prodotto in scorta bassa.
     *
     * Serve solo a scegliere l'etichetta in cassa, come
     * hasInsufficientIngredients() fa per l'esaurito: distingue "ne restano
     * pochi" da "l'ingrediente sta finendo", che si risolve rifornendo la
     * cucina e non il magazzino.
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function hasLowIngredients(array $items, Product $product): bool
    {
        $threshold = $this->getLowStockThreshold();
        if ($threshold <= 0 || $product->backorder) {
            return false;
        }

        foreach ($this->getIngredientUnitLimits($items, $product) as $limit) {
            if ($limit >= 0 && $limit <= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se il carrello contiene prodotti agli ultimi pezzi.
     *
     * Non blocca nulla: serve solo ad avvisare la cassa che dopo questo ordine
     * quel prodotto è vicino a finire.
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function hasLowStockItems(array $items): bool
    {
        if ($this->getLowStockThreshold() <= 0) {
            return false;
        }

        foreach ($items as $item) {
            $product = $this->findProduct($item['product_id']);
            if (! $product) {
                continue;
            }

            if ($this->isProductLowStock($items, $product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se ci sono prodotti fuori stock nel carrello
     *
     * @param  array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}>  $items
     */
    public function hasOutOfStockItems(array $items): bool
    {
        foreach ($items as $item) {
            $product = $this->findProduct($item['product_id']);
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
