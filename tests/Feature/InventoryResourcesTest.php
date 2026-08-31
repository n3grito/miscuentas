<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class InventoryResourcesTest extends TestCase
{
    public function test_inventory_resources_are_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $paths = [
            '/admin/categories',
            '/admin/units',
            '/admin/warehouses',
            '/admin/products',
            '/admin/inventory-movements',
            '/admin/stock-transfers',
            '/admin/boms',
            '/admin/productions',
            '/admin/stock-alerts',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_inventory_resources_are_forbidden_without_permissions(): void
    {
        $user = User::create([
            'name' => 'Usuario sin permisos '.rand(1000, 9999),
            'email' => 'sinpermisos'.rand(1000, 9999).'@miscuentas.test',
            'password' => bcrypt('password'),
        ]);

        $paths = [
            '/admin/categories',
            '/admin/units',
            '/admin/warehouses',
            '/admin/products',
            '/admin/inventory-movements',
            '/admin/stock-transfers',
            '/admin/boms',
            '/admin/productions',
            '/admin/stock-alerts',
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertForbidden();
        }
    }
}