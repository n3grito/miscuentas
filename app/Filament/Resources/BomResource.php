<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BomResource\Pages;
use App\Models\Bom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BomResource extends Resource
{
    protected static ?string $model = Bom::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Receta (BOM)';

    protected static ?string $pluralModelLabel = 'Recetas (BOM)';

    protected static ?string $slug = 'boms';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Producción')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto terminado')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('output_quantity')
                            ->label('Cantidad de salida')
                            ->numeric()
                            ->default(1)
                            ->minValue(0.0001)
                            ->required()
                            ->helperText('Cantidad que se obtiene al consumir los ingredientes indicados.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la receta')
                            ->maxLength(255)
                            ->placeholder('Opcional'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ingredientes')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Ingrediente')
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
                            ->addActionLabel('Agregar ingrediente')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto terminado')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Receta')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('output_quantity')
                    ->label('Salida')
                    ->numeric(4),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Ingredientes')
                    ->counts('items'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoms::route('/'),
            'create' => Pages\CreateBom::route('/create'),
            'edit' => Pages\EditBom::route('/{record}/edit'),
        ];
    }
}