<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Queue;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuickCreateOrder extends Page
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.order-resource.pages.quick-create-order';

    public ?int $queueId = null;

    /** @var array<int, array{product_id: int, quantity: int, note: string|null}> */
    public array $items = [];

    public ?string $note = null;

    public bool $free = false;

    public ?float $customTotalPaid = null;

    /**
     * @var array<string, string>
     */
    protected $listeners = ['create-order' => 'createOrder'];

    public function mount(): void
    {
        $queues = Queue::whereIsDisabled(false)->get();

        if ($queues->count() === 1) {
            $this->queueId = $queues->first()?->id;

            return;
        }
        $defaultQueue = Queue::whereIsDisabled(false)->whereIsDefault(true)->first();
        $this->queueId = $defaultQueue?->id;

    }

    public function updatedQueueId(): void
    {
        // Azzera il carrello quando si cambia coda
        if (! empty($this->items)) {
            Notification::make()
                ->title(__('filament.Cart cleared'))
                ->body(__('filament.Cart has been cleared due to queue change'))
                ->warning()
                ->send();
        }

        $this->items = [];
        $this->note = null;
    }

    public function getPaid(float|int $totalAmount): string
    {
        if ($this->customTotalPaid !== null) {
            return number_format($this->customTotalPaid, 2, '.', '');
        }
        if ($this->free) {
            return '0.00';
        }

        return number_format($totalAmount, 2, '.', '');

    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createOrder')
                ->label(__('filament.Create Order'))
                ->keyBindings(['alt+s'])
                ->color('success')
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->modalHeading(__('filament.No items'))
                ->modalDescription(__('filament.Are you sure you want to create an empty order?'))
                ->hidden(fn () => ! empty($this->items))
                ->visible(fn () => empty($this->items))
                ->action(fn () => $this->createOrder()),
            Action::make('createOrderDirect')
                ->label(__('filament.Create Order'))
                ->color('success')
                ->icon('heroicon-o-check')
                ->visible(fn () => ! empty($this->items))
                ->action(fn () => $this->createOrder()),
        ];
    }

    public function addProduct(int $productId): void
    {
        $found = false;
        foreach ($this->items as $key => $item) {
            if ($item['product_id'] === $productId) {
                $this->items[$key]['quantity']++;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $this->items[] = [
                'product_id' => $productId,
                'quantity' => 1,
                'note' => null,
            ];
        }
    }

    public function removeProduct(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function increaseQuantity(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity']++;
        }
    }

    public function decreaseQuantity(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }
        if ($this->items[$index]['quantity'] > 1) {
            $this->items[$index]['quantity']--;

            return;
        }
        $this->removeProduct($index);

    }

    public function updateItemNote(int $index, ?string $note): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['note'] = $note;
        }
    }

    public function splitItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        // Se la quantità è 1, non possiamo dividere
        if ($this->items[$index]['quantity'] <= 1) {
            return;
        }

        // Sottrai 1 dalla quantità corrente
        $this->items[$index]['quantity']--;

        // Crea una nuova riga con 1 unità dello stesso prodotto
        $this->items[] = [
            'product_id' => $this->items[$index]['product_id'],
            'quantity' => 1,
            'note' => null,
        ];
    }

    public function createOrder(): void
    {
        if (! $this->queueId) {
            Notification::make()
                ->title(__('filament.queue_label').' '.__('filament.required'))
                ->danger()
                ->send();

            return;
        }

        try {
            DB::beginTransaction();

            /** @var Queue|null $queue */
            $queue = Queue::find($this->queueId);
            if (! $queue) {
                throw new RuntimeException('Queue not found');
            }

            $number = $queue->order_number;
            $number++;
            $queue->order_number = $number;
            $queue->save();

            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($this->items as $item) {
                /** @var Product|null $product */
                $product = Product::find($item['product_id']);
                if (! $product) {
                    continue;
                }

                $rowAmount = ((float) $product->price) * $item['quantity'];
                $totalAmount += $rowAmount;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                    'amount' => $product->price,
                    'row_amount' => $item['quantity'] * ((float) $product->price),
                    'note' => $item['note'],
                ];

                // Update stock
                $product->stock -= $item['quantity'];
                $product->save();

                // Update ingredients stock
                $product->ingredients->each(function (Ingredient $ingredient) use ($item) {
                    if ($ingredient->is_disabled) {
                        return;
                    }
                    $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                    $ingredient->stock -= ($item['quantity'] * $qty);
                    $ingredient->save();
                });
            }

            // Calcola total_paid: usa customTotalPaid se impostato, altrimenti 0 se free, altrimenti totalAmount
            $totalPaid = $this->getPaid($totalAmount);

            /** @var Order $order */
            $order = Order::create([
                'number' => $number,
                'queue_id' => $this->queueId,
                'user_id' => auth()->user()?->id,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'total_paid' => $totalPaid,
                'note' => $this->note,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->orderItems()->create($itemData);
            }

            DB::commit();

            Notification::make()
                ->title(__('filament.Order created'))
                ->success()
                ->send();

            $this->redirect(OrderResource::getUrl('print', ['record' => $order->id, 'print' => true]));

        } catch (\Throwable $e) {
            DB::rollBack();

            Notification::make()
                ->title(__('filament.Error creating order'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Calcola il totale di un ingrediente usato nel carrello
     */
    protected function getTotalIngredientUsedInCart(int $ingredientId): float
    {
        $total = 0;
        foreach ($this->items as $item) {
            $product = Product::with('ingredients')->find($item['product_id']);
            if (! $product) {
                continue;
            }
            $ingredient = $product->ingredients->firstWhere('id', $ingredientId);
            if (! $ingredient || $ingredient->is_disabled) {
                continue;
            }
            $qtyNeeded = $ingredient->pivot?->qty ?? 0;
            $total += $qtyNeeded * $item['quantity'];
        }

        return $total;
    }

    /**
     * Verifica se un prodotto ha ingredienti insufficienti considerando il carrello
     */
    protected function hasInsufficientIngredients(Product $product): bool
    {
        if ($product->backorder) {
            return false;
        }

        if (! $product->ingredients) {
            return false;
        }

        foreach ($product->ingredients as $ingredient) {
            if ($ingredient->is_disabled) {
                continue;
            }

            $totalUsed = $this->getTotalIngredientUsedInCart($ingredient->id);
            if ($ingredient->stock - $totalUsed < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcola la quantità totale di un prodotto nel carrello
     */
    protected function getTotalInCart(int $productId): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            if ($item['product_id'] === $productId) {
                $total += $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Calcola lo stock rimanente di un prodotto considerando il carrello
     */
    protected function getRemainingStock(int $productId, int $currentStock): int
    {
        return $currentStock - $this->getTotalInCart($productId);
    }

    /**
     * Verifica se un prodotto è fuori stock considerando sia lo stock che gli ingredienti
     */
    protected function isProductOutOfStock(Product $product): bool
    {
        if ($product->backorder) {
            return false;
        }

        $remainingStock = $this->getRemainingStock($product->id, $product->stock);
        if ($remainingStock < 0) {
            return true;
        }

        return $this->hasInsufficientIngredients($product);
    }

    /**
     * Ottiene i dati arricchiti di un prodotto per la visualizzazione
     *
     * @return array{id: int, name: string, price: string, stock: int, backorder: bool, number: int, total_in_cart: int, remaining_stock: int, is_out_of_stock: bool, has_insufficient_ingredients: bool}
     */
    protected function getEnrichedProduct(Product $product, int $index): array
    {
        $totalInCart = $this->getTotalInCart($product->id);
        $remainingStock = $this->getRemainingStock($product->id, $product->stock);
        $hasInsufficientIngredients = $this->hasInsufficientIngredients($product);
        $isOutOfStock = $this->isProductOutOfStock($product);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock' => $product->stock,
            'backorder' => $product->backorder,
            'number' => $index + 1,
            'total_in_cart' => $totalInCart,
            'remaining_stock' => $remainingStock,
            'is_out_of_stock' => $isOutOfStock,
            'has_insufficient_ingredients' => $hasInsufficientIngredients && ! $product->backorder,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, price: string, stock: int, backorder: bool, number: int, total_in_cart: int, remaining_stock: int, is_out_of_stock: bool, has_insufficient_ingredients: bool}>
     */
    public function getProducts(): array
    {
        if (! $this->queueId) {
            return [];
        }

        $products = Product::whereIsDisabled(false)
            ->with('ingredients')
            ->join('product_queue', 'products.id', '=', 'product_queue.product_id')
            ->where('product_queue.queue_id', $this->queueId)
            ->orderBy('products.order')
            ->select('products.*')
            ->get();

        $enrichedProducts = [];
        foreach ($products as $index => $product) {
            $enrichedProducts[] = $this->getEnrichedProduct($product, $index);
        }

        return $enrichedProducts;
    }

    /**
     * Ottiene una mappa product_id => numero prodotto
     *
     * @return array<int, int>
     */
    protected function getProductNumbersMap(): array
    {
        $map = [];
        $products = $this->getProducts();
        foreach ($products as $product) {
            $map[$product['id']] = $product['number'];
        }

        return $map;
    }

    /**
     * Ottiene i dati arricchiti di un item del carrello
     *
     * @param  array{product_id: int, quantity: int, note: string|null}  $item
     * @return array{item: array, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, has_insufficient_ingredients: bool, remaining_stock: int}
     */
    protected function getEnrichedItem(array $item, int $originalIndex): array
    {
        $productNumbers = $this->getProductNumbersMap();
        $product = Product::with('ingredients')->find($item['product_id']);

        $rowTotal = 0;
        $remainingStock = 0;
        $isOutOfStock = false;
        $hasInsufficientIngredients = false;

        if ($product) {
            $rowTotal = ((float) $product->price) * $item['quantity'];
            $totalInCart = $this->getTotalInCart($item['product_id']);
            $remainingStock = $product->stock - $totalInCart;
            $hasInsufficientIngredients = $this->hasInsufficientIngredients($product);
            $isOutOfStock = ! $product->backorder && ($remainingStock < 0 || $hasInsufficientIngredients);
        }

        return [
            'item' => $item,
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
     * @return array<int, array{item: array, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, has_insufficient_ingredients: bool, remaining_stock: int}>
     */
    public function getSortedEnrichedItems(): array
    {
        $enrichedItems = [];

        foreach ($this->items as $index => $item) {
            $enrichedItems[] = $this->getEnrichedItem($item, $index);
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

    /**
     * Verifica se ci sono prodotti fuori stock nel carrello
     */
    public function hasOutOfStockItems(): bool
    {
        foreach ($this->items as $item) {
            $product = Product::with('ingredients')->find($item['product_id']);
            if (! $product || $product->backorder) {
                continue;
            }

            $totalInCart = $this->getTotalInCart($item['product_id']);
            $remainingStock = $product->stock - $totalInCart;

            if ($remainingStock < 0) {
                return true;
            }

            if ($this->hasInsufficientIngredients($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcola il totale dell'ordine
     */
    public function getOrderTotal(): float
    {
        $total = 0;
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $total += ((float) $product->price) * $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Calcola il numero totale di articoli nel carrello
     */
    public function getTotalItemsCount(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item['quantity'];
        }

        return $total;
    }

    /**
     * Verifica se la configurazione "free" è abilitata
     */
    public function isFreeConfigEnabled(): bool
    {
        return (bool) \App\Models\Config::whereCode('free')->first()?->config_value;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function getQueues(): array
    {
        return Queue::whereIsDisabled(false)
            ->get()
            ->map(fn ($queue) => [
                'id' => $queue->id,
                'label' => $queue->label,
            ])
            ->values()
            ->toArray();
    }
}
