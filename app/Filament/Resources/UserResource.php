<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|null|\UnitEnum $navigationGroup = 'filament.settings';

    protected static ?int $navigationSort = 11;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-shield-check';

    /**
     * Gestione utenti riservata agli admin: prima qualunque cassiere poteva
     * aprire /users/{id}/edit e cambiare email e password del titolare.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getLabel(): ?string
    {
        return __('filament.user_label');
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
        return __('filament.user_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
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
                // Nessun campo 'password' nel form: in modifica veniva popolato dal
                // record, serializzando l'hash bcrypt dell'utente nello snapshot
                // Livewire inviato al browser. I due hook mutateFormData*
                // costruiscono già $data['password'] da passwordS1.
                //
                // La regex è senza il flag /i: con /i i lookahead su maiuscole e
                // minuscole diventavano ridondanti e 'abcdefg1!' superava il
                // requisito "deve contenere una maiuscola".
                Forms\Components\TextInput::make('passwordS1')
                    ->label(__('filament.Password'))
                    ->password()
                    ->regex('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).*$/')
                    ->minLength(8)
                    ->hiddenOn('view'),
                Forms\Components\TextInput::make('passwordS2')
                    ->label(__('filament.ConfirmPassword'))
                    ->password()
                    ->regex('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).*$/')
                    ->minLength(8)
                    ->hiddenOn('view'),
                Forms\Components\Select::make('roles')
                    ->label(__('filament.Roles'))
                    ->multiple()
                    ->required()
                    ->options(fn () => Role::query()->pluck('name', 'name'))
                    ->default([User::ROLE_CASSA])
                    ->formatStateUsing(fn ($record) => $record
                        ? $record->roles->pluck('name')->toArray()
                        : [User::ROLE_CASSA])
                    // I ruoli stanno su una pivot, non su users: senza questo
                    // sarebbero trattati come una colonna inesistente.
                    ->saveRelationshipsUsing(function ($component, $state) {
                        $component->getRecord()?->syncRoles($state ?? []);
                    })
                    ->dehydrated(false),
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
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label(__('filament.Roles')),
            ])
            ->filters([
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
        //            ->emptyStateActions([
        //                \Filament\Actions\CreateAction::make(),
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
