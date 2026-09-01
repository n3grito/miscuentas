<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Services\InventoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    protected static ?string $slug = 'sales';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la venta')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('third_party_id')
                            ->label('Cliente')
                            ->relationship('thirdParty', 'full_name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->displayName())
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->preload(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Productos')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Artículo')
                                    ->relationship(
                                        'product',
                                        'name',
                                        fn ($query) => $query->where('is_active', true),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.0001)
                                    ->required(),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Precio unitario')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->live(),
                        Forms\Components\TextInput::make('tax')
                            ->label('Impuestos')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live()
                            ->helperText('Se suma al total del documento.'),
                        Forms\Components\Placeholder::make('totals')
                            ->label('Totales')
                            ->content(function (Get $get): string {
                                $subtotal = collect($get('items') ?? [])
                                    ->sum(fn ($item) => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
                                $tax = (float) ($get('tax') ?? 0);

                                return 'Subtotal: '.number_format($subtotal, 2).' | Impuestos: '.number_format($tax, 2).' | Total: '.number_format($subtotal + $tax, 2);
                            }),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Venta')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Referencia')
                            ->badge(),
                        Infolists\Components\TextEntry::make('thirdParty')
                            ->label('Cliente')
                            ->state(fn (Sale $record): string => $record->thirdParty?->displayName() ?? '—'),
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Almacén'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'completed' => 'Completada',
                                'cancelled' => 'Cancelada',
                                default => 'Borrador',
                            }),
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('tax')
                            ->label('Impuestos')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Total')
                            ->numeric(2)
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('cost_total')
                            ->label('Costo total')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('profit')
                            ->label('Utilidad')
                            ->state(fn (Sale $record): float => (float) $record->subtotal - (float) $record->cost_total)
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Completada el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Productos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
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
                                    ->label('Total precio')
                                    ->numeric(2),
                                Infolists\Components\TextEntry::make('unit_cost')
                                    ->label('Costo unitario')
                                    ->numeric(2)
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('total_cost')
                                    ->label('Costo total')
                                    ->numeric(2)
                                    ->placeholder('—'),
                            ])
                            ->columns(6),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer')
                    ->label('Cliente')
                    ->state(fn (Sale $record): string => $record->thirdParty?->displayName() ?? '—'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Productos')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->numeric(2)
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('cost_total')
                    ->label('Costo')
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                        default => 'Borrador',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('third_party_id')
                    ->label('Cliente')
                    ->relationship('thirdParty', 'full_name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->displayName())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn (Sale $record): bool => $record->status !== 'draft'),
                static::completeAction(),
                static::cancelAction(),
            ]);
    }

    public static function completeAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('complete')
            ->label('Completar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Completar venta')
            ->modalDescription('Se descargará el stock de los productos del almacén indicado al costo promedio. Si no hay existencia suficiente la operación será rechazada. ¿Desea continuar?')
            ->modalSubmitActionLabel('Completar')
            ->hidden(fn (Sale $record): bool => $record->status !== 'draft')
            ->action(function (Sale $record) {
                try {
                    app(InventoryService::class)->completeSale($record, auth()->id());

                    Notification::make()
                        ->title('Venta completada')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function cancelAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cancel')
            ->label('Cancelar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancelar venta')
            ->modalDescription('La venta quedará cancelada y no descargará stock. ¿Desea continuar?')
            ->modalSubmitActionLabel('Cancelar venta')
            ->hidden(fn (Sale $record): bool => $record->status !== 'draft')
            ->action(function (Sale $record) {
                $record->update(['status' => 'cancelled']);

                Notification::make()
                    ->title('Venta cancelada')
                    ->success()
                    ->send();
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
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}