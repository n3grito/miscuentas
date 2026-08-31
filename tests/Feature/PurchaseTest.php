<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use RuntimeException;

class PurchaseTest extends TestCase
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

    private function makePurchase(Product $product, Warehouse $warehouse, float $quantity, float $unitCost, float $tax = 0): Purchase
    {
        $purchase = Purchase::create([
            'reference' => 'COM-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'tax' => $tax,
            'created_by' => \App\Models\User::where('email', 'admin@miscuentas.test')->value('id') ?? 1,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);

        return $purchase;
    }

    public function test_receive_purchase_increases_stock_with_weighted_average(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $warehouse->id, 10, 100, 'initial');

        $purchase = Purchase::create([
            'reference' => 'COM-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'tax' => 0,
            'created_by' => \App\Models\User::where('email', 'admin@miscuentas.test')->value('id') ?? 1,
        ]);

        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 120]);

        $service->receivePurchase($purchase, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));

        $purchase->refresh();
        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEquals('received', $purchase->status);
        $this->assertNotNull($purchase->received_at);
        $this->assertEquals(20.0, (float) $inv->quantity);
        $this->assertEqualsWithDelta(110.0, (float) $inv->average_cost, 0.000001);
        $this->assertEqualsWithDelta(1200.0, (float) $purchase->subtotal, 0.000001);
        $this->assertEqualsWithDelta(1200.0, (float) $purchase->total, 0.000001);

        $movements = $product->movements()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->get();

        $this->assertSame(1, $movements->count());
        $this->assertSame(1, $movements->where('movement_type', 'purchase')->count());
    }

    public function test_receive_purchase_adds_tax_to_total(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $purchase = $this->makePurchase($product, $warehouse, 10, 50, tax: 25);

        app(InventoryService::class)->receivePurchase($purchase, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));

        $purchase->refresh();

        $this->assertEqualsWithDelta(500.0, (float) $purchase->subtotal, 0.000001);
        $this->assertEqualsWithDelta(525.0, (float) $purchase->total, 0.000001);
        $this->assertEquals(10.0, (float) $product->inventory()->where('warehouse_id', $warehouse->id)->first()->quantity);
    }

    public function test_receive_purchase_requires_draft_status(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $purchase = $this->makePurchase($product, $warehouse, 5, 10);
        $purchase->update(['status' => 'received']);

        $this->expectException(RuntimeException::class);

        app(InventoryService::class)->receivePurchase($purchase, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));
    }

    public function test_receive_purchase_requires_items(): void
    {
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $purchase = Purchase::create([
            'reference' => 'COM-TEST-'.Str::random(6),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        app(InventoryService::class)->receivePurchase($purchase, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));
    }

    public function test_cancelled_purchase_does_not_move_stock(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $purchase = $this->makePurchase($product, $warehouse, 10, 50);
        $purchase->update(['status' => 'cancelled']);

        $this->assertEquals(0.0, (float) ($product->inventory()->where('warehouse_id', $warehouse->id)->first()?->quantity ?? 0));
        $this->assertSame(0, $product->movements()->count());
    }

    public function test_purchase_with_supplier_is_recorded(): void
    {
        $supplier = ThirdParty::create([
            'type' => 'supplier',
            'identity_type' => 'NIT',
            'identity_number' => 'NIT'.Str::random(6),
            'business_name' => 'Proveedor Test '.Str::random(4),
            'full_name' => null,
            'is_active' => true,
        ]);

        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $purchase = Purchase::create([
            'reference' => 'COM-TEST-'.Str::random(6),
            'third_party_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->assertEquals($supplier->displayName(), $purchase->thirdParty->displayName());
        $this->assertNull($this->makePurchase($product, $warehouse, 1, 1)->thirdParty);
    }
}