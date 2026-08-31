<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class AdminPagesSmokeTest extends TestCase
{
    public function test_all_panel_pages_render_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $resourceSlugs = [
            'products', 'categories', 'units', 'warehouses', 'third-parties',
            'currencies', 'stock-transfers', 'productions',
            'boms', 'purchases', 'sales',
            'accounts', 'journal-entries',
            'users', 'roles',
        ];

        $indexOnlySlugs = ['inventory-movements', 'stock-alerts', 'activity-logs'];

        $urls = ['/admin'];

        foreach ($resourceSlugs as $slug) {
            $urls[] = "/admin/{$slug}";
            $urls[] = "/admin/{$slug}/create";
        }

        foreach ($indexOnlySlugs as $slug) {
            $urls[] = "/admin/{$slug}";
        }

        // Páginas de detalle de recursos con vista: usa el primer registro si existe.
        $viewModels = [
            'invoices' => \App\Models\Invoice::class,
            'sales' => \App\Models\Sale::class,
            'purchases' => \App\Models\Purchase::class,
            'journal-entries' => \App\Models\JournalEntry::class,
            'stock-transfers' => \App\Models\StockTransfer::class,
            'productions' => \App\Models\Production::class,
        ];

        foreach ($viewModels as $slug => $model) {
            $recordId = $model::query()->min('id');

            if ($recordId !== null) {
                $urls[] = "/admin/{$slug}/{$recordId}";
            }
        }

        $urls[] = '/admin/reportes/valorizacion';
        $urls[] = '/admin/reportes/movimientos';
        $urls[] = '/admin/reportes/resumen';
        $urls[] = '/admin/reportes/balance-comprobacion';
        $urls[] = '/admin/settings';

        foreach ($urls as $url) {
            $response = $this->actingAs($admin)->get($url);

            $this->assertSame(
                200,
                $response->status(),
                "La página {$url} no responde correctamente."
            );
        }
    }
}
