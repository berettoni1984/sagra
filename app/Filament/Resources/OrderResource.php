<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Config;
use App\Models\Order;
use App\Models\Product;
use App\Models\Queue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
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

    protected static ?string $navigationGroup = 'filament.work';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    /**
     * @param  Order  $record
     */
    public static function canEdit(Model $record): bool
    {
        $userId = auth()->user()?->id;
        $maxId = Order::where('user_id', $userId)->max('id');
        if (
            $record->id < $maxId ||
            $record->user_id !== $userId) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function getNavigationGroup(): ?string
    {
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
    public static function form(Form $form): Form
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

        return $form
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
                        (Config::whereCode('change_price')->first()?->config_value) ||
                        (Config::whereCode('free')->first()?->config_value)
                    )),
                Forms\Components\TextInput::make('total_paid')
                    ->inlineLabel()
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2])
                    ->hidden(fn () => ! (
                        (Config::whereCode('change_price')->first()?->config_value) ||
                        (Config::whereCode('free')->first()?->config_value)
                    ))
                    ->label(__('filament.Total Paid €'))
                    ->readOnly(fn (): bool => ! (Config::whereCode('change_price')->first()?->config_value)),
                Forms\Components\Textarea::make('note')
                    ->label(__('filament.Note'))
                    ->inlineLabel()
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2]),
                Forms\Components\Toggle::make('free')
                    ->label(__('filament.Free'))
                    ->inlineLabel()
                    ->columnSpan(['default' => 2, 'lg' => 4, 'md' => 4, 'sm' => 2])
                    ->hidden(fn (): bool => ! (Config::whereCode('free')->first()?->config_value))
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
                        (Config::whereCode('change_price')->first()?->config_value) ||
                        (Config::whereCode('free')->first()?->config_value)
                    ))
                    ->label(__('filament.Total Paid')),
                Tables\Columns\TextColumn::make('order_items_count')
                    ->label(__('filament.Items'))
                    ->counts('orderItems'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.created_at_column'))
                    ->sortable()
                    ->timezone(Config::whereCode('timezone')->first()?->config_value ?: config('app.timezone'))
                    ->dateTime('H:i d/m/Y'),
                Tables\Columns\TextColumn::make('user_id')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->user->code ?? '')
                    ->label(__('filament.user_label')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->hidden(fn ($record) => static::canEdit($record)),
                Tables\Actions\EditAction::make()
                    ->hidden(fn ($record) => ! static::canEdit($record)),
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->url(fn ($record) => static::getUrl('print', ['record' => $record->id, 'print' => true]))
                    ->label(__('filament.Print')),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->selectCurrentPageOnly(false)
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            if ($orderItem['product_id'] !== $pId) {
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
            for ($i = 1; $i <= (int) (Config::whereCode('max_qty')->first()->config_value ?? 15); $i++) {
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

        for ($i = 1; $i <= (int) (Config::whereCode('max_qty')->first()->config_value ?? 15); $i++) {
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
     * @return Collection<int|string,string>
     */
    public static function getProducts(callable $get, ?string $search = null): Collection
    {
        $products = Product::whereIsDisabled(false)
            ->join('product_queue', 'products.id', '=', 'product_queue.product_id')
            ->orderBy('products.order')
            ->select('products.*')
            ->where('product_queue.queue_id', $get('../../queue_id'))
            ->when($search, fn ($q, $search) => $q->where('products.name', 'like', "%{$search}%"))
            ->get()
            ->pluck('label', 'id');
        $repeatProducts = Config::whereCode('repeat_products')->first()->config_value ?? 0;
        $selectedProducts = [];
        if (! $repeatProducts) {
            $selectedProducts = static::getSelectedProducts($get('../../orderItems'), (int) $get('product_id'));
        }

        $response = $products->except($selectedProducts);

        return $response;
    }

    /**
     * @param  array<int|string,array<int|string,mixed>>  $orderItems
     * @return array<int|string,array<int|string,mixed>>
     */
    public static function getQtySel(?Order $order, array $orderItems): array
    {
        $qtyValue = [];
        $qtyIngredientValue = [];
        foreach ($order->orderItems ?? [] as $orderItem) {
            $productIdTmp = $orderItem->product_id;
            $quantity = $orderItem->quantity;
            if (! isset($qtyValue[$productIdTmp]['old'])) {
                $qtyValue[$productIdTmp] = ['old' => 0, 'new' => 0];
            }
            $qtyValue[$productIdTmp]['old'] += $quantity;
            foreach ($orderItem->product->ingredients ?? [] as $ingredient) {
                if ($ingredient->is_disabled) {
                    continue;
                }
                $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                if (! isset($qtyIngredientValue[$ingredient->id])) {
                    $qtyIngredientValue[$ingredient->id] = ['old' => 0, 'new' => 0];
                }
                $qtyIngredientValue[$ingredient->id]['old'] += ($quantity * $qty);
            }
        }
        foreach ($orderItems as $orderItem) {
            $productIdTmp = (int) $orderItem['product_id'];
            $product = Product::find($productIdTmp);
            $quantity = (int) $orderItem['quantity'];
            if (! isset($qtyValue[$productIdTmp])) {
                $qtyValue[$productIdTmp] = ['old' => 0, 'new' => 0];
            }
            $qtyValue[$productIdTmp]['new'] += $quantity;
            foreach ($product->ingredients ?? [] as $ingredient) {
                if ($ingredient->is_disabled) {
                    continue;
                }
                $qty = $ingredient->pivot?->getAttributeValue('qty') ?? 0;
                if (! isset($qtyIngredientValue[$ingredient->id])) {
                    $qtyIngredientValue[$ingredient->id] = ['old' => 0, 'new' => 0];
                }
                $qtyIngredientValue[$ingredient->id]['new'] += ($quantity * $qty);
            }
        }

        return [$qtyValue, $qtyIngredientValue];
    }
}
