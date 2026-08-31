<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class SaleAccessTest extends TestCase
{
    public function test_sales_resource_is_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/sales')
            ->assertOk();
    }

    public function test_sales_resource_is_forbidden_without_permissions(): void
    {
        $user = User::create([
            'name' => 'Sin permisos ventas '.rand(1000, 9999),
            'email' => 'sinventas'.rand(1000, 9999).'@miscuentas.test',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->get('/admin/sales')
            ->assertForbidden();
    }
}