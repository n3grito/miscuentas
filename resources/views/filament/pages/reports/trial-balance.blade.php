<x-filament-panels::page>
    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Debe</p>
            <p class="text-xl font-bold">{{ number_format($totals['debits'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Haber</p>
            <p class="text-xl font-bold">{{ number_format($totals['credits'], 2) }}</p>
        </div>
        <div class="rounded-xl border p-4 shadow-sm {{ $totals['balanced'] ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20' }}">
            <p class="text-sm text-gray-500 dark:text-gray-400">Estado</p>
            <p class="text-xl font-bold">{{ $totals['balanced'] ? 'Balanceado ✓' : 'Descuadrado' }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white shadow-sm dark:bg-gray-800">
        {{ $this->table }}
    </div>
</x-filament-panels::page>