<?php

namespace App\Policies;

class SettingPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'setting';
    }
}