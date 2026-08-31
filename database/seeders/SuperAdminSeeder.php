<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);

        $this->syncPermissions($superAdmin);

        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@miscuentas.test'],
            [
                'name' => 'Administrador',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );

        $admin->assignRole($superAdmin);

        Currency::upsert(
            [
                ['code' => 'CUP', 'name' => 'Peso Cubano', 'symbol' => 'CUP', 'decimal_places' => 2, 'is_base' => true, 'exchange_rate' => 1],
                ['code' => 'USD', 'name' => 'Dólar Estadounidense', 'symbol' => 'USD', 'decimal_places' => 2, 'is_base' => false, 'exchange_rate' => 120.00],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'decimal_places' => 2, 'is_base' => false, 'exchange_rate' => 130.00],
                ['code' => 'MLC', 'name' => 'Moneda Libremente Convertible', 'symbol' => 'MLC', 'decimal_places' => 2, 'is_base' => false, 'exchange_rate' => 120.00],
            ],
            ['code'],
            ['name', 'symbol', 'decimal_places', 'is_base', 'exchange_rate']
        );

        $settings = [
            ['company', 'name', 'Mi Empresa', 'string', 'Nombre de la empresa'],
            ['company', 'tin', null, 'string', 'Número de identificación fiscal (NIT)'],
            ['company', 'address', null, 'string', 'Dirección'],
            ['company', 'phone', null, 'string', 'Teléfono'],
            ['company', 'email', null, 'string', 'Correo de contacto'],
            ['company', 'currency_id', null, 'integer', 'Moneda base'],
            ['smtp', 'host', null, 'encrypted', 'Host SMTP'],
            ['smtp', 'port', null, 'encrypted', 'Puerto SMTP'],
            ['smtp', 'username', null, 'encrypted', 'Usuario SMTP'],
            ['smtp', 'password', null, 'encrypted', 'Contraseña SMTP'],
            ['smtp', 'encryption', null, 'encrypted', 'Cifrado SMTP'],
            ['smtp', 'from_address', null, 'encrypted', 'Correo remitente'],
            ['smtp', 'from_name', null, 'encrypted', 'Nombre remitente'],
        ];

        foreach ($settings as [$group, $key, $value, $type, $label]) {
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value, 'type' => $type, 'label' => $label]
            );
        }

        Setting::updateOrCreate(
            ['group' => 'accounting', 'key' => 'auto_entries'],
            ['value' => '0', 'type' => 'boolean', 'label' => 'Asientos contables automáticos en compras y ventas']
        );

        Setting::updateOrCreate(
            ['group' => 'logs', 'key' => 'retention_days'],
            ['value' => '180', 'type' => 'integer', 'label' => 'Días de retención del registro de auditoría']
        );

        $this->call(AccountingSeeder::class);
    }

    protected function syncPermissions(Role $role): void
    {
        // Recursos CRUD completos (generan view_any/view/create/update/delete/…).
        $resources = [
            'user',
            'role',
            'third_party',
            'currency',
            'activity_log',
            'setting',
            'category',
            'unit',
            'warehouse',
            'product',
            'inventory_movement',
            'stock_transfer',
            'bom',
            'production',
            'stock_alert',
            'purchase',
            'sale',
            'account',
            'journal_entry',
            'invoice',
        ];

        // Permisos independientes de un recurso CRUD.
        $standalone = [
            'use_pos',
            'view_reports',
            'view_logs',
        ];

        $permissions = [];

        foreach ($resources as $resource) {
            foreach ($this->actionsForResource($resource) as $action) {
                $permissions[] = "{$action}_{$resource}";
            }
        }

        foreach ($standalone as $name) {
            $permissions[] = $name;
        }

        $permissions = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        // Limpia permisos malformados de versiones anteriores (p. ej. view_any_use_pos).
        Permission::query()
            ->where('name', 'like', '%use_pos')
            ->where('name', '!=', 'use_pos')
            ->delete();

        $role->syncPermissions($permissions);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function actionsForResource(string $resource): array
    {
        return [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'restore',
            'force_delete',
        ];
    }
}