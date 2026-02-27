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
        $newItem = [
            'product_id' => $this->items[$index]['product_id'],
            'quantity' => 1,
            'note' => null, // Nuova riga senza nota
        ];

        // Inserisci la nuova riga subito dopo quella corrente
        array_splice($this->items, $index + 1, 0, [$newItem]);
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
     * @return array<int, Product>
     */
    public function getProducts(): array
    {
        if (! $this->queueId) {
            return [];
        }

        return Product::whereIsDisabled(false)
            ->join('product_queue', 'products.id', '=', 'product_queue.product_id')
            ->where('product_queue.queue_id', $this->queueId)
            ->orderBy('products.order')
            ->select('products.*')
            ->get()
            ->toArray();
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
            ->toArray();
    }
}
