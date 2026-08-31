<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'Moneda';

    protected static ?string $pluralModelLabel = 'Monedas';

    protected static ?string $slug = 'currencies';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la moneda')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(3)
                            ->afterStateUpdated(fn ($state) => $state === null ? null : mb_strtoupper($state))
                            ->unique(ignoreRecord: true)
                            ->placeholder('CUP'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('symbol')
                            ->label('Símbolo')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\Select::make('decimal_places')
                            ->label('Decimales')
                            ->options([
                                0 => '0',
                                1 => '1',
                                2 => '2',
                                3 => '3',
                                4 => '4',
                            ])
                            ->default(2)
                            ->required(),
                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('Tasa de cambio')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->step(0.000001)
                            ->minValue(0),
                        Forms\Components\Toggle::make('is_base')
                            ->label('Moneda base')
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Símbolo'),
                Tables\Columns\IconColumn::make('is_base')
                    ->label('Base')
                    ->boolean(),
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Tasa')
                    ->numeric(6)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_base')
                    ->label('Moneda base'),
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
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}