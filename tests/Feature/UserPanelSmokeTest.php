<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class UserPanelSmokeTest extends TestCase
{
    public function test_operational_user_panel_pages_render_for_permitted_user(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'operador.smoke@miscuentas.test'],
            ['name' => 'Operador Smoke', 'password' => 'password', 'is_active' => true]
        );

        $user->syncPermissions([
            'view_any_sale',
            'view_any_purchase',
            'view_any_product',
            'view_any_third_party',
            'view_any_invoice',
            'view_any_warehouse',
            'view_any_inventory_movement',
            'view_any_stock_transfer',
            'view_any_stock_alert',
            'view_any_bom',
            'view_any_production',
            'use_pos',
            'view_reports',
            'adjust_inventory',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $urls = [
            '/panel',
            '/panel/sales',
            '/panel/purchases',
            '/panel/products',
            '/panel/third-parties',
            '/panel/invoices',
            '/panel/warehouses',
            '/panel/inventory-movements',
            '/panel/stock-transfers',
            '/panel/stock-alerts',
            '/panel/boms',
            '/panel/productions',
            '/panel/pos',
            '/panel/inventario/ajuste-stock',
            '/panel/reportes/valorizacion',
            '/panel/reportes/movimientos',
            '/panel/reportes/resumen',
            '/panel/reportes/balance-comprobacion',
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertSame(
                200,
                $response->status(),
                "La página {$url} no responde correctamente en el panel de usuario."
            );
        }
    }
}