<?php

namespace App\Policies;

class InvoicePolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'invoice';
    }
}