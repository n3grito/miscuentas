<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\PermissionSync;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    private array $pendingPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['grouped_permissions'] = PermissionSync::buildGroupedState($this->getRecord());

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->pendingPermissions = $data['grouped_permissions'] ?? [];
        unset($data['grouped_permissions']);

        $record = parent::handleRecordUpdate($record, $data);

        PermissionSync::syncFromGroupedState($record, $this->pendingPermissions);

        return $record;
    }
}