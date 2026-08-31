<?php

namespace App\Filament\Pages\Reports;

use App\Models\InventoryMovement;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class MovementsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.pages.reports.movements';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Kardex de movimientos';

    protected static ?string $slug = 'reportes/movimientos';

    protected static ?int $navigationSort = 2;

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
            ->query(InventoryMovement::query()->with(['product', 'warehouse']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? 'Entrada' : 'Salida'),
                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Movimiento')
                    ->badge(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(4)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Costo unitario')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Costo total')
                    ->numeric(2)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Existencia')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(30),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['desde'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Desde '.$data['desde']);
                        }

                        if ($data['hasta'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Hasta '.$data['hasta']);
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Tipo de movimiento')
                    ->options([
                        'purchase' => 'Compra',
                        'sale' => 'Venta',
                        'transfer_in' => 'Traslado (entrada)',
                        'transfer_out' => 'Traslado (salida)',
                        'production' => 'Producción',
                        'adjustment' => 'Ajuste',
                        'initial' => 'Inicial',
                    ]),
            ])
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
                        fputcsv($out, ['Fecha', 'Producto', 'Almacén', 'Tipo', 'Movimiento', 'Cantidad', 'Costo unitario', 'Costo total', 'Existencia', 'Motivo']);

                        InventoryMovement::query()
                            ->with(['product', 'warehouse'])
                            ->orderBy('id')
                            ->chunk(500, function ($rows) use ($out) {
                                foreach ($rows as $row) {
                                    fputcsv($out, [
                                        $row->created_at?->format('Y-m-d H:i:s'),
                                        $row->product->name,
                                        $row->warehouse->name,
                                        $row->type === 'in' ? 'Entrada' : 'Salida',
                                        $row->movement_type,
                                        number_format((float) $row->quantity, 4, '.', ''),
                                        number_format((float) $row->unit_cost, 6, '.', ''),
                                        number_format((float) $row->total_cost, 6, '.', ''),
                                        number_format((float) $row->balance_after, 4, '.', ''),
                                        $row->reason,
                                    ]);
                                }
                            });

                        fclose($out);
                    }, 'kardex_movimientos.csv');
                }),
        ];
    }
}