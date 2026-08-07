<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogoResource\Pages;
use App\Models\Logo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class LogoResource extends Resource
{
    protected static ?string $model = Logo::class;

    protected static string|null|\UnitEnum $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 9;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-photo';

    /**
     * Il logo finisce su ogni scontrino stampato: è configurazione, quindi
     * riservata agli admin come ConfigResource.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getLabel(): ?string
    {
        return __('filament.logo_label');
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
        return __('filament.logo_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\FileUpload::make('path')
                    ->label(__('filament.Path'))
                    ->required()
                    ->unique()
                    ->disk('public')
                    ->directory('logos')
                    ->preserveFilenames()
                    ->image()
                    ->maxSize(1024)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'])
                    ->columnSpanFull(),
                // La posizione si cambia trascinando le righe in elenco: qui è
                // solo in lettura, come per i prodotti.
                Forms\Components\TextInput::make('order')
                    ->label(__('filament.order_column'))
                    ->helperText(__('filament.logo_order_hint'))
                    ->readOnly()
                    ->numeric()
                    ->default(static function () {
                        return (int) Logo::max('order') + 1;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            // L'ordine delle righe decide il logo stampato sugli scontrini: il
            // primo vince, non c'è più un flag "default".
            ->authorizeReorder(true)
            ->reorderable('order')
            ->defaultSort('order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label(__('filament.ID')),
                Tables\Columns\TextColumn::make('order')
                    ->label(__('filament.order_column')),
                Tables\Columns\TextColumn::make('path')
                    ->copyable()
                    ->label(__('filament.Path')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
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
            'index' => Pages\ListLogos::route('/'),
            'create' => Pages\CreateLogo::route('/create'),
            'view' => Pages\ViewLogo::route('/{record}'),
        ];
    }
}
