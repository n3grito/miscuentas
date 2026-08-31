<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Product;
use App\Models\Purchase;
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

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    protected static ?string $slug = 'purchases';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la compra')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('third_party_id')
                            ->label('Proveedor')
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
                                    ->label('Producto')
                                    ->relationship(
                                        'product',
                                        'name',
                                        fn ($query) => $query->where('type', 'product')->where('track_inventory', true),
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
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo unitario')
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
                                    ->sum(fn ($item) => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0));
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
                Infolists\Components\Section::make('Compra')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Referencia')
                            ->badge(),
                        Infolists\Components\TextEntry::make('thirdParty')
                            ->label('Proveedor')
                            ->state(fn (Purchase $record): string => $record->thirdParty?->displayName() ?? '—'),
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Almacén'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'received' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'received' => 'Recibida',
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
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('received_at')
                            ->label('Recibida el')
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
                                Infolists\Components\TextEntry::make('unit_cost')
                                    ->label('Costo unitario')
                                    ->numeric(2),
                                Infolists\Components\TextEntry::make('total_cost')
                                    ->label('Costo total')
                                    ->numeric(2),
                            ])
                            ->columns(4),
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
                Tables\Columns\TextColumn::make('supplier')
                    ->label('Proveedor')
                    ->state(fn (Purchase $record): string => $record->thirdParty?->displayName() ?? '—'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Productos')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax')
                    ->label('Impuestos')
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->numeric(2)
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'received' => 'Recibida',
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
                        'received' => 'Recibida',
                        'cancelled' => 'Cancelada',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('third_party_id')
                    ->label('Proveedor')
                    ->relationship('thirdParty', 'full_name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->displayName())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn (Purchase $record): bool => $record->status !== 'draft'),
                static::receiveAction(),
                static::cancelAction(),
            ]);
    }

    public static function receiveAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('receive')
            ->label('Recibir')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Recibir compra')
            ->modalDescription('Se ingresará el stock de los productos al almacén indicado actualizando el costo promedio. ¿Desea continuar?')
            ->modalSubmitActionLabel('Recibir')
            ->hidden(fn (Purchase $record): bool => $record->status !== 'draft')
            ->action(function (Purchase $record) {
                try {
                    app(InventoryService::class)->receivePurchase($record, auth()->id());

                    Notification::make()
                        ->title('Compra recibida')
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
            ->modalHeading('Cancelar compra')
            ->modalDescription('La compra quedará cancelada y no ingresará stock. ¿Desea continuar?')
            ->modalSubmitActionLabel('Cancelar compra')
            ->hidden(fn (Purchase $record): bool => $record->status !== 'draft')
            ->action(function (Purchase $record) {
                $record->update(['status' => 'cancelled']);

                Notification::make()
                    ->title('Compra cancelada')
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
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
            'view' => Pages\ViewPurchase::route('/{record}'),
        ];
    }
}