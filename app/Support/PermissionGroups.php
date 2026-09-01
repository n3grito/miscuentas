<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class PermissionGroups
{
    /**
     * Permisos que no derivan de un recurso CRUD (nombre completo, sin prefijo de acción).
     */
    public const STANDALONE = ['use_pos', 'view_reports', 'view_logs', 'adjust_inventory'];

    public const ACTION_LABELS = [
        'view_any' => 'Ver lista',
        'view' => 'Ver',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete_any' => 'Eliminar en lote',
        'delete' => 'Eliminar',
        'restore' => 'Restaurar',
        'force_delete' => 'Eliminar definitivamente',
    ];

    public const RESOURCE_LABELS = [
        'user' => 'usuarios',
        'role' => 'roles',
        'setting' => 'ajustes del sistema',
        'activity_log' => 'registros de auditoría',
        'third_party' => 'clientes y proveedores',
        'currency' => 'monedas',
        'category' => 'categorías',
        'unit' => 'unidades de medida',
        'product' => 'productos',
        'warehouse' => 'almacenes',
        'inventory_movement' => 'movimientos de inventario',
        'stock_transfer' => 'transferencias de stock',
        'stock_alert' => 'alertas de stock',
        'bom' => 'recetas de producción',
        'production' => 'órdenes de producción',
        'purchase' => 'compras',
        'sale' => 'ventas',
        'invoice' => 'facturas',
        'account' => 'cuentas contables',
        'journal_entry' => 'asientos contables',
    ];

    public const SPECIAL_LABELS = [
        'use_pos' => 'Operar el punto de venta',
        'view_reports' => 'Consultar reportes',
        'view_logs' => 'Consultar auditoría del sistema',
        'adjust_inventory' => 'Ajustar stock (entradas y salidas manuales)',
    ];

    /**
     * Módulos funcionales visibles en la asignación de permisos.
     *
     * @return array<string, array<int, string>>
     */
    public static function modules(): array
    {
        return [
            'Administración' => ['user', 'role', 'setting', 'activity_log', 'view_logs'],
            'Clientes y proveedores' => ['third_party'],
            'Monedas' => ['currency'],
            'Productos' => ['category', 'unit', 'product'],
            'Inventario' => ['warehouse', 'inventory_movement', 'stock_transfer', 'stock_alert', 'adjust_inventory'],
            'Producción' => ['bom', 'production'],
            'Compras' => ['purchase'],
            'Ventas' => ['sale'],
            'Punto de venta' => ['use_pos'],
            'Facturación' => ['invoice'],
            'Contabilidad y reportes' => ['account', 'journal_entry', 'view_reports'],
        ];
    }

    /**
     * Nombres de permisos que genera un recurso (o el permiso suelto si es standalone).
     *
     * @return array<int, string>
     */
    public static function permissionNamesForResource(string $key): array
    {
        if (in_array($key, self::STANDALONE, true)) {
            return [$key];
        }

        return array_map(
            fn (string $action): string => "{$action}_{$key}",
            array_keys(self::ACTION_LABELS)
        );
    }

    /**
     * Todos los nombres de permisos cubiertos por los módulos definidos.
     *
     * @return array<int, string>
     */
    public static function allMappedNames(): array
    {
        $names = [];

        foreach (self::modules() as $keys) {
            foreach ($keys as $key) {
                $names = array_merge($names, self::permissionNamesForResource($key));
            }
        }

        return $names;
    }

    public static function humanize(string $name): string
    {
        if (isset(self::SPECIAL_LABELS[$name])) {
            return self::SPECIAL_LABELS[$name];
        }

        foreach (self::ACTION_LABELS as $action => $label) {
            if (str_starts_with($name, "{$action}_")) {
                $resource = substr($name, strlen($action) + 1);

                return $label . ' ' . (self::RESOURCE_LABELS[$resource] ?? str_replace('_', ' ', $resource));
            }
        }

        return str_replace('_', ' ', $name);
    }

    /**
     * Opciones agrupadas por módulo con los IDs reales de la base de datos.
     *
     * @return array<string, array<int, string>>
     */
    public static function groupedOptions(): array
    {
        $out = [];

        foreach (self::modules() as $module => $keys) {
            $names = [];

            foreach ($keys as $key) {
                $names = array_merge($names, self::permissionNamesForResource($key));
            }

            $permissions = Permission::query()
                ->whereIn('name', $names)
                ->orderBy('name')
                ->get(['id', 'name']);

            $out[$module] = $permissions
                ->mapWithKeys(fn (Permission $p): array => [$p->id => self::humanize($p->name)])
                ->all();
        }

        return $out;
    }

    /**
     * Extrae los IDs seleccionados desde el estado agrupado del formulario.
     *
     * @param  array<string, mixed>  $groupedState
     * @return array<int, int>
     */
    public static function extractIds(array $groupedState): array
    {
        $ids = [];

        foreach ($groupedState as $selection) {
            foreach ((array) $selection as $id) {
                if ($id !== null && $id !== '') {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Clave estable para un módulo dentro del estado del formulario.
     */
    public static function keyFor(string $module): string
    {
        return Str::slug($module, '_');
    }
}