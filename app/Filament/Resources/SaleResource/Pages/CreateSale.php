<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\NumberSequenceService;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference'] = app(NumberSequenceService::class)->next('sale', 'VTA-');
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}