<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Ingredient;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    /**
     * Ingredienti non ancora collegati al prodotto corrente.
     *
     * @return Builder<Ingredient>
     */
    public function ingredientiCollegabili(): Builder
    {
        return Ingredient::query()
            ->whereDoesntHave(
                'products',
                fn ($query) => $query->whereKey($this->getOwnerRecord()->getKey())
            );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Forms\Components\TextInput::make('qty')
                    ->label(__('filament.Quantity'))
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.Ingredient')),
                // qty sta sulla pivot product_ingredient, non su ingredients:
                // come 'qty' la colonna risultava sempre vuota.
                Tables\Columns\TextColumn::make('pivot.qty')
                    ->label(__('filament.Quantity')),
            ])
            ->filters([
                //
            ])
            ->headerActions([

                AttachAction::make()
                    ->schema(fn (AttachAction $action): array => [
                        Forms\Components\Select::make('recordId')
                            ->label(__('filament.Ingredient'))
                            ->required()
                            ->searchable()
                            // Sostituire lo schema dell'azione scavalca il select
                            // predefinito di Filament, che escluderebbe da solo gli
                            // ingredienti già collegati. Senza questo filtro si
                            // poteva collegare due volte lo stesso ingrediente,
                            // raddoppiando lo scarico di magazzino a ogni vendita.
                            ->getSearchResultsUsing(
                                fn (string $search) => $this->ingredientiCollegabili()
                                    ->where('name', 'like', "%{$search}%")
                                    ->pluck('name', 'id')
                            )
                            ->options(
                                fn () => $this->ingredientiCollegabili()->pluck('name', 'id')
                            ),
                        Forms\Components\TextInput::make('qty')
                            ->label(__('filament.Quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
