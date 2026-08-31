<?php

namespace App\Policies;

class ThirdPartyPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'third_party';
    }
}