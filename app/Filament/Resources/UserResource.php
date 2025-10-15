<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function getLabel(): ?string
    {
        return __('filament.user_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(static::$navigationGroup);
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament.user_label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('filament.Name'))
                    ->required(),
                Forms\Components\TextInput::make('code')
                    ->label(__('filament.Code')),
                Forms\Components\TextInput::make('email')
                    ->label(__('filament.Email'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Hidden::make('password'),
                Forms\Components\TextInput::make('passwordS1')
                    ->label(__('filament.Password'))
                    ->password()
                    ->regex('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).*$/i')
                    ->minLength(8)
                    ->hiddenOn('view'),
                Forms\Components\TextInput::make('passwordS2')
                    ->label(__('filament.ConfirmPassword'))
                    ->password()
                    ->regex('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).*$/i')
                    ->minLength(8)
                    ->hiddenOn('view'),
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
                Tables\Columns\TextColumn::make('email')
                    ->label(__('filament.Email')),
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament.Code')),
            ])
            ->filters([
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
        //            ->emptyStateActions([
        //                Tables\Actions\CreateAction::make(),
        //            ])
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
