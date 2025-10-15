<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QueueResource\Pages;
use App\Models\Queue;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class QueueResource extends Resource
{
    protected static ?string $model = Queue::class;

    protected static ?string $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    public static function getLabel(): ?string
    {
        return __('filament.queue_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(static::$navigationGroup);
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.queue_label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('filament.Name')),
                Forms\Components\TextInput::make('comment')
                    ->label(__('filament.Comment')),
                Forms\Components\TextInput::make('order_number')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->label(__('filament.Order Number')),
                Forms\Components\DateTimePicker::make('reset_at')
                    ->default(Carbon::now())
                    ->required()
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resetNumber')
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
