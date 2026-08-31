<?php

namespace App\Policies;

class AccountPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'account';
    }
}