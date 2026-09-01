<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PosService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;

class ServiceSaleTest extends TestCase
{
    private function makeService(): Product
    {
        $suffix = Str::random(8);

        return Product::create([
            'name' => 'Servicio '.$suffix,
            'sku' => 'SVC-'.$suffix,
            'type' => 'service',
            'track_inventory' => false,
            'is_active' => true,
        ]);
    }

    private function makeNonTrackedProduct(): Product
    {
        $suffix = Str::random(8);

        return Product::create([
            'name' => 'Producto sin inventario '.$suffix,
            'sku' => 'NT-'.$suffix,
            'type' => 'product',
            'track_inventory' => false,
            'is_active' => true,
        ]);
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::where('is_active', true)->firstOrFail();
    }

    public function test_tracks_inventory_flag(): void
    {
        $this->assertFalse($this->makeService()->tracksInventory());
        $this->assertFalse($this->makeNonTrackedProduct()->tracksInventory());

        $product = Product::create([
            'name' => 'Inventariable '.Str::random(6),
            'sku' => 'INV-'.Str::random(6),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($product->tracksInventory());
    }

    public function test_pos_can_sell_service_without_inventory(): void
    {
        $service = $this->makeService();
        $warehouse = $this->warehouse();

        $sale = app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [
                ['product_id' => $service->id, 'quantity' => 2, 'unit_price' => 75],
            ],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: 200,
            userId: null,
        );

        $sale->refresh();

        $this->assertSame('completed', $sale->status);
        $this->assertEqualsWithDelta(150.0, (float) $sale->total, 0.000001);
        $this->assertEqualsWithDelta(0.0, (float) $sale->cost_total, 0.000001);

        // El servicio no genera movimientos de inventario.
        $this->assertSame(0, $service->movements()->count());
    }

    public function test_pos_can_sell_product_without_inventory(): void
    {
        $item = $this->makeNonTrackedProduct();
        $warehouse = $this->warehouse();

        $sale = app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [
                ['product_id' => $item->id, 'quantity' => 1, 'unit_price' => 120],
            ],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: 120,
            userId: null,
        );

        $sale->refresh();

        $this->assertSame('completed', $sale->status);
        $this->assertEqualsWithDelta(120.0, (float) $sale->total, 0.000001);
        $this->assertEqualsWithDelta(0.0, (float) $sale->cost_total, 0.000001);
        $this->assertSame(0, $item->movements()->count());
    }

    public function test_mixed_sale_of_product_and_service(): void
    {
        $service = $this->makeService();
        $warehouse = $this->warehouse();

        $product = Product::create([
            'name' => 'Fisico '.Str::random(6),
            'sku' => 'FIS-'.Str::random(6),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        app(InventoryService::class)->increase($product, $warehouse->id, 10, 30, 'initial');

        $sale = app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 40],
                ['product_id' => $service->id, 'quantity' => 1, 'unit_price' => 60],
            ],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: 200,
            userId: null,
        );

        $sale->refresh();

        $this->assertSame('completed', $sale->status);
        $this->assertEqualsWithDelta(140.0, (float) $sale->total, 0.000001);
        $this->assertEqualsWithDelta(60.0, (float) $sale->cost_total, 0.000001);

        // El físico se descuenta; el servicio no.
        $stock = (float) $product->inventory()->where('warehouse_id', $warehouse->id)->value('quantity');
        $this->assertEqualsWithDelta(8.0, $stock, 0.000001);
        $this->assertSame(0, $service->movements()->count());
    }

    public function test_pos_search_includes_services(): void
    {
        $service = $this->makeService();
        $warehouse = $this->warehouse();

        $results = app(PosService::class)->searchProducts($warehouse->id, $service->sku);

        $found = $results->firstWhere('id', $service->id);

        $this->assertNotNull($found);
        $this->assertFalse($found['tracks_inventory']);
        $this->assertNull($found['stock']);
    }

    public function test_pos_search_excludes_inactive_articles(): void
    {
        $warehouse = $this->warehouse();
        $inactive = Product::create([
            'name' => 'Inactivo '.Str::random(6),
            'sku' => 'DES-'.Str::random(6),
            'type' => 'service',
            'track_inventory' => false,
            'is_active' => false,
        ]);

        $results = app(PosService::class)->searchProducts($warehouse->id, $inactive->sku);

        $this->assertNull($results->firstWhere('id', $inactive->id));
    }
}