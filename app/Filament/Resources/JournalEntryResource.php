<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $modelLabel = 'Asiento contable';

    protected static ?string $pluralModelLabel = 'Asientos contables';

    protected static ?string $slug = 'journal-entries';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del asiento')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DatePicker::make('date')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Movimientos')
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('account_id')
                                    ->label('Cuenta')
                                    ->relationship(
                                        'account',
                                        'name',
                                        fn (Builder $query) => $query->where('is_active', true)->orderBy('code'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} — {$record->name}")
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Forms\Components\TextInput::make('debit')
                                    ->label('Debe')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->rule(function (Get $get) {
                                        return function (string $attribute, $value, $fail) use ($get) {
                                            if ((float) $value > 0 && (float) ($get('credit') ?? 0) > 0) {
                                                $fail('La línea no puede llevar debe y haber a la vez.');
                                            }
                                        };
                                    }),
                                Forms\Components\TextInput::make('credit')
                                    ->label('Haber')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->live(onBlur: true),
                                Forms\Components\TextInput::make('memo')
                                    ->label('Concepto')
                                    ->maxLength(255),
                            ])
                            ->columns(4)
                            ->defaultItems(2)
                            ->minItems(2)
                            ->addActionLabel('Agregar línea')
                            ->reorderable(false)
                            ->live(),
                        Forms\Components\Placeholder::make('totals')
                            ->label('Totales')
                            ->content(function (Get $get): string {
                                $debit = collect($get('lines') ?? [])->sum(fn ($line) => (float) ($line['debit'] ?? 0));
                                $credit = collect($get('lines') ?? [])->sum(fn ($line) => (float) ($line['credit'] ?? 0));
                                $difference = round($debit - $credit, 6);

                                return 'Debe: '.number_format($debit, 2).' | Haber: '.number_format($credit, 2)
                                    .($difference == 0
                                        ? ' | Asiento cuadrado ✓'
                                        : ' | Descuadre: '.number_format(abs($difference), 2).' '.($difference > 0 ? '(falta haber)' : '(falta debe)'));
                            })
                            ->helperText('El asiento solo puede contabilizarse si Debe y Haber coinciden.'),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Asiento contable')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Referencia')
                            ->badge(),
                        Infolists\Components\TextEntry::make('date')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'posted' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'Contabilizado' : 'Borrador'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Descripción'),
                        Infolists\Components\TextEntry::make('total_debit')
                            ->label('Debe total')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('total_credit')
                            ->label('Haber total')
                            ->numeric(2),
                        Infolists\Components\TextEntry::make('posted_at')
                            ->label('Contabilizado el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Creado por')
                            ->placeholder('—'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Movimientos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lines')
                            ->schema([
                                Infolists\Components\TextEntry::make('account.code')
                                    ->label('Código'),
                                Infolists\Components\TextEntry::make('account.name')
                                    ->label('Cuenta'),
                                Infolists\Components\TextEntry::make('debit')
                                    ->label('Debe')
                                    ->numeric(2),
                                Infolists\Components\TextEntry::make('credit')
                                    ->label('Haber')
                                    ->numeric(2),
                                Infolists\Components\TextEntry::make('memo')
                                    ->label('Concepto')
                                    ->placeholder('—'),
                            ])
                            ->columns(5),
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
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('total_debit')
                    ->label('Monto')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'posted' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'Contabilizado' : 'Borrador')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'posted' => 'Contabilizado',
                    ]),
                Tables\Filters\SelectFilter::make('date')
                    ->label('Periodo')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $v) => $q->where('date', '>=', $v))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $v) => $q->where('date', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn (JournalEntry $record): bool => $record->status !== 'draft'),
                static::postAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function postAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('post')
            ->label('Contabilizar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Contabilizar asiento')
            ->modalDescription('Se validará que Debe y Haber coincidan y el asiento quedará inmutable. ¿Desea continuar?')
            ->modalSubmitActionLabel('Contabilizar')
            ->hidden(fn (JournalEntry $record): bool => $record->status !== 'draft')
            ->action(function (JournalEntry $record) {
                try {
                    app(JournalService::class)->post($record, auth()->id());

                    Notification::make()
                        ->title('Asiento contabilizado')
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
        ];
    }
}