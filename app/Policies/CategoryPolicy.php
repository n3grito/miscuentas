<?php

namespace App\Policies;

class CategoryPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'category';
    }
}