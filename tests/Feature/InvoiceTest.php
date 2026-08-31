<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PosService;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

class InvoiceTest extends TestCase
{
    private function completedSale(): Sale
    {
        $product = Product::create([
            'name' => 'Producto Facturable '.rand(10000, 99999),
            'sku' => 'SKU-FAC-'.rand(10000, 99999),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::where('is_active', true)->firstOrFail();

        app(InventoryService::class)->increase($product, $warehouse->id, 20, 15, 'initial');

        return app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 30]],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: null,
            userId: null,
        );
    }

    public function test_invoice_is_generated_from_completed_sale(): void
    {
        $sale = $this->completedSale();

        $invoice = app(\App\Services\InvoiceService::class)->createFromSale($sale, null);

        $this->assertMatchesRegularExpression('/^FAC-\d{6}$/', $invoice->number);
        $this->assertSame('issued', $invoice->status);
        $this->assertEqualsWithDelta((float) $sale->total, (float) $invoice->total, 0.000001);
        $this->assertSame($sale->id, $invoice->sale_id);

        // La venta queda vinculada.
        $this->assertSame($invoice->id, $sale->fresh()->invoice?->id);
    }

    public function test_sale_cannot_be_invoiced_twice(): void
    {
        $sale = $this->completedSale();
        $service = app(\App\Services\InvoiceService::class);

        $service->createFromSale($sale, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya tiene la factura');

        $service->createFromSale($sale, null);
    }

    public function test_draft_sale_cannot_be_invoiced(): void
    {
        [$product, $warehouse] = [null, Warehouse::where('is_active', true)->firstOrFail()];

        $product = Product::create([
            'name' => 'Borrador FAC '.rand(10000, 99999),
            'sku' => 'SKU-BRF-'.rand(10000, 99999),
            'type' => 'product',
            'track_inventory' => true,
        ]);

        $sale = Sale::create([
            'reference' => 'VTA-BRF-'.rand(10000, 99999),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ventas completadas');

        app(\App\Services\InvoiceService::class)->createFromSale($sale, null);
    }

    public function test_pos_can_generate_invoice_automatically(): void
    {
        $product = Product::create([
            'name' => 'POS con factura '.rand(10000, 99999),
            'sku' => 'SKU-PFI-'.rand(10000, 99999),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::where('is_active', true)->firstOrFail();
        app(InventoryService::class)->increase($product, $warehouse->id, 5, 8, 'initial');

        $sale = app(PosService::class)->checkout(
            warehouseId: $warehouse->id,
            lines: [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25]],
            thirdPartyId: null,
            discount: 0,
            tax: 0,
            cashReceived: 60,
            userId: null,
            createInvoice: true,
        );

        $this->assertNotNull($sale->fresh()->invoice);
        $this->assertEqualsWithDelta(50.0, (float) $sale->invoice->total, 0.000001);
        $this->assertEqualsWithDelta(10.0, (float) $sale->change_given, 0.000001);
    }

    public function test_print_page_requires_authentication(): void
    {
        $sale = $this->completedSale();
        $invoice = app(\App\Services\InvoiceService::class)->createFromSale($sale, null);

        $this->get("/admin/invoices/{$invoice->id}/print")->assertRedirect();

        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();
        $this->actingAs($admin)
            ->get("/admin/invoices/{$invoice->id}/print")
            ->assertOk()
            ->assertSee($invoice->number);
    }

    public function test_invoices_page_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin/invoices')->assertOk();
    }
}
