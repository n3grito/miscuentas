<?php

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\ProductionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    public function mount(int|string $record): void
    {
        $model = static::getResource()::getModel()::findOrFail($record);

        if ($model->status !== 'draft') {
            $this->redirect(static::getResource()::getUrl('index'));

            return;
        }

        parent::mount($record);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['reference'] = $this->record->reference;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}