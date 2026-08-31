<?php

namespace App\Policies;

class UserPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'user';
    }
}