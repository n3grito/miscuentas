<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PosService
{
    public function __construct(
        protected InventoryService $inventory,
        protected NumberSequenceService $sequences,
    ) {}

    /**
     * Procesa el cobro de una venta de mostrador:
     * crea la venta, valida efectivo y stock, descarga inventario y
     * registra el cambio a entregar. Todo atómico.
     *
     * @param array<int, array{product_id: int, quantity: float, unit_price: float}> $lines
     */
    public function checkout(
        int $warehouseId,
        array $lines,
        ?int $thirdPartyId,
        float $discount,
        float $tax,
        ?float $cashReceived,
        ?int $userId,
        bool $createInvoice = false,
    ): Sale {
        if ($warehouseId < 1 || ! Warehouse::where('id', $warehouseId)->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Seleccione un almacén activo.');
        }

        if (count($lines) === 0) {
            throw new RuntimeException('El carrito está vacío.');
        }

        $discount = round(max(0.0, (float) $discount), 6);
        $tax = round(max(0.0, (float) $tax), 6);

        return DB::transaction(function () use ($warehouseId, $lines, $thirdPartyId, $discount, $tax, $cashReceived, $userId, $createInvoice) {
            $sale = Sale::create([
                'reference' => $this->sequences->next('sale', 'VTA-'),
                'third_party_id' => $thirdPartyId,
                'warehouse_id' => $warehouseId,
                'status' => 'draft',
                'tax' => 0,
                'discount' => 0,
                'total' => 0,
                'cost_total' => 0,
                'is_pos' => true,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $product = Product::find($line['product_id'] ?? 0);

                if (! $product || ! $product->is_active) {
                    throw new RuntimeException('Uno de los productos del carrito ya no está disponible.');
                }

                $quantity = (float) ($line['quantity'] ?? 0);
                $unitPrice = round((float) ($line['unit_price'] ?? 0), 6);

                if ($quantity <= 0) {
                    throw new RuntimeException("Cantidad inválida para {$product->name}.");
                }

                if ($unitPrice < 0) {
                    throw new RuntimeException("Precio inválido para {$product->name}.");
                }

                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => round($quantity * $unitPrice, 6),
                    'unit_cost' => 0,
                    'total_cost' => 0,
                ]);
            }

            // Recalcula SIEMPRE en el servidor; nunca confía en totales del cliente.
            $subtotal = round((float) $sale->items()->sum('total_price'), 6);
            $payable = round(max($subtotal - $discount, 0.0) + $tax, 6);

            if ($payable <= 0) {
                throw new RuntimeException('El total a cobrar debe ser mayor que cero.');
            }

            $cashReceived = $cashReceived === null ? null : round((float) $cashReceived, 6);

            if ($cashReceived !== null && $cashReceived + 0.000001 < $payable) {
                throw new RuntimeException(sprintf(
                    'El efectivo recibido (%.2f) es menor que el total a cobrar (%.2f).',
                    $cashReceived,
                    $payable
                ));
            }

            $sale->update([
                'tax' => $tax,
                'discount' => $discount,
                'cash_received' => $cashReceived,
            ]);

            $this->inventory->completeSale($sale->fresh(), $userId);

            $sale->refresh();

            $change = $cashReceived === null
                ? 0.0
                : round(max($cashReceived - (float) $sale->total, 0.0), 6);

            $sale->update(['change_given' => $change]);

            if ($createInvoice && class_exists(\App\Services\InvoiceService::class)) {
                app(\App\Services\InvoiceService::class)->createFromSale($sale, $userId);
            }

            activity()
                ->useLog('pos')
                ->causedBy($userId)
                ->withProperties(['ip' => request()->ip(), 'sale_reference' => $sale->reference, 'total' => $sale->total, 'change' => $change])
                ->log("Cobro POS {$sale->reference}");

            return $sale->refresh();
        });
    }

    /**
     * Busca productos para el terminal: por nombre, SKU o código de barras.
     */
    public function searchProducts(int $warehouseId, string $term, int $limit = 12): Collection
    {
        $query = Product::query()
            ->select(['id', 'name', 'sku', 'barcode', 'min_stock'])
            ->where('is_active', true)
            ->where('type', 'product')
            ->withSum(['inventory as stock' => fn ($q) => $q->where('warehouse_id', $warehouseId)], 'quantity')
            ->orderBy('name');

        if ($term !== '') {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like, $term) {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', '=', $term);
            });
        }

        return $query->limit($limit)->get()->map(fn (Product $p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'stock' => (float) ($p->stock ?? 0),
        ]);
    }
}