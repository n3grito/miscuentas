<?php

namespace App\Policies;

class UnitPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'unit';
    }
}