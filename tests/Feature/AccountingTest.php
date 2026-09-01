<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\JournalService;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    public function test_post_validates_double_entry(): void
    {
        $entry = $this->createDraftEntry(100.0, 100.0);

        app(JournalService::class)->post($entry);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'posted',
        ]);
    }

    public function test_post_rejects_unbalanced_entry(): void
    {
        $entry = $this->createDraftEntry(100.0, 50.0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está cuadrado');

        app(JournalService::class)->post($entry);
    }

    public function test_purchase_completion_creates_auto_entry_when_enabled(): void
    {
        [$product, $warehouse] = $this->prepareAccountingScenario();

        $supplier = \App\Models\ThirdParty::create([
            'type' => 'supplier',
            'identity_type' => 'NIT',
            'identity_number' => 'NIT-ACC-'.Str::random(6),
            'business_name' => 'Proveedor Contable '.Str::random(4),
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'reference' => 'COM-TEST-'.Str::random(5),
            'third_party_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'tax' => 0,
            'status' => 'draft',
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 20,
            'total_cost' => 200,
        ]);

        app(InventoryService::class)->receivePurchase($purchase->fresh());

        $entry = JournalEntry::where('status', 'posted')
            ->where('description', 'like', "%{$purchase->reference}%")
            ->firstOrFail();

        $this->assertEqualsWithDelta(200.0, (float) $entry->lines()->where('debit', '>', 0)->sum('debit'), 0.000001);
        $this->assertEqualsWithDelta(200.0, (float) $entry->lines()->where('credit', '>', 0)->sum('credit'), 0.000001);

        $inventoryId = Account::where('code', JournalService::CODE_INVENTORY)->value('id');
        $suppliersId = Account::where('code', JournalService::CODE_SUPPLIERS)->value('id');

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventoryId,
            'debit' => 200,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $suppliersId,
            'debit' => 0,
            'credit' => 200,
        ]);
    }

    public function test_sale_completion_creates_revenue_and_cost_entries(): void
    {
        [$product, $warehouse] = $this->prepareAccountingScenario();

        app(InventoryService::class)->increase($product, $warehouse->id, 10, 30, 'initial');

        $sale = Sale::create([
            'reference' => 'VTA-TEST-'.Str::random(5),
            'warehouse_id' => $warehouse->id,
            'tax' => 0,
            'status' => 'draft',
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 50,
            'total_price' => 200,
            'unit_cost' => 0,
            'total_cost' => 0,
        ]);

        app(InventoryService::class)->completeSale($sale->fresh(), null, true);

        $revenueEntry = JournalEntry::where('status', 'posted')
            ->where('description', 'like', "%Venta {$sale->reference}%")
            ->firstOrFail();
        $costEntry = JournalEntry::where('status', 'posted')
            ->where('description', 'like', "%Costo de venta {$sale->reference}%")
            ->firstOrFail();

        $this->assertEqualsWithDelta(200.0, (float) $revenueEntry->lines()->sum('credit'), 0.000001);
        $this->assertEqualsWithDelta(200.0, (float) $revenueEntry->lines()->sum('debit'), 0.000001);
        $this->assertEqualsWithDelta(120.0, (float) $costEntry->lines()->where('debit', '>', 0)->sum('debit'), 0.000001);
        $this->assertEqualsWithDelta(120.0, (float) $costEntry->lines()->where('credit', '>', 0)->sum('credit'), 0.000001);
    }

    public function test_trial_balance_groups_by_explicit_account_columns(): void
    {
        $page = new \App\Filament\Pages\Reports\TrialBalance;

        $getQuery = new \ReflectionMethod($page, 'getTableQuery');
        $getQuery->setAccessible(true);

        $query = $getQuery->invoke($page);
        $sql = strtolower($query->toSql());

        // En MySQL 8 "GROUP BY accounts.id" bastaría por dependencia funcional,
        // pero MariaDB con ONLY_FULL_GROUP_BY rechaza accounts.*: hay que agrupar
        // por cada columna seleccionada explícitamente.
        $this->assertStringNotContainsString('accounts.*', $sql);
        $this->assertStringContainsString('group by', $sql);

        $this->assertMatchesRegularExpression(
            '/group by\s+`?accounts`?\.`?id`?[,\s]/i',
            $sql
        );

        // La consulta debe ejecutarse sin errores y devolver los totales.
        $rows = $query->get();
        $this->assertGreaterThan(0, $rows->count());
        $this->assertTrue($rows->first()->offsetExists('total_debits'));
        $this->assertTrue($rows->first()->offsetExists('total_credits'));
    }

    private function createDraftEntry(float $debit, float $credit): JournalEntry
    {
        $cash = Account::where('code', JournalService::CODE_CASH)->firstOrFail();
        $capital = Account::where('code', '310000')->firstOrFail();

        $entry = JournalEntry::create([
            'reference' => app(JournalService::class)->nextReference(),
            'date' => now(),
            'description' => 'Test '.Str::random(8),
            'status' => 'draft',
        ]);

        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => $debit, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => $credit],
        ]);

        return $entry->fresh();
    }

    /**
     * Activa la contabilidad automática y devuelve [producto, almacén] de prueba.
     */
    private function prepareAccountingScenario(): array
    {
        Setting::updateOrCreate(
            ['group' => 'accounting', 'key' => 'auto_entries'],
            ['value' => '1', 'type' => 'boolean', 'label' => 'Asientos automáticos']
        );

        $product = Product::create([
            'name' => 'Producto Contable '.Str::random(6),
            'sku' => 'SKU-ACC-'.Str::random(6),
            'type' => 'product',
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-ACC'],
            ['name' => 'Almacén Contable', 'is_active' => true]
        );

        return [$product, $warehouse];
    }
}