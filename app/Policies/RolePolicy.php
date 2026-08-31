<?php

namespace App\Policies;

class RolePolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'role';
    }
}