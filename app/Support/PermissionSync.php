<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSync
{
    /**
     * Sincroniza permisos de un rol o usuario a partir del estado agrupado del formulario.
     *
     * @param  array<string, mixed>  $groupedState
     */
    public static function syncFromGroupedState(Model $model, array $groupedState): void
    {
        $ids = PermissionGroups::extractIds($groupedState);

        if ($ids === []) {
            $model->syncPermissions([]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $validIds = Permission::query()->whereIn('id', $ids)->pluck('id')->all();

        $model->syncPermissions($validIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Construye el estado agrupado (clave de módulo => ids seleccionados)
     * para hidratar el formulario de un rol o usuario.
     *
     * @return array<string, array<int, int>>
     */
    public static function buildGroupedState(Model $model): array
    {
        $selected = $model->permissions->pluck('id', 'name');

        $state = [];

        foreach (PermissionGroups::modules() as $module => $keys) {
            $names = [];

            foreach ($keys as $key) {
                $names = array_merge($names, PermissionGroups::permissionNamesForResource($key));
            }

            $state[PermissionGroups::keyFor($module)] = $selected
                ->only($names)
                ->values()
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $state;
    }

    /**
     * Asigna permisos directos a un usuario (sin pasar por roles).
     *
     * @param  array<int, int|string>  $permissionNamesOrIds
     */
    public static function giveDirect(User $user, array $permissionNamesOrIds): void
    {
        $permissions = [];

        foreach ($permissionNamesOrIds as $value) {
            $permissions[] = is_numeric($value)
                ? Permission::findOrFail((int) $value)
                : Permission::where('name', $value)->firstOrFail();
        }

        $user->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}