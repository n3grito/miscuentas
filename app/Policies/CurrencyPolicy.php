<?php

namespace App\Policies;

class CurrencyPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'currency';
    }
}