<?php

namespace App\Filament\Pages\Reports;

use App\Models\Inventory;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Pages\Page;

class OperationsSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.reports.operations-summary';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Resumen de operaciones';

    protected static ?string $slug = 'reportes/resumen';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    public function getViewData(): array
    {
        $inventoryValue = (float) Inventory::query()
            ->selectRaw('COALESCE(SUM(quantity * average_cost), 0) as total')
            ->value('total');

        $purchasesMonth = (float) Purchase::query()
            ->where('status', 'received')
            ->whereBetween('received_at', [now()->startOfMonth(), now()])
            ->sum('total');

        $salesMonth = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [now()->startOfMonth(), now()]);

        $salesTotalMonth = (float) (clone $salesMonth)->sum('total');
        $salesCostMonth = (float) (clone $salesMonth)->sum('cost_total');

        $daily = collect();

        $purchasesDaily = Purchase::query()
            ->where('status', 'received')
            ->where('received_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(received_at) as period, COUNT(*) as cnt, SUM(total) as amount')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $salesDaily = Sale::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(completed_at) as period, COUNT(*) as cnt, SUM(total) as amount, SUM(cost_total) as cost_amount')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $purchaseRow = $purchasesDaily->get($day);
            $saleRow = $salesDaily->get($day);

            $daily->push([
                'date' => $day,
                'purchases_count' => $purchaseRow?->cnt ?? 0,
                'purchases_total' => (float) ($purchaseRow?->amount ?? 0),
                'sales_count' => $saleRow?->cnt ?? 0,
                'sales_total' => (float) ($saleRow?->amount ?? 0),
                'profit' => (float) ($saleRow?->amount ?? 0) - (float) ($saleRow?->cost_amount ?? 0),
            ]);
        }

        return [
            'inventoryValue' => $inventoryValue,
            'purchasesMonth' => $purchasesMonth,
            'salesTotalMonth' => $salesTotalMonth,
            'salesProfitMonth' => $salesTotalMonth - $salesCostMonth,
            'daily' => $daily->reverse()->values(),
        ];
    }
}