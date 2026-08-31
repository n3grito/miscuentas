<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\PermissionSync;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    private array $pendingPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingPermissions = $data['grouped_permissions'] ?? [];
        unset($data['grouped_permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        PermissionSync::syncFromGroupedState($this->getRecord(), $this->pendingPermissions);
    }
}