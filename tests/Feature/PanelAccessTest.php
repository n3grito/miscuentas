<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class PanelAccessTest extends TestCase
{
    private User $superAdmin;

    private User $plainUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->plainUser = User::firstOrCreate(
            ['email' => 'plain@example.com'],
            [
                'name' => 'Sin Permisos',
                'password' => 'password',
                'is_active' => true,
            ]
        );
    }

    public function test_superadmin_can_access_all_admin_resources(): void
    {
        $pages = [
            '/admin',
            '/admin/users',
            '/admin/users/create',
            '/admin/roles',
            '/admin/third-parties',
            '/admin/currencies',
            '/admin/activity-logs',
            '/admin/settings',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->superAdmin)
                ->get($page)
                ->assertOk();
        }
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $this->actingAs($this->plainUser)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_user_without_permissions_is_forbidden_from_settings(): void
    {
        $this->actingAs($this->plainUser)
            ->get('/admin/settings')
            ->assertForbidden();
    }
}