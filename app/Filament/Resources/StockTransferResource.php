<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransferResource\Pages;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Transferencia de stock';

    protected static ?string $pluralModelLabel = 'Transferencias de stock';

    protected static ?string $slug = 'stock-transfers';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la transferencia')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('from_warehouse_id')
                            ->label('Almacén de origen')
                            ->relationship('fromWarehouse', 'name')
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('to_warehouse_id')
                            ->label('Almacén de destino')
                            ->relationship('toWarehouse', 'name')
                            ->required()
                            ->live()
                            ->different('from_warehouse_id'),
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
                                    ->relationship('product', 'name')
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
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transferencia')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Referencia')
                            ->badge(),
                        Infolists\Components\TextEntry::make('fromWarehouse.name')
                            ->label('Almacén de origen'),
                        Infolists\Components\TextEntry::make('toWarehouse.name')
                            ->label('Almacén de destino'),
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
                    ->columns(3),

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
                                    ->numeric()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('total_cost')
                                    ->label('Costo total')
                                    ->numeric()
                                    ->placeholder('—'),
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
                Tables\Columns\TextColumn::make('fromWarehouse.name')
                    ->label('Origen')
                    ->sortable(),
                Tables\Columns\TextColumn::make('toWarehouse.name')
                    ->label('Destino')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Productos')
                    ->counts('items'),
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
                    }),
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
                Tables\Filters\SelectFilter::make('from_warehouse_id')
                    ->label('Almacén origen')
                    ->relationship('fromWarehouse', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('to_warehouse_id')
                    ->label('Almacén destino')
                    ->relationship('toWarehouse', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn (StockTransfer $record): bool => $record->status !== 'draft'),
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
            ->modalHeading('Completar transferencia')
            ->modalDescription('Se moverá el stock de los productos indicados del almacén de origen al de destino. ¿Desea continuar?')
            ->modalSubmitActionLabel('Completar')
            ->hidden(fn (StockTransfer $record): bool => $record->status !== 'draft')
            ->action(function (StockTransfer $record) {
                try {
                    app(InventoryService::class)->completeTransfer($record, auth()->id());

                    Notification::make()
                        ->title('Transferencia completada')
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
            ->modalHeading('Cancelar transferencia')
            ->modalDescription('La transferencia quedará cancelada y no moverá stock. ¿Desea continuar?')
            ->modalSubmitActionLabel('Cancelar transferencia')
            ->hidden(fn (StockTransfer $record): bool => $record->status !== 'draft')
            ->action(function (StockTransfer $record) {
                $record->update(['status' => 'cancelled']);

                Notification::make()
                    ->title('Transferencia cancelada')
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
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
            'view' => Pages\ViewStockTransfer::route('/{record}'),
        ];
    }
}