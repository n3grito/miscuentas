<?php

namespace App\Filament\Widgets;

use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class RecentActivity extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'Actividad reciente';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with(['causer', 'subject'])
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('description')
                    ->label('Acción')
                    ->wrap(),
                TextColumn::make('log_name')
                    ->label('Módulo')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s'),
            ])
            ->actions([
                ViewAction::make()
                    ->infolist(\App\Filament\Resources\ActivityLogResource::infolist(...))
                    ->label('Ver'),
            ])
            ->paginated(false);
    }
}