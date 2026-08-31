<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

class SpanishUITest extends TestCase
{
    public function test_laravel_validation_messages_are_in_spanish(): void
    {
        $validator = Validator::make(
            ['email' => ''],
            ['email' => ['required', 'email']],
            ['email.required' => 'El correo es obligatorio']
        );

        $this->assertTrue($validator->fails());
        $this->assertEquals('El correo es obligatorio', $validator->errors()->first('email'));

        $validator = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertStringContainsString('obligatorio', $validator->errors()->first('name'));
    }

    public function test_login_page_renders_in_spanish(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Inicia sesión');
        $response->assertSee('Correo electrónico');
        $response->assertSee('Iniciar sesión');
    }

    public function test_dashboard_renders_in_spanish(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Usuarios');
        $response->assertSee('Configuración');
    }
}