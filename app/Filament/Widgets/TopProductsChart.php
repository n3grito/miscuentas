<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Filament\Widgets\ChartWidget;

class TopProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 10 productos más vendidos';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $topProducts = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->selectRaw('products.name as name, SUM(sale_items.quantity) as total_qty')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Unidades vendidas',
                    'data' => $topProducts->pluck('total_qty')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6',
                        '#ec4899',
                        '#06b6d4',
                        '#84cc16',
                        '#f97316',
                        '#6366f1',
                    ],
                ],
            ],
            'labels' => $topProducts->pluck('name')->map(fn ($name) => str($name)->limit(20, '...'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}