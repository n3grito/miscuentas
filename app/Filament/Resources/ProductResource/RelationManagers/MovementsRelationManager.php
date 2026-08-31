<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Kardex (movimientos)';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Movimiento')
                    ->badge(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Costo unitario')
                    ->numeric(6),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Costo total')
                    ->numeric(6),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Existencia')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(30),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ]);
    }
}