<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QueueResource\Pages;
use App\Models\Queue;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class QueueResource extends Resource
{
    protected static ?string $model = Queue::class;

    protected static string|null|\UnitEnum $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 2;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-queue-list';

    public static function getLabel(): ?string
    {
        return __('filament.queue_label');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        if (static::$navigationGroup instanceof \UnitEnum) {
            return static::$navigationGroup;
        }

        return __(static::$navigationGroup);
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.queue_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('filament.Name')),
                Forms\Components\TextInput::make('comment')
                    ->required()
                    ->label(__('filament.Comment')),
                // La colonna è smallint unsigned: fuori da 0..65535 il database
                // rifiutava il valore con un errore SQL e una pagina 500.
                Forms\Components\TextInput::make('order_number')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->required()
                    ->default(0)
                    ->label(__('filament.Order Number')),
                // La colonna è nullable e NULL significa "coda mai azzerata":
                // con il campo obbligatorio non si poteva salvare una modifica a
                // una coda mai resettata senza inventare una data, falsando il
                // conteggio dei venduti che parte proprio da reset_at.
                Forms\Components\DateTimePicker::make('reset_at')
                    ->default(Carbon::now())
                    ->label(__('filament.Reset Date')),
                Forms\Components\Toggle::make('is_disabled')
                    ->label(__('filament.Is Disabled')),
                Forms\Components\Toggle::make('is_default')
                    ->label(__('filament.Is Default')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('filament.ID')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.Name')),
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('filament.Comment')),
                Tables\Columns\ToggleColumn::make('is_default')
                    ->label(__('filament.Is Default'))
                    ->afterStateUpdated(static function ($state, $record) {
                        if ($state) {
                            Queue::where('id', '!=', $record->id)
                                ->update(['is_default' => false]);
                        }
                    }),
                Tables\Columns\TextColumn::make('order_number')
                    ->label(__('filament.Order Number')),
                Tables\Columns\TextColumn::make('reset_at')
                    ->label(__('filament.Reset Date')),
                Tables\Columns\ToggleColumn::make('is_disabled')
                    ->label(__('filament.Is Disabled')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetNumber')
                    ->label(__('filament.reset_number'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($record) {
                        $record->update([
                            'order_number' => 0,
                            'reset_at' => Carbon::now(),
                        ]);
                    })
                    ->color('danger')
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListQueues::route('/'),
            'create' => Pages\CreateQueue::route('/create'),
            'edit' => Pages\EditQueue::route('/{record}/edit'),
        ];
    }
}
