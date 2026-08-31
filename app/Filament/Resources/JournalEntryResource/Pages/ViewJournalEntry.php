<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Services\JournalService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('post')
                ->label('Contabilizar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Contabilizar asiento')
                ->modalDescription('Se validará que Debe y Haber coincidan y el asiento quedará inmutable. ¿Desea continuar?')
                ->modalSubmitActionLabel('Contabilizar')
                ->action(function () {
                    try {
                        app(JournalService::class)->post($this->getRecord(), auth()->id());

                        Notification::make()
                            ->title('Asiento contabilizado')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\EditAction::make()
                ->visible(fn (): bool => $this->getRecord()->status === 'draft'),
        ];
    }
}