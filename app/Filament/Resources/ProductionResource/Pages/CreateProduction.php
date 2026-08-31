<?php

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\ProductionResource;
use App\Services\NumberSequenceService;
use Filament\Resources\Pages\CreateRecord;

class CreateProduction extends CreateRecord
{
    protected static string $resource = ProductionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference'] = app(NumberSequenceService::class)->next('production', 'PROD-');
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}