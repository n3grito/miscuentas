<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class PurchaseAccessTest extends TestCase
{
    public function test_purchases_resource_is_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/purchases')
            ->assertOk();
    }

    public function test_purchases_resource_is_forbidden_without_permissions(): void
    {
        $user = User::create([
            'name' => 'Sin permisos compras '.rand(1000, 9999),
            'email' => 'sincompras'.rand(1000, 9999).'@miscuentas.test',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->get('/admin/purchases')
            ->assertForbidden();
    }
}