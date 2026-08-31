<?php

namespace App\Filament\Resources\StockAlertResource\Pages;

use App\Filament\Resources\StockAlertResource;
use App\Services\StockAlertService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStockAlerts extends ListRecords
{
    protected static string $resource = StockAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan_now')
                ->label('Escanear ahora')
                ->icon('heroicon-o-magnifying-glass-circle')
                ->action(function (StockAlertService $service) {
                    $created = $service->scan();

                    Notification::make()
                        ->title($created > 0 ? "Se detectaron {$created} nueva(s) alerta(s)" : 'No se detectaron nuevas alertas')
                        ->success()
                        ->send();
                }),
        ];
    }
}