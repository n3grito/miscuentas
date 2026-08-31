<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\PermissionSync;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private array $pendingPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingPermissions = $data['direct_permissions'] ?? [];
        unset($data['direct_permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        PermissionSync::syncFromGroupedState($this->getRecord(), $this->pendingPermissions);
    }
}