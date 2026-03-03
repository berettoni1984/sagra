<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Queue;
use App\Services\OrderManagementService;
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

    /** @var array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}> */
    public array $items = [];

    public ?string $note = null;

    public bool $free = false;

    public ?float $customTotalPaid = null;

    private OrderManagementService $orderService;

    public function boot(OrderManagementService $orderService): void
    {
        $this->orderService = $orderService;
    }

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
        $this->items = $this->orderService->addProduct($this->items, $productId);
    }

    public function removeProduct(int $index): void
    {
        $this->items = $this->orderService->removeProduct($this->items, $index);
    }

    public function increaseQuantity(int $index): void
    {
        $this->items = $this->orderService->increaseQuantity($this->items, $index);
    }

    public function decreaseQuantity(int $index): void
    {
        $this->items = $this->orderService->decreaseQuantity($this->items, $index);
    }

    public function updateItemNote(int $index, ?string $note): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['note'] = $note;
        }
    }

    public function splitItem(int $index): void
    {
        $this->items = $this->orderService->splitItem($this->items, $index);
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
            $enrichedProducts[] = $this->orderService->getEnrichedProduct($this->items, $product, $index);
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
     * Ottiene tutti gli item del carrello ordinati e arricchiti
     *
     * @return array<int, array{item: mixed, item_id: string, original_index: int, sort_order: int, product: ?Product, row_total: float, product_number: int, is_out_of_stock: bool, has_insufficient_ingredients: bool, remaining_stock: int}>
     */
    public function getSortedEnrichedItems(): array
    {
        $productNumbers = $this->getProductNumbersMap();

        return $this->orderService->getSortedEnrichedItems($this->items, $productNumbers);
    }

    /**
     * Verifica se ci sono prodotti fuori stock nel carrello
     */
    public function hasOutOfStockItems(): bool
    {
        return $this->orderService->hasOutOfStockItems($this->items);
    }

    /**
     * Calcola il totale dell'ordine
     */
    public function getOrderTotal(): float
    {
        return $this->orderService->getOrderTotal($this->items);
    }

    /**
     * Calcola il numero totale di articoli nel carrello
     */
    public function getTotalItemsCount(): int
    {
        return $this->orderService->getTotalItemsCount($this->items);
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
