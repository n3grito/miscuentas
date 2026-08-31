<?php

namespace App\Policies;

class StockAlertPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'stock_alert';
    }
}