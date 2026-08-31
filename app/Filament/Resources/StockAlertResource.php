<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAlertResource\Pages;
use App\Models\StockAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockAlertResource extends Resource
{
    protected static ?string $model = StockAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Alerta de stock';

    protected static ?string $pluralModelLabel = 'Alertas de stock';

    protected static ?string $slug = 'stock-alerts';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('info')
                    ->content('Las alertas de stock se generan automáticamente mediante el escaneo periódico de umbrales mínimos y máximos.'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Alerta')
                    ->schema([
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Producto'),
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Almacén'),
                        Infolists\Components\TextEntry::make('current_qty')
                            ->label('Existencia actual')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('min_stock')
                            ->label('Stock mínimo')
                            ->numeric()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('max_stock')
                            ->label('Stock máximo')
                            ->numeric()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('level')
                            ->label('Nivel')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'below_min' ? 'danger' : 'warning')
                            ->formatStateUsing(fn (string $state): string => $state === 'below_min' ? 'Por debajo del mínimo' : 'Por encima del máximo'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'open' ? 'danger' : 'success')
                            ->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Abierta' : 'Resuelta'),
                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resuelta el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén'),
                Tables\Columns\TextColumn::make('current_qty')
                    ->label('Existencia')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('max_stock')
                    ->label('Máximo')
                    ->numeric(4)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'below_min' ? 'danger' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === 'below_min' ? 'Bajo mínimo' : 'Sobre máximo'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'open' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Abierta' : 'Resuelta')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('status')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Abierta',
                        'resolved' => 'Resuelta',
                    ]),
                Tables\Filters\SelectFilter::make('level')
                    ->label('Nivel')
                    ->options([
                        'below_min' => 'Por debajo del mínimo',
                        'over_max' => 'Por encima del máximo',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
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
            'index' => Pages\ListStockAlerts::route('/'),
            'view' => Pages\ViewStockAlert::route('/{record}'),
        ];
    }
}