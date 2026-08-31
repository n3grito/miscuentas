<?php

namespace App\Policies;

class SalePolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'sale';
    }
}