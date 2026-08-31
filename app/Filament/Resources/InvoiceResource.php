<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Facturación';

    protected static ?string $modelLabel = 'Factura';

    protected static ?string $pluralModelLabel = 'Facturas';

    protected static ?string $slug = 'invoices';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Datos de la factura')
                    ->schema([
                        Infolists\Components\TextEntry::make('number')
                            ->label('Número')
                            ->badge()
                            ->color(fn (Invoice $record): string => $record->status === 'cancelled' ? 'danger' : 'success'),
                        Infolists\Components\TextEntry::make('issue_date')
                            ->label('Fecha de emisión')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'cancelled' ? 'Cancelada' : 'Emitida')
                            ->color(fn (string $state): string => $state === 'cancelled' ? 'danger' : 'success'),
                        Infolists\Components\TextEntry::make('sale.reference')
                            ->label('Venta asociada'),
                        Infolists\Components\TextEntry::make('thirdParty')
                            ->label('Cliente')
                            ->state(fn (Invoice $record): string => $record->thirdParty?->displayName() ?? 'Consumidor final'),
                        Infolists\Components\TextEntry::make('currency.code')
                            ->label('Moneda')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Generada por')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Totales')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('discount')
                            ->label('Descuento')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('tax')
                            ->label('Impuestos')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Total')
                            ->numeric(2)
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Detalle de la venta')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('sale.items')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Producto'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric(),
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Precio unitario')
                                    ->numeric(2),
                                Infolists\Components\TextEntry::make('total_price')
                                    ->label('Importe')
                                    ->numeric(2)
                                    ->weight('bold'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')
                    ->badge()
                    ->color(fn (Invoice $record): string => $record->status === 'cancelled' ? 'danger' : 'primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client')
                    ->label('Cliente')
                    ->state(fn (Invoice $record): string => $record->thirdParty?->displayName() ?? 'Consumidor final'),
                Tables\Columns\TextColumn::make('sale.reference')
                    ->label('Venta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->numeric(2)
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'cancelled' ? 'Cancelada' : 'Emitida')
                    ->color(fn (string $state): string => $state === 'cancelled' ? 'danger' : 'success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(['issued' => 'Emitida', 'cancelled' => 'Cancelada']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.print', $record))
                    ->openUrlInNewTab(),
                static::cancelAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function cancelAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cancel')
            ->label('Cancelar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancelar factura')
            ->modalDescription('La factura quedará marcada como cancelada y no podrá volver a emitirse. ¿Desea continuar?')
            ->modalSubmitActionLabel('Cancelar factura')
            ->hidden(fn (Invoice $record): bool => $record->status !== 'issued')
            ->action(function (Invoice $record) {
                try {
                    app(InvoiceService::class)->cancel($record, auth()->id());

                    Notification::make()->title('Factura cancelada')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}