<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Services\NumberSequenceService;
use Filament\Resources\Pages\CreateRecord;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference'] = app(NumberSequenceService::class)->next('stock_transfer', 'TRF-');
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}