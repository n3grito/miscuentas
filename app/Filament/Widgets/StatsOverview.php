<?php

namespace App\Filament\Widgets;

use App\Models\Currency;
use App\Models\ThirdParty;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Usuarios activos', User::where('is_active', true)->count())
                ->description('Total de cuentas habilitadas')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
            Stat::make('Clientes', ThirdParty::whereIn('type', ['customer', 'both'])->count())
                ->description('Terceros registrados')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),
            Stat::make('Monedas', Currency::where('is_active', true)->count())
                ->description('Monedas habilitadas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
            Stat::make('Actividad registrada', Activity::count())
                ->description('Eventos de auditoría')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}