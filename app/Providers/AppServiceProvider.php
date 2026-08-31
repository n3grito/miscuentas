<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
    }

    /**
     * Registers every stored permission as a Gate ability and lets the
     * SuperAdmin role bypass any authorization check.
     */
    protected function registerPermissionGates(): void
    {
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('SuperAdmin')) {
                return true;
            }

            return null;
        });

        try {
            Permission::all()->each(function (Permission $permission) {
                Gate::define($permission->name, function ($user) use ($permission) {
                    return $user->hasPermissionTo($permission->name);
                });
            });
        } catch (\Throwable $e) {
            // Ignore when the permissions table does not exist yet (migrations pending).
        }
    }
}