<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Config;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Queue;
use App\Services\OrderStockService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|null|\UnitEnum $navigationGroup = 'filament.work';

    protected static ?int $navigationSort = 1;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-list-bullet';

    /**
     * Ultimo id ordine per utente, risolto una volta per richiesta.
     *
     * @var array<int|string, int|null>
     */
    private static array $latestOrderIdCache = [];

    /**
     * Prodotti per coda (e ricerca), risolti una volta per richiesta.
     *
     * @var array<string, Collection<int,string>>
     */
    private static array $queueProductsCache = [];

    /**
     * @param  Order  $record
     */
    public static function canEdit(Model $record): bool
    {
        $userId = auth()->user()?->id;

        // canEdit() viene invocato due volte per riga della tabella (ViewAction e
        // EditAction): senza memoizzazione erano due MAX(id) su tutti gli ordini
        // per ogni riga, cioè un centinaio di scansioni per pagina.
        $cacheKey = $userId ?? 'guest';
        if (! array_key_exists($cacheKey, self::$latestOrderIdCache)) {
            self::$latestOrderIdCache[$cacheKey] = Order::where('user_id', $userId)->max('id');
        }
        $maxId = self::$latestOrderIdCache[$cacheKey];

        if (
            $record->id < $maxId ||
            $record->user_id !== $userId) {
            return false;
        }

        return parent::canEdit($record);
    }

    /**
     * Un admin può annullare qualunque ordine; una cassa solo il proprio più
     * recente, come per la modifica.
     *
     * Prima non c'era alcun controllo: dalla pagina di dettaglio o dall'elenco
     * una cassa poteva annullare gli ordini di altri operatori, con relativo
     * ricalcolo delle giacenze.
     *
     * @param  Order  $record
     */
    public static function canDelete(Model $record): bool
    {
        if (auth()->user()?->isAdmin()) {
            return parent::canDelete($record);
        }

        return static::canEdit($record) && parent::canDelete($record);
    }

    /**
     * @param  Order  $record
     */
    public static function canForceDelete(Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && parent::canForceDelete($record);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        if (static::$navigationGroup instanceof \UnitEnum) {
            return static::$navigationGroup;
        }

        return __(static::$navigationGroup);
    }

    public static function getLabel(): ?string
    {
        return __('filament.order_label');
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.order_label_plural');
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    public static function form(Schema $schema): Schema
    {
        $row = [
            Forms\Components\Hidden::make('id')
                ->hiddenOn(['create', 'view']),
            Forms\Components\Select::make('product_id')
                ->reactive()
                ->searchable()
                ->required()
                ->hiddenLabel()
                ->columnSpan(['default' => 6, 'lg' => 3, 'md' => 3, 'sm' => 6])
                ->options(fn ($get) => static::getProducts($get))
                ->getSearchResultsUsing(fn ($get, $search) => static::getProducts($get, $search))
                ->hiddenOn(['view'])
                ->afterStateUpdated(static function ($set, $get) {
                    /** @var Product|null $p */
                    $p = Product::find($get('product_id'));
                    $set('name', $p?->name);
                    $set('amount', $p?->price);

                    if (! $get('quantity')) {
                        $set('quantity', 1);
                    }
                    $rowAmount = number_format(((float) $get('amount')) * ((int) $get('quantity')), 2);
                    $set('row_amount', $rowAmount);
                    $total = static::getTotal($get('../../orderItems'));
                    $set('../../total_amount', $total);
                    $set('../../total_paid', $get('../../free') ? '0.00' : $total);
                }),
            Forms\Components\TextInput::make('name')
                ->hiddenLabel()
                ->columnSpan(['default' => 6, 'lg' => 3, 'md' => 3, 'sm' => 6])
                ->hiddenOn(['create', 'edit']),
            Forms\Components\Select::make('quantity')
                ->reactive()
                ->inlineLabel()
                ->required()
                ->columnSpan(['default' => 2, 'lg' => 1, 'md' => 1, 'sm' => 2])
                ->label(__('filament.Qty'))
                ->afterStateUpdated(static function ($set, $get) {
                    $rowAmount = number_format(((float) $get('amount')) * ((int) $get('quantity')), 2);
                    $set('row_amount', $rowAmount);
                    $total = static::getTotal($get('../../orderItems'));
                    $set('../../total_amount', $total);
                    $set('../../total_paid', $get('../../free') ? '0.00' : $total);
                })
                ->options(
                    static function ($get, $state) {
                        /** @var Order|null $order */
                        $order = Order::find($get('../../id'));

                        return static::getQuantityOptions(
                            (int) $state,
                            (int) $get('product_id'),
                            $get('../../orderItems') ?? [],
                            $order);
                    }
                ),
            Forms\Components\TextInput::make('amount')
                ->inlineLabel()
                ->columnSpan(['default' => 2, 'lg' => 1, 'md' => 1, 'sm' => 2])
                ->label(__('filament.Price €'))
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->numeric()
                ->readOnly(),
            Forms\Components\Hidden::make('row_amount'),
            Forms\Components\Textarea::make('note')
                ->inlineLabel()
                ->label(__('filament.Note'))
                ->columnSpan(['default' => 4, 'lg' => 2, 'sm' => 2, 'md' => 4]),
        ];

        return $schema
            ->columns(['default' => 4, 'lg' => 8, 'md' => 8, 'sm' => 4])
            ->schema([
                Forms\Components\Hidden::make('id')->hiddenOn(['create', 'view']),
                Forms\Components\Repeater::make('orderItems')
                    ->hiddenLabel()
                    ->label(__('filament.order_items'))
                    ->addActionLabel(__('filament.Add Row - Alt + r'))
                    ->columnSpan(['default' => 4, 'lg' => 8, 'md' => 8, 'sm' => 4])
                    ->columns(['default' => 4, 'lg' => 7, 'md' => 7, 'sm' => 4])
                    ->relationship('orderItems')
                    ->minItems(1)
                    ->schema($row)
                    ->addAction(fn ($action) => $action->color('danger')
                        ->icon('heroicon-o-plus')
                        ->keyBindings(['alt+r']))
                    ->mutateRelationshipDataBeforeCreateUsing(fn ($data) => static::orderItemBeforeCreate($data)),

                Forms\Components\TextInput::make('total_amount')
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2])
                    ->label(__('filament.Total €'))
                    ->inlineLabel()
                    ->readOnly(),
                Forms\Components\Hidden::make('total_paid')
                    ->hidden(fn () => (
                        (Config::value('change_price')) ||
                        (Config::value('free'))
                    )),
                Forms\Components\TextInput::make('total_paid')
                    ->inlineLabel()
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2])
                    ->hidden(fn () => ! (
                        (Config::value('change_price')) ||
                        (Config::value('free'))
                    ))
                    ->label(__('filament.Total Paid €'))
                    ->readOnly(fn (): bool => ! (Config::value('change_price'))),
                Forms\Components\Textarea::make('note')
                    ->label(__('filament.Note'))
                    ->inlineLabel()
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2]),
                Forms\Components\Toggle::make('free')
                    ->label(__('filament.Free'))
                    ->inlineLabel()
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2])
                    ->hidden(fn (): bool => ! (Config::value('free')))
                    ->reactive()
                    ->afterStateUpdated(static function ($get, $set) {
                        if ($get('free')) {
                            $set('total_paid', '0.00');
                        }
                        if (! $get('free')) {
                            $total = static::getTotal($get('orderItems'));
                            $set('total_paid', $total);
                        }
                    }),
                Forms\Components\TextInput::make('queue_tmp')
                    ->hiddenOn(['create'])
                    ->label(__('filament.queue_label'))
                    ->inlineLabel()
                    ->readOnly()
                    ->columnSpan(['default' => 4, 'lg' => 4, 'md' => 4, 'sm' => 4])
                    ->formatStateUsing(
                        static function ($record) {
                            return $record->queue->label ?? '';
                        }
                    ),
                Forms\Components\Hidden::make('queue_id')
                    ->hiddenOn(['create']),

                Forms\Components\Select::make('queue_id')
                    ->reactive()
                    ->searchable()
                    ->label(__('filament.queue_label'))
                    ->required()
                    ->inlineLabel()
                    ->afterStateUpdated(static function ($get, $set) {
                        $orderItems = $get('orderItems');
                        $queueId = $get('queue_id');
                        foreach ($orderItems as $key => $orderItem) {
                            try {
                                /** @var Product|null $p */
                                $p = Product::find((int) $orderItem['product_id']);
                                $exist = $p?->queues()
                                    ->where('queues.id', $queueId)
                                    ->exists();
                                if (! $exist) {
                                    $set('orderItems.'.$key.'.product_id', null);
                                    $set('orderItems.'.$key.'.name', null);
                                    $set('orderItems.'.$key.'.amount', null);

                                    $set('orderItems.'.$key.'.row_amount', null);
                                    $set('orderItems.'.$key.'.quantity', null);
                                }
                            } catch (Throwable $e) {
                                $set('orderItems.'.$key.'.product_id', null);
                                $set('orderItems.'.$key.'.name', null);
                                $set('orderItems.'.$key.'.amount', null);

                                $set('orderItems.'.$key.'.row_amount', null);
                                $set('orderItems.'.$key.'.quantity', null);
                            }
                            $total = static::getTotal($get('orderItems'));
                            $set('total_amount', $total);
                            $set('total_paid', $get('free') ? '0.00' : $total);
                        }
                    })
                    ->default(static function () {
                        if (((int) Queue::whereIsDisabled(false)->count()) === 1) {
                            return Queue::whereIsDisabled(false)->first()?->id;
                        }

                        return Queue::whereIsDisabled(false)->whereIsDefault(true)->first()?->id;
                    })
                    ->columnSpan(['default' => 4, 'lg' => 4, 'md' => 4, 'sm' => 4])
                    ->options(static function () {
                        return Queue::whereIsDisabled(false)
                            ->get()
                            ->pluck('label', 'id');
                    })
                    ->hiddenOn(['edit', 'view']),
            ]);
    }

    /**
     * @param  array<int|string,mixed>  $data
     * @return array<int|string,mixed>
     */
    public static function orderItemBeforeCreate(array $data): array
    {
        /** @var Product|null $p */
        $p = Product::find($data['product_id']);
        $data['name'] = $p?->name ?: '';

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // La colonna user_id legge $record->user->code tramite formatStateUsing:
            // non essendo una colonna con notazione a punti, Filament non fa eager
            // loading e caricava l'utente con una query per riga.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->sortable()
                    ->label(__('filament.Number')),
                Tables\Columns\TextColumn::make('queue.label')
                    ->sortable()
                    ->label(__('filament.Queue')),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('eur')
                    ->label(__('filament.Total')),
                Tables\Columns\TextColumn::make('total_paid')
                    ->money('eur')
                    ->hidden(fn () => ! (
                        (Config::value('change_price')) ||
                        (Config::value('free'))
                    ))
                    ->label(__('filament.Total Paid')),
                Tables\Columns\TextColumn::make('order_items_count')
                    ->label(__('filament.Items'))
                    ->counts('orderItems'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.created_at_column'))
                    ->sortable()
                    ->timezone(Config::value('timezone') ?: config('app.timezone'))
                    ->dateTime('H:i d/m/Y'),
                Tables\Columns\TextColumn::make('user_id')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->user->code ?? '')
                    ->label(__('filament.user_label')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn ($record) => static::canEdit($record)),
                EditAction::make()
                    ->hidden(fn ($record) => ! static::canEdit($record)),
                Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->url(fn ($record) => static::getUrl('print', ['record' => $record->id, 'print' => true]))
                    ->label(__('filament.Print')),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    // Senza questo hook annullare N ordini errati dall'elenco non
                    // restituiva nulla a magazzino, e i prodotti restavano
                    // falsamente esauriti per il resto della serata.
                    // La cancellazione multipla resta agli admin: una cassa puo'
                    // annullare solo il proprio ultimo ordine, uno alla volta.
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                        ->before(static function (Collection $records) {
                            /** @var Collection<int, Order> $records */
                            app(OrderStockService::class)->restoreMany($records);
                        }),
                ]),
            ])
            ->selectCurrentPageOnly(false)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'quick-create' => Pages\QuickCreateOrder::route('/quick-create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'print' => Pages\ViewPrintOrder::route('/{record}/print'),
        ];
    }

    /**
     * @param  array<int|string,array<int|string,mixed>>  $orderItems
     */
    public static function getTotal(array $orderItems): string
    {
        $total = 0;
        foreach ($orderItems as $orderItem) {
            $total += $orderItem['row_amount'] ?: 0;
        }

        return number_format($total, 2);
    }

    /**
     * @param  array<int|string,array<int|string,mixed>>  $orderItems
     * @return array<int|string,mixed>
     */
    public static function getSelectedProducts(array $orderItems, int $pId): array
    {
        $res = [];
        foreach ($orderItems as $orderItem) {
            if (((int) $orderItem['product_id']) !== $pId) {
                $res[] = $orderItem['product_id'];
            }
        }

        return $res;
    }

    /**
     * @param  array<int|string,array<int|string,mixed>>  $orderItems
     * @return array<int,string>
     */
    protected static function getQuantityOptions(int $state, int $productId, array $orderItems, ?Order $order): array
    {

        $options = [];
        if (! $productId) {
            for ($i = 1; $i <= Config::intValue('max_qty', 15); $i++) {
                $options[(int) $i] = (string) $i;
            }

            return $options;
        }

        [$qtyValue, $qtyIngredientValue] = self::getQtySel($order, $orderItems);

        $qtySel = $qtyValue[$productId]['new'] ?? 0;
        $qtySel -= $qtyValue[$productId]['old'] ?? 0;
        $qtySel -= $state;
        $product = Product::find($productId);
        $stock = $product->stock ?? 0;
        $backorder = $product->backorder ?? 0;

        for ($i = 1; $i <= Config::intValue('max_qty', 15); $i++) {
            $label = $i;
            if (! $backorder && ($i + $qtySel) > ($stock)) {
                $label .= ' ('.__('filament.Out of Stock').')';
            }
            if ($i === $label) {
                foreach ($product->ingredients ?? [] as $ingredient) {
                    if ($ingredient->is_disabled) {
                        continue;
                    }

                    $qtyIngredientSel = $qtyIngredientValue[$ingredient->id]['new'] ?? 0;
                    $qtyIngredientSel -= $qtyIngredientValue[$ingredient->id]['old'] ?? 0;
                    $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                    $qtyIngredientSel -= ($state * $qty);
                    if (($ingredient->stock) < ($qty * $i) + $qtyIngredientSel) {
                        $label .= ' ('.__('filament.Ingredient Out of Stock').')';
                        break;
                    }
                }
            }
            $options[(int) $i] = (string) $label;
        }

        return $options;
    }

    /**
     * @return Collection<int,string>
     */
    public static function getProducts(callable $get, ?string $search = null): Collection
    {
        // Il select prodotto viene rivalutato per ogni riga del repeater a ogni
        // ricalcolo: senza memoizzazione l'elenco dei prodotti della coda veniva
        // riletto dal database una volta per riga.
        $queueId = (int) $get('../../queue_id');
        $cacheKey = $queueId.'|'.($search ?? '');

        if (! array_key_exists($cacheKey, self::$queueProductsCache)) {
            self::$queueProductsCache[$cacheKey] = Product::whereIsDisabled(false)
                ->join('product_queue', 'products.id', '=', 'product_queue.product_id')
                ->orderBy('products.order')
                ->select('products.*')
                ->where('product_queue.queue_id', $queueId)
                ->when($search, fn ($q, $search) => $q->where('products.name', 'like', "%{$search}%"))
                ->get()
                ->pluck('label', 'id');
        }

        $products = self::$queueProductsCache[$cacheKey];
        $repeatProducts = Config::value('repeat_products') ?? 0;
        $selectedProducts = [];
        if (! $repeatProducts) {
            $selectedProducts = static::getSelectedProducts($get('../../orderItems'), (int) $get('product_id'));
        }

        $response = $products->except($selectedProducts)
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (string) $value]);

        return $response;
    }

    /**
     * Quantità per prodotto e per ingrediente, confrontando lo stato salvato
     * dell'ordine ("old") con quello attualmente nel form ("new").
     *
     * @param  array<int|string,array<int|string,mixed>>  $orderItems
     * @return array<int|string,array<int|string,mixed>>
     */
    public static function getQtySel(?Order $order, array $orderItems): array
    {
        $qtyValue = [];
        $qtyIngredientValue = [];

        // Evita il lazy load di product+ingredients per ogni riga già salvata.
        $order?->loadMissing('orderItems.product.ingredients');

        foreach ($order->orderItems ?? [] as $orderItem) {
            // product_id è nullable (prodotto cancellato dopo l'ordine): come
            // chiave diventerebbe '' qui e 0 nel ciclo sotto, quindi le due voci
            // non si riconcilierebbero mai. Senza prodotto non c'è nulla da
            // confrontare.
            if ($orderItem->product_id === null) {
                continue;
            }

            self::accumulaQta($qtyValue, $orderItem->product_id, 'old', $orderItem->quantity);
            self::accumulaIngredienti(
                $qtyIngredientValue,
                $orderItem->product?->ingredients,
                'old',
                $orderItem->quantity
            );
        }

        $products = self::prodottiDelForm($orderItems);

        foreach ($orderItems as $orderItem) {
            $productId = (int) $orderItem['product_id'];
            $quantity = (int) $orderItem['quantity'];

            self::accumulaQta($qtyValue, $productId, 'new', $quantity);
            self::accumulaIngredienti(
                $qtyIngredientValue,
                $products->get($productId)?->ingredients,
                'new',
                $quantity
            );
        }

        return [$qtyValue, $qtyIngredientValue];
    }

    /**
     * Prodotti citati dalle righe del form, in una sola query.
     *
     * Prima si eseguiva una find() per riga, dentro un metodo già rivalutato
     * per ogni riga del repeater: il costo cresceva col quadrato delle righe.
     *
     * @param  array<int|string,array<int|string,mixed>>  $orderItems
     * @return Collection<int,Product>
     */
    private static function prodottiDelForm(array $orderItems): Collection
    {
        $productIds = collect($orderItems)
            ->pluck('product_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->all();

        if ($productIds === []) {
            return collect();
        }

        return Product::with('ingredients')->findMany($productIds)->keyBy('id');
    }

    /**
     * @param  array<int|string,array<string,int>>  $totali
     */
    private static function accumulaQta(array &$totali, int $key, string $slot, int $quantity): void
    {
        $totali[$key] ??= ['old' => 0, 'new' => 0];
        $totali[$key][$slot] += $quantity;
    }

    /**
     * @param  array<int|string,array<string,int>>  $totali
     * @param  iterable<Ingredient>|null  $ingredients
     */
    private static function accumulaIngredienti(array &$totali, ?iterable $ingredients, string $slot, int $quantity): void
    {
        foreach ($ingredients ?? [] as $ingredient) {
            if ($ingredient->is_disabled) {
                continue;
            }

            $qty = (int) ($ingredient->pivot?->getAttributeValue('qty') ?? 0);
            self::accumulaQta($totali, $ingredient->id, $slot, $quantity * $qty);
        }
    }
}
