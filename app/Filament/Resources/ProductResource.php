<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\IngredientsRelationManager;
use App\Models\Config;
use App\Models\Product;
use App\Models\Queue;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    public static function getLabel(): ?string
    {
        return __('filament.product_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(static::$navigationGroup);
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.product_label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label(__('filament.Name')),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->default(0)
                    ->label(__('filament.Price'))
                    ->numeric(),
                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->label(__('filament.Stock'))
                    ->default(0)
                    ->numeric(),
                Forms\Components\Toggle::make('backorder')
                    ->label(__('filament.Backorder'))
                    ->default(true),
                Forms\Components\TextInput::make('order')
                    ->label(__('filament.order_column'))
                    ->readOnly()
                    ->numeric()
                    ->default(static function () {
                        return Product::max('order') + 1;
                    }),
                Forms\Components\Toggle::make('is_disabled')
                    ->label(__('filament.Is Disabled'))
                    ->default(false),
                Forms\Components\Select::make('queues')
                    ->multiple()
                    ->relationship('queues')
                    ->required()
                    ->options(fn () => Queue::all()->pluck('label', 'id'))
                    ->label(__('filament.queue_label_plural'))
                    ->searchable()
                    ->getSearchResultsUsing(
                        function (string $search) {
                            return Queue::query()
                                ->where('name', 'like', "%{$search}%")
                                ->where('comment', 'like', "%{$search}%")
                                ->get()
                                ->pluck('label', 'id');
                        }
                    )
                    ->default(fn () => [Queue::whereIsDefault(true)->first()?->id]),
            ]);
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    public static function table(Table $table): Table
    {
        return $table
            ->authorizeReorder(true)
            ->reorderable('order')
            ->defaultSort('order', 'asc')
            ->query(
                static function () {

                    return Product::query()
                        ->select([
                            'products.id', 'products.name', 'products.price', 'products.stock', 'products.backorder', 'products.order', 'products.is_disabled',
                            \DB::raw('SUM(order_items.quantity) as order_items_sum_quantity'),
                        ])
                        ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                        ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
                        ->groupBy(['products.id', 'products.name', 'products.price', 'products.stock', 'products.backorder', 'products.order', 'products.is_disabled'])
                        ->with(['queues']);
                }
            )
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label(__('filament.order_column')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.Name')),
                Tables\Columns\TextColumn::make('price')
                    ->money('eur')
                    ->label(__('filament.Price')),
                Tables\Columns\TextInputColumn::make('stock')
                    ->rules(['required', 'numeric'])
                    ->label(__('filament.Stock')),
                Tables\Columns\ToggleColumn::make('backorder')
                    ->label(__('filament.Backorder')),
                Tables\Columns\ToggleColumn::make('is_disabled')
                    ->label(__('filament.Disable')),
                Tables\Columns\TextColumn::make('queues')
                    ->label(__('filament.queue_label_plural'))
                    ->wrap()
                    ->html()
                    ->getStateUsing(
                        function (Product $record) {
                            return $record->queues->pluck('label')->implode('<br>');
                        }
                    ),
                Tables\Columns\TextColumn::make('order_items_sum_quantity')
                    ->label(__('filament.Qty Ordered')),

            ])
            ->filters([
                Filter::make('created_at_range')
                    ->form([
                        Forms\Components\Fieldset::make(__('filament.created_at_range'))
                            ->schema([
                                DateTimePicker::make('created_from')
                                    ->label(__('filament.From')),
                                DateTimePicker::make('created_until')
                                    ->label(__('filament.To')),
                            ])
                            ->label(__('filament.Created At Range')),
                    ])
                    ->query(function (Builder $query, array $data) {
                        try {
                            $from = Carbon::make(
                                $data['created_from'],
                                Config::whereCode('timezone')
                                    ->first()
                                    ?->config_value ?: config('app.timezone')
                            )?->timezone(config('app.timezone'));

                        } catch (\Throwable $e) {
                            $from = null;
                        }
                        try {
                            $to = Carbon::make(
                                $data['created_until'],
                                Config::whereCode('timezone')
                                    ->first()
                                    ?->config_value ?: config('app.timezone')
                            )?->timezone(config('app.timezone'));
                        } catch (\Throwable $e) {
                            $to = null;
                        }
                        if (! $from && ! $to) {
                            return $query;
                        }
                        $query->whereNull('orders.deleted_at')
                            ->whereNull('order_items.deleted_at');

                        if ($from && $to) {
                            $query->whereBetween('orders.created_at', [
                                $from,
                                $to,
                            ]);
                        } elseif ($from) {
                            $query->where('orders.created_at', '>=', $from);
                        } elseif ($to) {
                            $query->where('orders.created_at', '<=', $to);
                        }

                        return $query;
                    })
                    ->label(__('filament.Created At Range')),
                Tables\Filters\Filter::make('queue')
                    ->form([
                        Forms\Components\Select::make('queue')
                            ->label(__('filament.queue_label_plural'))
                            ->multiple()
                            ->options(
                                Queue::all()->pluck('label', 'id')
                            ),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $queues = $data['queue'] ?? [];
                        if (empty($queues)) {
                            return $query;
                        }
                        $ids = \DB::table('product_queue')
                            ->whereIn('queue_id', $queues)
                            ->pluck('product_id')
                            ->toArray();

                        return $query->whereIn('products.id', $ids);
                    }),
                Tables\Filters\SelectFilter::make('products.id')
                    ->label(__('filament.product_label_plural'))
                    ->multiple()
                    ->options(fn () => Product::all()->pluck('name', 'id'))
                    ->searchable()
                    ->getSearchResultsUsing(fn ($search) => Product::where('name', 'like', "%{$search}%")
                        ->pluck('name', 'id')
                    ),
                Tables\Filters\SelectFilter::make('products.is_disabled')
                    ->label(__('filament.Is Disabled'))
                    ->options([
                        0 => __('filament.No'),
                        1 => __('filament.Yes'),
                    ])
                    ->searchable(),
            ])
            ->selectCurrentPageOnly(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('queues')
                        ->label(__('filament.Queue'))
                        ->form([
                            Forms\Components\Select::make('queues')
                                ->label(__('filament.queue_label_plural'))
                                ->multiple()
                                ->options(Queue::all()->pluck('label', 'id'))
                                ->required(),
                        ])
                        ->action(function (Tables\Actions\BulkAction $action, \Illuminate\Support\Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->queues()->sync($data['queues'] ?? null);
                                $record->save();
                            }
                            $action->success();
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            IngredientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
