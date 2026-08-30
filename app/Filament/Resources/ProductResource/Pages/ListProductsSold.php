<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\Queue;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class ListProductsSold extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    /**
     * ProductResource::canAccess() vale per tutte le pagine dei prodotti
     * insieme: chi puo' aprire questa in particolare si decide qui.
     */
    public static function authorizeResourceAccess(): void
    {
        parent::authorizeResourceAccess();

        abort_unless(ProductResource::canAccessSoldSheet(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getSoldQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.Name')),
                Tables\Columns\TextColumn::make('qty_done')
                    ->default(0)
                    ->label(__('filament.Qty Done')),
                Tables\Columns\TextColumn::make('qty_remaining')
                    ->default(0)
                    ->label(__('filament.Qty Remaining')),
                Tables\Columns\TextInputColumn::make('stock')
                    ->rules(['required', 'numeric'])
                    ->label(__('filament.Stock')),
                Tables\Columns\ToggleColumn::make('backorder')
                    ->label(__('filament.Backorder')),
            ])
            ->filters([
                Filter::make('number_now')
                    ->schema([
                        Select::make('queue_id')
                            ->searchable()
                            ->default(static fn () => Queue::enabledOrdered()->first()?->id)
                            ->options(static fn () => Queue::enabledOrdered()->get()->pluck('comment', 'id'))
                            ->label(__('filament.Queue'))
                            ->placeholder(__('filament.Queue')),
                        TextInput::make('number_now')
                            ->default(0)
                            ->numeric()
                            ->minValue(0)
                            ->label(__('filament.Number Now')),
                    ])
                    // I due totali sono aggregati (withSum) e vanno costruiti
                    // sulla query base: qui Filament passa una closure `where`
                    // annidata, dove una select aggiuntiva verrebbe ignorata.
                    ->query(static fn (Builder $query): Builder => $query)
                    ->label(__('filament.Number Now')),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordActions([
            ])
            ->toolbarActions([
            ])
            ->emptyStateActions([
            ]);
    }

    /**
     * I prodotti della coda selezionata, con quanto e' gia' stato ordinato fino
     * al numero servito adesso (`qty_done`) e quanto resta da preparare per
     * gli ordini successivi (`qty_remaining`).
     *
     * Il conteggio segue le stesse regole della cassa (vedi
     * ProductEnrichmentService::getSoldSinceQueueReset): e' per coda, parte
     * dall'ultimo reset di quella coda e ignora gli ordini annullati — qui via
     * soft delete, applicato in automatico dalle relazioni.
     *
     * @return Builder<Product>
     */
    private function getSoldQuery(): Builder
    {
        $state = $this->getTableFilterState('number_now') ?? [];

        $query = Product::query()
            ->whereIsDisabled(false)
            ->orderBy('products.order');

        $queue = filled($state['queue_id'] ?? null)
            ? Queue::find($state['queue_id'])
            : null;

        if (! $queue instanceof Queue) {
            return $query->whereRaw('1 = 0');
        }

        $numberNow = filled($state['number_now'] ?? null)
            ? (int) $state['number_now']
            : null;

        return $query
            ->whereHas('queues', static fn (Builder $queues): Builder => $queues->whereKey($queue->getKey()))
            ->withSum(['orderItems as qty_done' => $this->orderedInQueue($queue, '<=', $numberNow)], 'quantity')
            ->withSum(['orderItems as qty_remaining' => $this->orderedInQueue($queue, '>', $numberNow)], 'quantity');
    }

    /**
     * Vincolo sugli order item della coda, opzionalmente limitato agli ordini
     * prima o dopo il numero servito adesso.
     */
    private function orderedInQueue(Queue $queue, string $operator, ?int $numberNow): Closure
    {
        return static function (Builder $items) use ($queue, $operator, $numberNow): void {
            $items->whereHas('order', static function (Builder $orders) use ($queue, $operator, $numberNow): void {
                $orders->where('queue_id', $queue->getKey());

                if ($queue->reset_at !== null) {
                    $orders->where('created_at', '>=', $queue->reset_at);
                }

                if ($numberNow !== null) {
                    $orders->where('number', $operator, $numberNow);
                }
            });
        };
    }
}
