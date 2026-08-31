<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PosService;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

class PosTest extends TestCase
{
    private function scenario(): array
    {
        $product = Product::create([
            'name' => 'Producto POS '.rand(10000, 99999),
            'sku' => 'SKU-POS-'.rand(10000, 99999),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::where('is_active', true)->firstOrFail();

        app(InventoryService::class)->increase($product, $warehouse->id, 10, 20, 'initial');

        return [$product, $warehouse];
    }

    public function test_checkout_completes_sale_and_returns_change(): void
    {
        [$product, $warehouse] = $this->scenario();

        $sale = app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50],
            ],
            thirdPartyId: null,
            discount: 10,
            tax: 0,
            cashReceived: 500,
            userId: null,
        );

        $sale->refresh();

        $this->assertSame('completed', $sale->status);
        $this->assertTrue($sale->is_pos);
        $this->assertEqualsWithDelta(140.0, (float) $sale->total, 0.000001);
        $this->assertEqualsWithDelta(360.0, (float) $sale->change_given, 0.000001);
        $this->assertEqualsWithDelta(500.0, (float) $sale->cash_received, 0.000001);

        $stock = (float) $product->inventory()->where('warehouse_id', $warehouse->id)->value('quantity');
        $this->assertEqualsWithDelta(7.0, $stock, 0.000001);
    }

    public function test_checkout_rejects_unavailable_product(): void
    {
        [$product, $warehouse] = $this->scenario();

        try {
            app(PosService::class)->checkout(
                warehouseId: $warehouse->id,
                lines: [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50],
                    ['product_id' => $product->id + 1000000, 'quantity' => 1, 'unit_price' => 10],
                ],
                thirdPartyId: null,
                discount: 0,
                tax: 0,
                cashReceived: null,
                userId: null,
            );

            $this->fail('Se esperaba RuntimeException por producto inexistente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no está disponible', $e->getMessage());
        }
    }

    public function test_checkout_rejects_insufficient_cash(): void
    {
        [$product, $warehouse] = $this->scenario();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('menor que el total');

        app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
            ],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: 50,
            userId: null,
        );
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        [$product, $warehouse] = $this->scenario();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('carrito está vacío');

        app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: null,
            userId: null,
        );
    }

    public function test_pos_page_access_control(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin/pos')->assertOk();
    }

    public function test_pos_page_forbidden_without_permission(): void
    {
        $user = User::create([
            'name' => 'Sin POS '.rand(1000, 9999),
            'email' => 'sinpos'.rand(1000, 9999).'@miscuentas.test',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user)->get('/admin/pos');
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }
}
