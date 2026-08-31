<?php

namespace App\Policies;

class BomPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'bom';
    }
}