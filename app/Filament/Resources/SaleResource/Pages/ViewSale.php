<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInvoice')
                ->label('Generar factura')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === 'completed'
                    && ! $this->getRecord()->invoice
                    && auth()->user()?->can('create_invoice'))
                ->requiresConfirmation()
                ->modalHeading('Generar factura')
                ->modalDescription('Se emitirá una factura con los totales de esta venta. ¿Desea continuar?')
                ->modalSubmitActionLabel('Generar')
                ->action(function () {
                    try {
                        $invoice = app(InvoiceService::class)->createFromSale($this->getRecord(), auth()->id());

                        Notification::make()
                            ->title("Factura {$invoice->number} generada.")
                            ->success()
                            ->send();

                        $this->redirect(SaleResource::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}