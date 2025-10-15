<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogoResource\Pages;
use App\Models\Logo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class LogoResource extends Resource
{
    protected static ?string $model = Logo::class;

    protected static ?string $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function getLabel(): ?string
    {
        return __('filament.logo_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(static::$navigationGroup);
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.logo_label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form
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
                Forms\Components\Toggle::make('is_default')
                    ->label(__('filament.Is Default')),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label(__('filament.ID')),
                Tables\Columns\TextColumn::make('path')
                    ->copyable()
                    ->label(__('filament.Path')),
                Tables\Columns\ToggleColumn::make('is_default')
                    ->label(__('filament.Is Default'))
                    ->afterStateUpdated(static function ($state, $record) {
                        if ($state) {
                            Logo::where('id', '!=', $record->id)
                                ->update(['is_default' => false]);
                        }
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListLogos::route('/'),
            'create' => Pages\CreateLogo::route('/create'),
            'view' => Pages\ViewLogo::route('/{record}'),
        ];
    }
}
