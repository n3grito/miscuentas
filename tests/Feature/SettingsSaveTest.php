<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Illuminate\Foundation\Testing\TestCase;
use Livewire\Livewire;

class SettingsSaveTest extends TestCase
{
    public function test_smtp_settings_are_stored_encrypted(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm([
                'company.name' => 'Mi Empresa de Prueba',
                'smtp.host' => 'smtp.example.com',
                'smtp.port' => '587',
                'smtp.username' => 'user@example.com',
                'smtp.password' => 'secret-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'group' => 'company',
            'key' => 'name',
            'value' => 'Mi Empresa de Prueba',
        ]);

        $host = Setting::where('group', 'smtp')->where('key', 'host')->firstOrFail();
        $this->assertNotEquals('smtp.example.com', $host->value);
        $this->assertEquals('smtp.example.com', decrypt($host->value));

        $password = Setting::where('group', 'smtp')->where('key', 'password')->firstOrFail();
        $this->assertNotEquals('secret-password', $password->value);
        $this->assertEquals('secret-password', decrypt($password->value));
    }
}