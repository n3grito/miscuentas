<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\PermissionSync;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private array $pendingPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['direct_permissions'] = PermissionSync::buildGroupedState($this->getRecord());

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->pendingPermissions = $data['direct_permissions'] ?? [];
        unset($data['direct_permissions']);

        $record = parent::handleRecordUpdate($record, $data);

        PermissionSync::syncFromGroupedState($record, $this->pendingPermissions);

        return $record;
    }
}