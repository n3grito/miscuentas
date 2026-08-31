<?php

namespace App\Filament\Pages\Reports;

use App\Models\Inventory;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

class InventoryValuation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.reports.inventory-valuation';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Valorización de inventario';

    protected static ?string $slug = 'reportes/valorizacion';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Inventory::query()
                    ->selectRaw('inventory.*, (inventory.quantity * inventory.average_cost) as value')
                    ->where('quantity', '>', 0);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(4)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()),
                Tables\Columns\TextColumn::make('average_cost')
                    ->label('Costo promedio')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor total')
                    ->numeric(2)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('product.category_id')
                    ->label('Categoría')
                    ->relationship('product.category', 'name')
                    ->preload(),
            ])
            ->defaultSort('value', 'desc')
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fputs($out, "\xEF\xBB\xBF");
                        fputcsv($out, ['Producto', 'SKU', 'Almacén', 'Cantidad', 'Costo promedio', 'Valor total']);

                        Inventory::query()
                            ->where('quantity', '>', 0)
                            ->with(['product', 'warehouse'])
                            ->chunk(500, function ($rows) use ($out) {
                                foreach ($rows as $row) {
                                    fputcsv($out, [
                                        $row->product->name,
                                        $row->product->sku,
                                        $row->warehouse->name,
                                        number_format((float) $row->quantity, 4, '.', ''),
                                        number_format((float) $row->average_cost, 6, '.', ''),
                                        number_format((float) $row->quantity * (float) $row->average_cost, 2, '.', ''),
                                    ]);
                                }
                            });

                        fclose($out);
                    }, 'valorizacion_inventario.csv');
                }),
        ];
    }
}