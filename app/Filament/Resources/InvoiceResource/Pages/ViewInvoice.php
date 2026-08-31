<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('invoices.print', $this->getRecord()))
                ->openUrlInNewTab(),
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar factura')
                ->modalDescription('La factura quedará marcada como cancelada y no podrá volver a emitirse. ¿Desea continuar?')
                ->modalSubmitActionLabel('Cancelar factura')
                ->visible(fn (): bool => $this->getRecord()->status === 'issued')
                ->action(function () {
                    try {
                        app(InvoiceService::class)->cancel($this->getRecord(), auth()->id());

                        Notification::make()->title('Factura cancelada')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}