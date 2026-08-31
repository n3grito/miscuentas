<?php

namespace App\Policies;

class InventoryMovementPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'inventory_movement';
    }
}