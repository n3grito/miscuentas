<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\NumberSequenceService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference'] = app(NumberSequenceService::class)->next('purchase', 'COM-');
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}