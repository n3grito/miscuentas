<?php

namespace App\Policies;

class PurchasePolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'purchase';
    }
}