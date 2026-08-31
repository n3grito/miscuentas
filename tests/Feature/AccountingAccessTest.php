<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class AccountingAccessTest extends TestCase
{
    public function test_accounting_resources_are_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        foreach (['/admin/accounts', '/admin/journal-entries', '/admin/reportes/balance-comprobacion'] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_accounting_resources_are_forbidden_without_permissions(): void
    {
        $user = User::create([
            'name' => 'Sin permisos contables '.rand(1000, 9999),
            'email' => 'sincontables'.rand(1000, 9999).'@miscuentas.test',
            'password' => 'password',
        ]);

        foreach (['/admin/accounts', '/admin/journal-entries', '/admin/reportes/balance-comprobacion'] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertForbidden();
        }
    }
}
