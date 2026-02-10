<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfigResource\Pages;
use App\Models\Config;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class ConfigResource extends Resource
{
    protected static ?string $model = Config::class;

    protected static string|null|\UnitEnum $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 7;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cog';

    public static function getLabel(): ?string
    {
        return __('filament.config_label');
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
        return __('filament.config_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->label(__('filament.Code')),
                Forms\Components\TextInput::make('config_value')
                    ->label(__('filament.Value')),
                Forms\Components\Textarea::make('comment')
                    ->label(__('filament.Comment')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament.Code')),
                Tables\Columns\TextColumn::make('config_value')
                    ->label(__('filament.Value')),
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('filament.Comment')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ], position: \Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make(),
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
            'index' => Pages\ListConfigs::route('/'),
            'create' => Pages\CreateConfig::route('/create'),
            'edit' => Pages\EditConfig::route('/{record}/edit'),
        ];
    }
}
