<?php

namespace App\Policies;

class StockTransferPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'stock_transfer';
    }
}