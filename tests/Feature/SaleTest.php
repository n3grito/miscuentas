<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use RuntimeException;

class SaleTest extends TestCase
{
    private function makeProduct(array $overrides = []): Product
    {
        $suffix = Str::random(8);

        return Product::create(array_merge([
            'name' => 'Producto '.$suffix,
            'sku' => 'SKU-'.$suffix,
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function makeWarehouse(string $code): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => $code],
            ['name' => 'Almacén '.$code, 'is_active' => true]
        );
    }

    public function test_complete_sale_decreases_stock_at_average_cost(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $warehouse->id, 20, 50, 'initial');

        $sale = Sale::create([
            'reference' => 'VTA-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'tax' => 0,
            'created_by' => \App\Models\User::where('email', 'admin@miscuentas.test')->value('id') ?? 1,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 8,
            'unit_price' => 100,
        ]);

        $service->completeSale($sale, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));

        $sale->refresh();
        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEquals('completed', $sale->status);
        $this->assertNotNull($sale->completed_at);
        $this->assertEquals(12.0, (float) $inv->quantity);
        $this->assertEqualsWithDelta(50.0, (float) $inv->average_cost, 0.000001);

        $this->assertEqualsWithDelta(800.0, (float) $sale->subtotal, 0.000001);
        $this->assertEqualsWithDelta(800.0, (float) $sale->total, 0.000001);
        $this->assertEqualsWithDelta(400.0, (float) $sale->cost_total, 0.000001);

        $item = $sale->items()->first();
        $this->assertEqualsWithDelta(50.0, (float) $item->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(400.0, (float) $item->total_cost, 0.000001);
        $this->assertEqualsWithDelta(800.0, (float) $item->total_price, 0.000001);

        $movements = $product->movements()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->get();

        $this->assertSame(1, $movements->count());
        $this->assertSame('out', $movements->first()->type);
        $this->assertSame('sale', $movements->first()->movement_type);
    }

    public function test_complete_sale_rejects_insufficient_stock_and_rolls_back(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($productA, $warehouse->id, 10, 10, 'initial');
        $service->increase($productB, $warehouse->id, 5, 10, 'initial');

        $sale = Sale::create([
            'reference' => 'VTA-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $productA->id, 'quantity' => 3, 'unit_price' => 20]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $productB->id, 'quantity' => 50, 'unit_price' => 30]);

        try {
            $service->completeSale($sale, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));
            $this->fail('Se esperaba excepción por stock insuficiente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }

        $sale->refresh();
        $this->assertEquals('draft', $sale->status);
        $this->assertEquals(10.0, (float) $productA->inventory()->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertEquals(5.0, (float) $productB->inventory()->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertEqualsWithDelta(0.0, (float) $sale->total, 0.000001);
    }

    public function test_complete_sale_requires_draft_status(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        app(InventoryService::class)->increase($product, $warehouse->id, 5, 10, 'initial');

        $sale = Sale::create([
            'reference' => 'VTA-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'completed',
        ]);

        $this->expectException(RuntimeException::class);

        app(InventoryService::class)->completeSale($sale, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));
    }

    public function test_complete_sale_requires_items(): void
    {
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $sale = Sale::create([
            'reference' => 'VTA-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        app(InventoryService::class)->completeSale($sale, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));
    }

    public function test_cancelled_sale_does_not_move_stock(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        app(InventoryService::class)->increase($product, $warehouse->id, 7, 10, 'initial');

        $sale = Sale::create([
            'reference' => 'VTA-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 15]);

        $sale->update(['status' => 'cancelled']);

        $this->assertEquals(7.0, (float) $product->inventory()->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertSame(1, $product->movements()->count(), 'Solo debe existir el movimiento inicial.');
    }
}