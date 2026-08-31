<?php

namespace Tests\Feature;

use App\Filament\Widgets\PurchasesChart;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProductsChart;
use App\Models\User;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@miscuentas.test')->first();
    }

    public function test_admin_can_access_dashboard_with_widgets(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_stats_overview_widget_class_exists(): void
    {
        $this->assertTrue(class_exists(StatsOverview::class));
    }

    public function test_sales_chart_widget_class_exists(): void
    {
        $this->assertTrue(class_exists(SalesChart::class));
    }

    public function test_purchases_chart_widget_class_exists(): void
    {
        $this->assertTrue(class_exists(PurchasesChart::class));
    }

    public function test_top_products_chart_widget_class_exists(): void
    {
        $this->assertTrue(class_exists(TopProductsChart::class));
    }

    public function test_sales_chart_type_is_line(): void
    {
        $widget = new SalesChart();
        $reflection = new \ReflectionMethod($widget, 'getType');
        $reflection->setAccessible(true);

        $this->assertEquals('line', $reflection->invoke($widget));
    }

    public function test_purchases_chart_type_is_line(): void
    {
        $widget = new PurchasesChart();
        $reflection = new \ReflectionMethod($widget, 'getType');
        $reflection->setAccessible(true);

        $this->assertEquals('line', $reflection->invoke($widget));
    }

    public function test_top_products_chart_type_is_bar(): void
    {
        $widget = new TopProductsChart();
        $reflection = new \ReflectionMethod($widget, 'getType');
        $reflection->setAccessible(true);

        $this->assertEquals('bar', $reflection->invoke($widget));
    }
}