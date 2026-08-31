<?php

namespace App\Policies;

class WarehousePolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'warehouse';
    }
}