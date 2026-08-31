<?php

namespace App\Filament\Widgets;

use App\Models\StockAlert;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlertsWidget extends TableWidget
{
    protected static ?string $heading = 'Alertas de stock abiertas';

    protected static ?int $sort = 3;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return StockAlert::query()
            ->where('status', 'open')
            ->with(['product', 'warehouse'])
            ->latest();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén'),
                Tables\Columns\TextColumn::make('current_qty')
                    ->label('Existencia')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'below_min' ? 'danger' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === 'below_min' ? 'Bajo mínimo' : 'Sobre máximo'),
            ])
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_stock_alert') ?? false;
    }
}