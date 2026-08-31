<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;

class ReportsAccessTest extends TestCase
{
    public function test_reports_are_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        foreach ([
            '/admin/reportes/valorizacion',
            '/admin/reportes/movimientos',
            '/admin/reportes/resumen',
        ] as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_reports_are_forbidden_without_permission(): void
    {
        $user = User::create([
            'name' => 'Sin permisos reportes '.rand(1000, 9999),
            'email' => 'sinreportes'.rand(1000, 9999).'@miscuentas.test',
            'password' => 'password',
        ]);

        foreach ([
            '/admin/reportes/valorizacion',
            '/admin/reportes/movimientos',
            '/admin/reportes/resumen',
        ] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertForbidden();
        }
    }

    public function test_valuation_report_shows_stock_value(): void
    {
        $product = Product::create([
            'name' => 'Producto Valorización '.Str::random(6),
            'sku' => 'SKU-VAL-'.Str::random(6),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-VAL-'.Str::random(4)],
            ['name' => 'Almacén VAL', 'is_active' => true]
        );

        app(InventoryService::class)->increase($product, $warehouse->id, 10, 25, 'initial');

        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/reportes/valorizacion')
            ->assertOk()
            ->assertSee('Valor total');
    }

    public function test_operations_summary_shows_totals(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/reportes/resumen')
            ->assertOk()
            ->assertSee('Valor del inventario')
            ->assertSee('Últimos 14 días');
    }
}