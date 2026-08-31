<?php

namespace App\Policies;

class JournalEntryPolicy extends BasePermissionPolicy
{
    protected function resourceKey(): string
    {
        return 'journal_entry';
    }
}