<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'Artículo';

    protected static ?string $pluralModelLabel = 'Catálogo de artículos';

    protected static ?string $slug = 'products';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información general')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->afterStateUpdated(fn ($state) => $state === null || $state === '' ? null : mb_strtoupper($state))
                            ->helperText('Déjelo vacío y se generará automáticamente.')
                            ->placeholder('SKU-000001'),
                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de barras')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('unit_id')
                            ->label('Unidad de medida')
                            ->relationship('unit', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'product' => 'Producto',
                                'service' => 'Servicio',
                            ])
                            ->default('product')
                            ->live()
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Inventario')
                    ->schema([
                        Forms\Components\Toggle::make('track_inventory')
                            ->label('Lleva control de inventario')
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('type') === 'product'),
                        Forms\Components\Select::make('cost_method')
                            ->label('Método de costeo')
                            ->options([
                                'weighted_average' => 'Promedio ponderado',
                            ])
                            ->default('weighted_average')
                            ->visible(fn (Get $get): bool => $get('type') === 'product'),
                        Forms\Components\TextInput::make('min_stock')
                            ->label('Stock mínimo')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->placeholder('Umbral para alertas'),
                        Forms\Components\TextInput::make('max_stock')
                            ->label('Stock máximo')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->placeholder('Umbral para alertas'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('type') === 'product'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.abbreviation')
                    ->label('UM'),
                Tables\Columns\TextColumn::make('inventory_sum_quantity')
                    ->label('Existencia total')
                    ->numeric(4)
                    ->sortable()
                    ->color(fn (Product $record): string => (float) $record->inventory_sum_quantity < (float) $record->min_stock ? 'danger' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'product' => 'Producto',
                        'service' => 'Servicio',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withSum('inventory', 'quantity');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InventoryRelationManager::class,
            RelationManagers\MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}