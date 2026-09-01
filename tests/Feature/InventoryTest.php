<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Product;
use App\Models\Production;
use App\Models\StockAlert;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\NumberSequenceService;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use InvalidArgumentException;

class InventoryTest extends TestCase
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

    public function test_increase_applies_weighted_average_cost(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.$product->id.'-'.Str::random(3));
        $service = app(InventoryService::class);

        $service->increase($product, $warehouse->id, 10, 100, 'initial', reason: 'Apertura');
        $service->increase($product, $warehouse->id, 10, 120, 'purchase', reason: 'Compra');

        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEquals(20.0, (float) $inv->quantity);
        $this->assertEqualsWithDelta(110.0, (float) $inv->average_cost, 0.000001);
        $this->assertSame(2, $product->movements()->count());
    }

    public function test_decrease_uses_average_cost_and_rejects_negative_stock(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(6));
        $service = app(InventoryService::class);

        $service->increase($product, $warehouse->id, 10, 100, 'initial');

        $movement = $service->decrease($product, $warehouse->id, 4, 'sale', reason: 'Venta');

        $this->assertEquals(6.0, (float) $product->inventory()->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertEquals(100.0, (float) $movement->unit_cost);
        $this->assertEquals(400.0, (float) $movement->total_cost);
        $this->assertEquals(6.0, (float) $movement->balance_after);

        try {
            $service->decrease($product, $warehouse->id, 10, 'sale');
            $this->fail('Se esperaba una excepción por stock insuficiente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }

        $this->assertEquals(6.0, (float) $product->inventory()->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertSame(2, $product->movements()->count(), 'El movimiento fallido no debe quedar registrado.');
    }

    public function test_service_product_cannot_be_moved(): void
    {
        $serviceProduct = $this->makeProduct(['type' => 'service', 'track_inventory' => false]);
        $warehouse = $this->makeWarehouse('WH-'.Str::random(6));

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->increase($serviceProduct, $warehouse->id, 1, 10, 'initial');
    }

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        $product = $this->makeProduct();
        $from = $this->makeWarehouse('WH-'.Str::random(4));
        $to = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $from->id, 20, 50, 'initial');

        [$out, $in] = $service->transfer($product, $from->id, $to->id, 8);

        $this->assertEquals(12.0, (float) $product->inventory()->where('warehouse_id', $from->id)->first()->quantity);
        $this->assertEquals(8.0, (float) $product->inventory()->where('warehouse_id', $to->id)->first()->quantity);
        $this->assertEquals('transfer_out', $out->movement_type);
        $this->assertEquals('transfer_in', $in->movement_type);
        $this->assertEquals(50.0, (float) $in->unit_cost, 'El costo se conserva al transferir.');
    }

    public function test_transfer_to_same_warehouse_throws(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->transfer($product, $warehouse->id, $warehouse->id, 5);
    }

    public function test_complete_stock_transfer_updates_status_and_costs(): void
    {
        $product = $this->makeProduct();
        $from = $this->makeWarehouse('WH-'.Str::random(4));
        $to = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $from->id, 30, 10, 'initial');

        $transfer = StockTransfer::create([
            'reference' => 'TRF-TEST-'.Str::random(6),
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => 'draft',
            'created_by' => \App\Models\User::where('email', 'admin@miscuentas.test')->value('id') ?? 1,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $service->completeTransfer($transfer, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));

        $transfer->refresh();

        $this->assertEquals('completed', $transfer->status);
        $this->assertNotNull($transfer->completed_at);
        $this->assertEquals(20.0, (float) $product->inventory()->where('warehouse_id', $from->id)->first()->quantity);
        $this->assertEquals(10.0, (float) $product->inventory()->where('warehouse_id', $to->id)->first()->quantity);

        $item = $transfer->items()->first();
        $this->assertEquals(10.0, (float) $item->unit_cost);
        $this->assertEquals(100.0, (float) $item->total_cost);

        $movements = $product->movements()->where('reference_type', StockTransfer::class)->get();
        $this->assertSame(2, $movements->count(), 'La transferencia debe generar salida y entrada.');
    }

    public function test_complete_transfer_requires_draft_status(): void
    {
        $product = $this->makeProduct();
        $from = $this->makeWarehouse('WH-'.Str::random(4));
        $to = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $from->id, 10, 5, 'initial');

        $transfer = StockTransfer::create([
            'reference' => 'TRF-TEST-'.Str::random(6),
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => 'completed',
        ]);

        $this->expectException(RuntimeException::class);

        $service->completeTransfer($transfer, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));
    }

    public function test_complete_production_consumes_ingredients_and_adds_output(): void
    {
        $flour = $this->makeProduct();
        $sugar = $this->makeProduct();
        $bread = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($flour, $warehouse->id, 100, 20, 'initial');
        $service->increase($sugar, $warehouse->id, 100, 30, 'initial');

        $bom = Bom::create([
            'name' => 'Receta pan',
            'product_id' => $bread->id,
            'output_quantity' => 1,
            'is_active' => true,
        ]);

        $bom->items()->create(['product_id' => $flour->id, 'quantity' => 0.5]);
        $bom->items()->create(['product_id' => $sugar->id, 'quantity' => 0.25]);

        $production = Production::create([
            'reference' => 'PROD-TEST-'.Str::random(6),
            'bom_id' => $bom->id,
            'product_id' => $bread->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'status' => 'draft',
            'created_by' => \App\Models\User::where('email', 'admin@miscuentas.test')->value('id') ?? 1,
        ]);

        $service->completeProduction($production, \App\Models\User::where('email', 'admin@miscuentas.test')->value('id'));

        $production->refresh();

        $this->assertEquals('completed', $production->status);

        $flourStock = $flour->inventory()->where('warehouse_id', $warehouse->id)->first();
        $sugarStock = $sugar->inventory()->where('warehouse_id', $warehouse->id)->first();
        $breadStock = $bread->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEqualsWithDelta(95.0, (float) $flourStock->quantity, 0.001);
        $this->assertEqualsWithDelta(97.5, (float) $sugarStock->quantity, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $breadStock->quantity, 0.001);

        $this->assertSame(2, $production->items()->count());
        $this->assertEqualsWithDelta(17.5, (float) $breadStock->average_cost, 0.000001, 'El costo del producto terminado es la suma de materias primas / cantidad.');
    }

    public function test_stock_alert_scan_creates_and_resolves_alerts(): void
    {
        $product = $this->makeProduct(['min_stock' => 10]);
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(StockAlertService::class);

        $service->scan($warehouse->id);

        $this->assertSame(1, StockAlert::where('product_id', $product->id)->where('status', 'open')->where('level', 'below_min')->count());

        app(InventoryService::class)->increase($product, $warehouse->id, 50, 10, 'initial');

        $service->scan($warehouse->id);

        $this->assertSame(0, StockAlert::where('product_id', $product->id)->where('status', 'open')->count());
        $this->assertSame(1, StockAlert::where('product_id', $product->id)->where('status', 'resolved')->count());
    }

    public function test_stock_alert_over_max(): void
    {
        $product = $this->makeProduct(['max_stock' => 100]);
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(StockAlertService::class);

        app(InventoryService::class)->increase($product, $warehouse->id, 200, 10, 'initial');

        $service->scan($warehouse->id);

        $this->assertSame(1, StockAlert::where('product_id', $product->id)->where('status', 'open')->where('level', 'over_max')->count());
    }

    public function test_number_sequence_is_correlative(): void
    {
        $service = app(NumberSequenceService::class);
        $type = 'test_'.Str::random(6);

        $this->assertEquals('TRF-000001', $service->next($type, 'TRF-'));
        $this->assertEquals('TRF-000002', $service->next($type, 'TRF-'));
        $this->assertEquals('TRF-000003', $service->next($type, 'TRF-'));
    }

    public function test_failed_decrease_rolls_back_transaction_atomically(): void
    {
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $warehouse->id, 5, 10, 'initial');

        DB::transaction(function () use ($service, $product, $warehouse) {
            try {
                $service->decrease($product, $warehouse->id, 6, 'sale');
            } catch (RuntimeException) {
                // Se captura para que la transacción no se marque como rota.
            }
        });

        $inv = $product->inventory()->where('warehouse_id', $warehouse->id)->first();

        $this->assertEquals(5.0, (float) $inv->quantity);
        $this->assertSame(1, $product->movements()->count());
    }

    public function test_increase_updates_existing_inventory_row_without_duplicate(): void
    {
        // Escenario real del servidor: ya existe una fila de inventario
        // (p.ej. quantity=5000) y llega una nueva entrada para el mismo
        // producto/almacén. Debe actualizarla, no insertar otra (error 1062).
        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse('WH-'.Str::random(4));
        $service = app(InventoryService::class);

        $service->increase($product, $warehouse->id, 5000, 3.5, 'initial', reason: 'Apertura');
        $service->increase($product, $warehouse->id, 200, 4.0, 'purchase', reason: 'Compra');

        $rows = $product->inventory()->where('warehouse_id', $warehouse->id)->get();

        $this->assertSame(1, $rows->count());
        $this->assertEquals(5200.0, (float) $rows->first()->quantity);
        $this->assertSame(2, $product->movements()->count());
    }
}