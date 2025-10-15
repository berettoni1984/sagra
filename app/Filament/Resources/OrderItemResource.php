<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderItemResource\Pages;
use App\Models\Config;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 */
class OrderItemResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $navigationGroup = 'filament.statistics';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public static function getLabel(): ?string
    {
        return __('filament.order_item_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(static::$navigationGroup);
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.order_item_label_plural');
    }

    /**
     * @throws \Exception
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public static function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->withoutGlobalScopes([
                        SoftDeletingScope::class,
                    ])
                    ->select('order_items.*')
                    ->whereNull('order_items.deleted_at')
                    ->whereNull('orders.deleted_at')
                    ->selectRaw('orders.number as order_number, orders.total_amount as order_total_amount, orders.total_paid as order_total_paid')
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.number')
                    ->sortable()
                    ->label(__('filament.Order Number')),
                Tables\Columns\TextColumn::make('order.queue.label')
                    ->sortable()
                    ->label(__('filament.Order Queue')),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->label(__('filament.product_label')),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable()
                    ->label(__('filament.Qty')),
                Tables\Columns\TextColumn::make('amount')
                    ->money('eur')
                    ->label(__('filament.Price €')),
                Tables\Columns\TextColumn::make('row_amount')
                    ->money('eur')
                    ->label(__('filament.Row Total €')),
                Tables\Columns\TextColumn::make('order.total_amount')
                    ->money('eur')
                    ->label(__('filament.Total')),
                Tables\Columns\TextColumn::make('order.total_paid')
                    ->money('eur')
                    ->label(__('filament.Total Paid')),
                Tables\Columns\TextColumn::make('order.created_at')
                    ->label(__('filament.created_at_column'))
                    ->sortable()
                    ->timezone(Config::whereCode('timezone')->first()?->config_value ?: config('app.timezone'))
                    ->dateTime('H:i d/m/Y'),
                Tables\Columns\TextColumn::make('order.user_id')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->order->user->code ?? '')
                    ->label(__('filament.user_label')),
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
                    })
                    ->label(__('filament.Created At Range')),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label(__('filament.Product Name'))
                    ->options(fn () => Product::all()->pluck('name', 'id'))
                    ->searchable()
                    ->multiple()
                    ->getSearchResultsUsing(fn ($search) => Product::where('name', 'like', "%{$search}%")
                        ->pluck('name', 'id')
                    ),
                Tables\Filters\SelectFilter::make('orders.queue_id')
                    ->label(__('filament.Queue'))
                    ->options(fn () => Queue::all()->pluck('label', 'id'))
                    ->searchable()
                    ->multiple()
                    ->getSearchResultsUsing(fn ($search) => Queue::where('name', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->pluck('label', 'id')
                    ),
                Tables\Filters\SelectFilter::make('orders.user_id')
                    ->label(__('filament.user_label'))
                    ->options(fn () => User::all()->pluck('email', 'id'))
                    ->searchable()
                    ->getSearchResultsUsing(fn ($search) => User::where('email', 'like', "%{$search}%")
                        ->pluck('email', 'id')
                    ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
            ])
            ->bulkActions([
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
            'index' => Pages\ListOrderItems::route('/'),
        ];
    }
}
