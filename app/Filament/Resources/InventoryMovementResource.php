<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Movimiento de inventario';

    protected static ?string $pluralModelLabel = 'Kardex (movimientos de inventario)';

    protected static ?string $slug = 'inventory-movements';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('info')
                    ->content('Este registro es de solo lectura. Los movimientos se generan desde compras, ventas, transferencias y producciones.'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Movimiento')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Producto'),
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Almacén'),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                        Infolists\Components\TextEntry::make('movement_type')
                            ->label('Movimiento')
                            ->badge(),
                        Infolists\Components\TextEntry::make('quantity')
                            ->label('Cantidad')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('unit_cost')
                            ->label('Costo unitario')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('total_cost')
                            ->label('Costo total')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('balance_after')
                            ->label('Existencia después')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('reason')
                            ->label('Motivo'),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Registrado por'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
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
                    ->label('Costo unit.')
                    ->numeric(6),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Existencia')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Registrado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'in' => 'Entrada',
                        'out' => 'Salida',
                    ]),
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Movimiento')
                    ->options([
                        'initial' => 'Inicial',
                        'purchase' => 'Compra',
                        'sale' => 'Venta',
                        'production' => 'Producción',
                        'transfer_in' => 'Entrada por transferencia',
                        'transfer_out' => 'Salida por transferencia',
                        'adjustment' => 'Ajuste',
                        'scrap' => 'Merma',
                        'return_in' => 'Devolución de entrada',
                        'return_out' => 'Devolución de salida',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->label('Rango de fechas')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListInventoryMovements::route('/'),
            'view' => Pages\ViewInventoryMovement::route('/{record}'),
        ];
    }
}