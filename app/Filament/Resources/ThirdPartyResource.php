<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ThirdPartyResource\Pages;
use App\Models\ThirdParty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ThirdPartyResource extends Resource
{
    protected static ?string $model = ThirdParty::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Terceros';

    protected static ?string $modelLabel = 'Tercero';

    protected static ?string $pluralModelLabel = 'Terceros';

    protected static ?string $slug = 'third-parties';

    protected static ?int $navigationSort = 3;

    protected static function identityOptions(): array
    {
        return [
            'CI' => 'Carné de Identidad (CI)',
            'NIT' => 'Número de Identificación Tributaria (NIT)',
            'PASSPORT' => 'Pasaporte',
            'OTHER' => 'Otro',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tipo y documento')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'customer' => 'Cliente',
                                'supplier' => 'Proveedor',
                                'both' => 'Cliente y Proveedor',
                            ])
                            ->default('customer')
                            ->required(),
                        Forms\Components\Select::make('identity_type')
                            ->label('Tipo de documento')
                            ->options(fn (): array => static::identityOptions())
                            ->default('CI')
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('identity_number')
                            ->label('Número de documento')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(30),
                        Forms\Components\TextInput::make('business_name')
                            ->label('Razón social')
                            ->requiredWithout('full_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('full_name')
                            ->label('Nombre completo')
                            ->requiredWithout('business_name')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('mobile')
                            ->label('Móvil')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('state')
                            ->label('Provincia')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('zip')
                            ->label('Código postal')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('country')
                            ->label('País')
                            ->default('CU')
                            ->maxLength(3),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Toggle::make('is_taxpayer')
                            ->label('Contribuyente'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'customer' => 'Cliente',
                        'supplier' => 'Proveedor',
                        'both' => 'Ambos',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'supplier' => 'warning',
                        'both' => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('identity_number')
                    ->label('Documento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nombre / Razón social')
                    ->getStateUsing(fn (ThirdParty $record): string => $record->displayName())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'customer' => 'Cliente',
                        'supplier' => 'Proveedor',
                        'both' => 'Ambos',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListThirdParties::route('/'),
            'create' => Pages\CreateThirdParty::route('/create'),
            'edit' => Pages\EditThirdParty::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}