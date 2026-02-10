<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Ingredient;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

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
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('filament.Quantity')),
            ])
            ->filters([
                //
            ])
            ->headerActions([

                \Filament\Actions\AttachAction::make()
                    ->form(fn (\Filament\Actions\AttachAction $action): array => [
                        Forms\Components\Select::make('recordId')
                            ->label(__('filament.Ingredient'))
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(
                                function (string $search) {
                                    return Ingredient::query()
                                        ->where('name', 'like', "%{$search}%")
                                        ->pluck('name', 'id');
                                }
                            )
                            ->options(
                                function () {
                                    return Ingredient::query()
                                        ->pluck('name', 'id');
                                }
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
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DetachAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
