<?php

namespace App\Policies;

class ProductPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'product';
    }
}