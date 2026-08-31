<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionGroups;
use App\Support\PermissionSync;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionsModulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Idempotente: actualiza permisos y roles sin destruir datos de desarrollo.
        $this->seed(SuperAdminSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function plainUser(): User
    {
        return User::create([
            'name' => 'Plain ' . uniqid(),
            'email' => uniqid() . '@perms.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_use_pos_permission_exists_as_standalone(): void
    {
        $this->assertNotNull(Permission::where('name', 'use_pos')->first());
        $this->assertNull(Permission::where('name', 'view_any_use_pos')->first());

        foreach (['view_reports', 'view_logs'] as $standalone) {
            $this->assertNotNull(Permission::where('name', $standalone)->first(), "Falta {$standalone}");
        }
    }

    public function test_new_module_permissions_exist(): void
    {
        foreach ([
            'view_any_invoice', 'create_invoice', 'update_invoice',
            'view_any_account', 'create_account',
            'view_any_journal_entry',
            'view_any_purchase', 'view_any_sale',
        ] as $perm) {
            $this->assertNotNull(Permission::where('name', $perm)->first(), "Falta {$perm}");
        }
    }

    public function test_groups_cover_every_permission_in_database(): void
    {
        $dbNames = Permission::query()->pluck('name')->all();

        $mapped = PermissionGroups::allMappedNames();

        $orphans = array_diff($dbNames, $mapped);
        $phantoms = array_diff($mapped, $dbNames);

        $this->assertSame([], array_values($orphans), 'Permisos sin módulo asignado: ' . implode(', ', $orphans));
        $this->assertSame([], array_values($phantoms), 'Módulos referencian permisos inexistentes: ' . implode(', ', $phantoms));
    }

    public function test_role_sync_from_grouped_state(): void
    {
        $role = Role::create(['name' => 'Vendedor Test ' . uniqid(), 'guard_name' => 'web']);

        $options = PermissionGroups::groupedOptions();

        PermissionSync::syncFromGroupedState($role, [
            'ventas' => array_map('intval', array_keys($options['Ventas'])),
            'punto_de_venta' => [(int) collect($options['Punto de venta'])->keys()->first()],
        ]);

        $this->assertTrue($role->hasPermissionTo('create_sale'));
        $this->assertTrue($role->hasPermissionTo('use_pos'));
        $this->assertFalse($role->hasPermissionTo('create_user'));
        $this->assertFalse($role->hasPermissionTo('view_reports'));

        // El estado agrupado reconstruido coincide con lo asignado.
        $state = PermissionSync::buildGroupedState($role);
        $this->assertCount(count($options['Ventas']), $state['ventas']);
        $this->assertContains(Permission::where('name', 'use_pos')->value('id'), $state['punto_de_venta']);
    }

    public function test_user_direct_permissions_grant_access(): void
    {
        $user = $this->plainUser();

        PermissionSync::giveDirect($user, ['use_pos']);

        $this->assertTrue($user->can('use_pos'));

        PermissionSync::giveDirect($user, ['view_reports']);

        $this->assertTrue($user->fresh()->can('view_reports'));
    }

    public function test_user_without_permissions_cannot_see_administration(): void
    {
        $user = $this->plainUser();

        foreach (['view_any_user', 'view_any_role', 'view_any_setting', 'use_pos', 'view_reports'] as $ability) {
            $this->assertFalse($user->can($ability), "No debería tener {$ability}");
        }
    }
}
