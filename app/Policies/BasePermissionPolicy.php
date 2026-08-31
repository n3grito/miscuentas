<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BasePermissionPolicy
{
    abstract protected function resourceKey(): string;

    protected function ability(string $action, ?Model $record = null): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $permission = "{$action}_{$this->resourceKey()}";

        return $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->ability('view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->ability('view');
    }

    public function create(User $user): bool
    {
        return $this->ability('create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->ability('update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->ability('delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->ability('delete_any');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->ability('restore');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->ability('force_delete');
    }

    public function replicate(User $user, Model $model): bool
    {
        return false;
    }
}