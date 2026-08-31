<?php

namespace Tests\Feature;

use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class ThirdPartiesTest extends TestCase
{
    public function test_customer_can_be_registered(): void
    {
        $customer = ThirdParty::create([
            'type' => 'customer',
            'identity_type' => 'CI',
            'identity_number' => 'CLI-'.rand(100000, 999999),
            'full_name' => 'Cliente de Prueba '.rand(100, 999),
            'phone' => '+53 5'.rand(1000000, 9999999),
            'is_active' => true,
        ]);

        $this->assertModelExists($customer);
        $this->assertStringStartsWith('Cliente de Prueba', $customer->displayName());
        $this->assertTrue(in_array($customer->type, ['customer', 'supplier', 'both']));
    }

    public function test_supplier_with_business_name_is_registered(): void
    {
        $supplier = ThirdParty::create([
            'type' => 'supplier',
            'identity_type' => 'NIT',
            'identity_number' => 'NIT-'.rand(100000, 999999),
            'business_name' => 'Distribuidora S.A. '.rand(100, 999),
            'is_taxpayer' => true,
            'is_active' => true,
        ]);

        $this->assertModelExists($supplier);
        $this->assertStringContainsString('Distribuidora', $supplier->displayName());
    }

    public function test_identity_pair_must_be_unique(): void
    {
        $identity = 'DUP-'.rand(100000, 999999);

        ThirdParty::create([
            'type' => 'customer',
            'identity_type' => 'CI',
            'identity_number' => $identity,
            'full_name' => 'Primero',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ThirdParty::create([
            'type' => 'both',
            'identity_type' => 'CI',
            'identity_number' => $identity,
            'full_name' => 'Duplicado',
        ]);
    }

    public function test_third_parties_page_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin/third-parties')->assertOk();
    }
}
