<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'Registro de auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    protected static ?string $slug = 'activity-logs';

    protected static ?int $navigationSort = 6;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('log_name')
                    ->label('Módulo'),
                TextEntry::make('description')
                    ->label('Acción'),
                TextEntry::make('event')
                    ->label('Evento'),
                TextEntry::make('subject_type')
                    ->label('Registro')
                    ->getStateUsing(fn (Activity $record): string => static::subjectLabel($record)),
                TextEntry::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                TextEntry::make('ip')
                    ->label('IP')
                    ->getStateUsing(fn (Activity $record): ?string => $record->properties?->get('ip')),
                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s'),
                KeyValueEntry::make('properties')
                    ->label('Detalles')
                    ->keyLabel('Atributo')
                    ->valueLabel('Valor'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Módulo')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state)),
                Tables\Columns\TextColumn::make('description')
                    ->label('Acción')
                    ->wrap(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'deleted' => 'danger',
                        'created' => 'success',
                        'updated' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Registro')
                    ->getStateUsing(fn (Activity $record): string => static::subjectLabel($record)),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->getStateUsing(fn (Activity $record): ?string => $record->properties?->get('ip')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Módulo')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        'restored' => 'Restaurado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    protected static function subjectLabel(Activity $record): string
    {
        $subject = $record->subject;

        if ($subject === null) {
            return class_basename($record->subject_type).' #'.$record->subject_id;
        }

        $label = match (true) {
            $subject instanceof \App\Models\User => $subject->email,
            $subject instanceof \App\Models\ThirdParty => $subject->displayName(),
            $subject instanceof \App\Models\Currency => $subject->code,
            default => class_basename($subject).' #'.$subject->getKey(),
        };

        return $label;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}