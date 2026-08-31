<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $modelLabel = 'Cuenta contable';

    protected static ?string $pluralModelLabel = 'Cuentas contables';

    protected static ?string $slug = 'accounts';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la cuenta')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(Account::types())
                            ->required(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Cuenta padre')
                            ->relationship(
                                'parent',
                                'name',
                                fn ($query) => $query->orderBy('code'),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} — {$record->name}")
                            ->searchable()
                            ->preload()
                            ->rule(function ($get, $record) {
                                return function (string $attribute, $value, $fail) use ($get, $record) {
                                    if (empty($value)) {
                                        return;
                                    }

                                    if ($record && (int) $value === $record->id) {
                                        $fail('Una cuenta no puede ser su propia cuenta padre.');
                                    }
                                };
                            }),
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
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state, Account $record): string => str_repeat('    ', (int) ($record->parent_id !== null)).$state),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Account::TYPE_ASSET => 'success',
                        Account::TYPE_LIABILITY => 'warning',
                        Account::TYPE_EQUITY => 'info',
                        Account::TYPE_INCOME => 'primary',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => Account::types()[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('code', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Account::types()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
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
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}