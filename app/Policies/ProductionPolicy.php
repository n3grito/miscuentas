<?php

namespace App\Policies;

class ProductionPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'production';
    }
}