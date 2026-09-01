<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('panel')
            ->login()
            ->brandName('MisCuentas')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->resources([
                \App\Filament\Resources\SaleResource::class,
                \App\Filament\Resources\PurchaseResource::class,
                \App\Filament\Resources\ProductResource::class,
                \App\Filament\Resources\ThirdPartyResource::class,
                \App\Filament\Resources\InvoiceResource::class,
                \App\Filament\Resources\WarehouseResource::class,
                \App\Filament\Resources\InventoryMovementResource::class,
                \App\Filament\Resources\StockTransferResource::class,
                \App\Filament\Resources\StockAlertResource::class,
                \App\Filament\Resources\BomResource::class,
                \App\Filament\Resources\ProductionResource::class,
            ])
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\PosTerminal::class,
                \App\Filament\Pages\StockAdjustment::class,
                \App\Filament\Pages\Reports\OperationsSummary::class,
                \App\Filament\Pages\Reports\MovementsReport::class,
                \App\Filament\Pages\Reports\InventoryValuation::class,
                \App\Filament\Pages\Reports\TrialBalance::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\SalesChart::class,
                \App\Filament\Widgets\PurchasesChart::class,
                \App\Filament\Widgets\TopProductsChart::class,
                \App\Filament\Widgets\StockAlertsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}