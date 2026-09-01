<?php

namespace Tests\Feature;

use App\Filament\Pages\StockAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

class StockAdjustmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SuperAdminSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@miscuentas.test')->firstOrFail();
    }

    private function makeInventoryProduct(): Product
    {
        $suffix = Str::random(6);

        return Product::create([
            'name' => 'Producto Ajuste '.$suffix,
            'sku' => 'ADJ-'.$suffix,
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);
    }

    private function makeWarehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => 'WH-ADJ'],
            ['name' => 'Almacén Ajustes', 'is_active' => true]
        );
    }

    public function test_stock_adjustment_page_is_accessible_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/inventario/ajuste-stock')
            ->assertOk();
    }

    public function test_increase_adjustment_adds_stock_and_records_movement(): void
    {
        $product = $this->makeInventoryProduct();
        $warehouse = $this->makeWarehouse();

        Livewire::actingAs($this->admin())
            ->test(StockAdjustment::class)
            ->fillForm([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'in',
                'quantity' => 50,
                'unit_cost' => 25,
                'reason' => 'Conteo físico',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertNotNull($inv);
        $this->assertEquals(50.0, (float) $inv->quantity);
        $this->assertEquals(25.0, (float) $inv->average_cost);

        $movement = $product->movements()->where('movement_type', 'adjustment')->first();
        $this->assertNotNull($movement);
        $this->assertSame('in', $movement->type);
        $this->assertSame('Conteo físico', $movement->reason);
    }

    public function test_decrease_adjustment_subtracts_stock(): void
    {
        $product = $this->makeInventoryProduct();
        $warehouse = $this->makeWarehouse();

        app(\App\Services\InventoryService::class)
            ->increase($product, $warehouse->id, 100, 10, 'initial');

        Livewire::actingAs($this->admin())
            ->test(StockAdjustment::class)
            ->fillForm([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'out',
                'quantity' => 30,
                'reason' => 'Merma constatada',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEquals(70.0, (float) $inv->quantity);
    }

    public function test_decrease_exceeding_stock_shows_error_and_does_not_modify(): void
    {
        $product = $this->makeInventoryProduct();
        $warehouse = $this->makeWarehouse();

        app(\App\Services\InventoryService::class)
            ->increase($product, $warehouse->id, 10, 10, 'initial');

        Livewire::actingAs($this->admin())
            ->test(StockAdjustment::class)
            ->fillForm([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'out',
                'quantity' => 500,
                'reason' => 'Salida mayor al stock',
            ])
            ->call('save');

        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEquals(10.0, (float) $inv->quantity);
        $this->assertSame(1, $product->movements()->count());
    }
}