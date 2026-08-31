<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="space-y-4 p-6">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Este mes</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Compras recibidas</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white">{{ number_format($purchasesMonth, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ventas completadas</p>
                        <p class="text-lg font-bold text-gray-950 dark:text-white">{{ number_format($salesTotalMonth, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Utilidad estimada</p>
                        <p class="text-lg font-bold {{ $salesProfitMonth >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ number_format($salesProfitMonth, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="space-y-4 p-6">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Valor del inventario</h3>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($inventoryValue, 2) }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Suma de cantidad × costo promedio en todos los almacenes</p>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="p-6 pb-0">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Últimos 14 días</h3>
        </div>
        <div class="overflow-x-auto p-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4 font-medium">Fecha</th>
                        <th class="py-2 pr-4 font-medium">Compras</th>
                        <th class="py-2 pr-4 font-medium">Total compras</th>
                        <th class="py-2 pr-4 font-medium">Ventas</th>
                        <th class="py-2 pr-4 font-medium">Total ventas</th>
                        <th class="py-2 font-medium">Utilidad ventas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daily as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td class="py-2 pr-4">{{ $row['purchases_count'] }}</td>
                            <td class="py-2 pr-4">{{ number_format($row['purchases_total'], 2) }}</td>
                            <td class="py-2 pr-4">{{ $row['sales_count'] }}</td>
                            <td class="py-2 pr-4">{{ number_format($row['sales_total'], 2) }}</td>
                            <td class="py-2 {{ $row['profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ number_format($row['profit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-center text-gray-500">Sin datos en el período</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
