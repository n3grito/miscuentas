<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionResource\Pages;
use App\Models\Bom;
use App\Models\Production;
use App\Services\InventoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Orden de producción';

    protected static ?string $pluralModelLabel = 'Órdenes de producción';

    protected static ?string $slug = 'productions';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Producción')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('bom_id')
                            ->label('Receta (BOM)')
                            ->relationship('bom', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('product_id', $state ? Bom::find($state)?->product_id : null);
                            }),
                        Forms\Components\Select::make('product_id')
                            ->label('Producto a producir')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad a producir')
                            ->numeric()
                            ->minValue(0.0001)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Producción')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Referencia')
                            ->badge(),
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Producto'),
                        Infolists\Components\TextEntry::make('bom.name')
                            ->label('Receta')
                            ->placeholder('Sin receta'),
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Almacén'),
                        Infolists\Components\TextEntry::make('quantity')
                            ->label('Cantidad')
                            ->numeric(),
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
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Completada el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Materias primas consumidas')
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
                            ->columns(4)
                            ->hidden(fn (Production $record): bool => $record->status !== 'completed'),
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
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(4),
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
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->relationship('warehouse', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn (Production $record): bool => $record->status !== 'draft'),
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
            ->modalHeading('Completar producción')
            ->modalDescription('Se consumirán las materias primas de la receta y se añadirá el producto terminado al almacén. ¿Desea continuar?')
            ->modalSubmitActionLabel('Completar')
            ->hidden(fn (Production $record): bool => $record->status !== 'draft')
            ->action(function (Production $record) {
                try {
                    app(InventoryService::class)->completeProduction($record, auth()->id());

                    Notification::make()
                        ->title('Producción completada')
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
            ->modalHeading('Cancelar producción')
            ->modalDescription('La orden de producción quedará cancelada. ¿Desea continuar?')
            ->modalSubmitActionLabel('Cancelar producción')
            ->hidden(fn (Production $record): bool => $record->status !== 'draft')
            ->action(function (Production $record) {
                $record->update(['status' => 'cancelled']);

                Notification::make()
                    ->title('Producción cancelada')
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
            'index' => Pages\ListProductions::route('/'),
            'create' => Pages\CreateProduction::route('/create'),
            'edit' => Pages\EditProduction::route('/{record}/edit'),
            'view' => Pages\ViewProduction::route('/{record}'),
        ];
    }
}