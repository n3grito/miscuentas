<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionItem;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Services\JournalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    /**
     * Registra una entrada de inventario (compra, ajuste positivo, etc.).
     */
    public function increase(
        Product $product,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $movementType,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return DB::transaction(fn () => $this->doIncrease($product, $warehouseId, $quantity, $unitCost, $movementType, $reference, $reason, $createdBy));
    }

    /**
     * Registra una salida de inventario (venta, merma, etc.) validando stock.
     */
    public function decrease(
        Product $product,
        int $warehouseId,
        float $quantity,
        string $movementType,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return DB::transaction(fn () => $this->doDecrease($product, $warehouseId, $quantity, $movementType, $reference, $reason, $createdBy));
    }

    /**
     * Transfiere stock entre almacenes. Devuelve [movimientoSalida, movimientoEntrada].
     */
    public function transfer(
        Product $product,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): array {
        return DB::transaction(function () use ($product, $fromWarehouseId, $toWarehouseId, $quantity, $reference, $reason, $createdBy) {
            $this->assertDifferentWarehouses($fromWarehouseId, $toWarehouseId);

            $this->lockInventoryRowsInOrder([
                [$product->id, $fromWarehouseId],
                [$product->id, $toWarehouseId],
            ]);

            return $this->doTransferInternal($product, $fromWarehouseId, $toWarehouseId, $quantity, $reference, $reason, $createdBy);
        });
    }

    /**
     * Produce un producto terminado a partir de ingredientes (receta).
     * Devuelve [movimientosSalida, movimientoEntrada].
     */
    public function produce(
        Product $outputProduct,
        int $warehouseId,
        float $outputQuantity,
        array $ingredients,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): array {
        return DB::transaction(function () use ($outputProduct, $warehouseId, $outputQuantity, $ingredients, $reference, $reason, $createdBy) {
            $this->lockInventoryRowsInOrder($this->produceLockPairs($outputProduct, $warehouseId, $ingredients));

            return $this->doProduceInternal($outputProduct, $warehouseId, $outputQuantity, $ingredients, $reference, $reason, $createdBy);
        });
    }

    /**
     * Completar una transferencia de stock en borrador.
     */
    public function completeTransfer(StockTransfer $transfer, ?int $userId = null): void
    {
        DB::transaction(function () use ($transfer, $userId) {
            if ($transfer->status !== 'draft') {
                throw new RuntimeException('Solo se pueden completar transferencias en estado borrador.');
            }

            if ($transfer->items()->count() === 0) {
                throw new RuntimeException('La transferencia debe contener al menos un producto.');
            }

            $pairs = $transfer->items->flatMap(fn ($item) => [
                [$item->product_id, $transfer->from_warehouse_id],
                [$item->product_id, $transfer->to_warehouse_id],
            ])->all();

            $this->lockInventoryRowsInOrder($pairs);

            foreach ($transfer->items as $item) {
                [$out] = $this->doTransferInternal(
                    $item->product,
                    $transfer->from_warehouse_id,
                    $transfer->to_warehouse_id,
                    (float) $item->quantity,
                    $transfer,
                    "Transferencia {$transfer->reference}",
                    $userId,
                );

                $item->update([
                    'unit_cost' => (float) $out->unit_cost,
                    'total_cost' => (float) $out->total_cost,
                ]);
            }

            $transfer->update([
                'status' => 'completed',
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Recibe una compra en borrador: ingresa el stock con el costo de cada línea
     * y recalcula los totales del documento.
     */
    public function receivePurchase(Purchase $purchase, ?int $userId = null): void
    {
        DB::transaction(function () use ($purchase, $userId) {
            if ($purchase->status !== 'draft') {
                throw new RuntimeException('Solo se pueden recibir compras en estado borrador.');
            }

            if ($purchase->items()->count() === 0) {
                throw new RuntimeException('La compra debe contener al menos un producto.');
            }

            $pairs = $purchase->items->map(fn ($item) => [$item->product_id, $purchase->warehouse_id])->all();

            $this->lockInventoryRowsInOrder($pairs);

            $subtotal = 0.0;

            foreach ($purchase->items as $item) {
                $movement = $this->doIncrease(
                    $item->product,
                    $purchase->warehouse_id,
                    (float) $item->quantity,
                    (float) $item->unit_cost,
                    'purchase',
                    $purchase,
                    "Compra {$purchase->reference}",
                    $userId,
                );

                $item->update(['total_cost' => (float) $movement->total_cost]);

                $subtotal += (float) $movement->total_cost;
            }

            $tax = (float) $purchase->tax;

            $purchase->update([
                'status' => 'received',
                'subtotal' => $subtotal,
                'total' => $subtotal + $tax,
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            app(JournalService::class)->recordPurchaseEntry($purchase, $userId);
        });
    }

    /**
     * Completa una venta en borrador: descarga el stock al costo promedio,
     * valida existencia suficiente y recalcula los totales del documento.
     * Si falla, no se modifica nada (rollback atómico).
     */
    public function completeSale(Sale $sale, ?int $userId = null): void
    {
        DB::transaction(function () use ($sale, $userId) {
            if ($sale->status !== 'draft') {
                throw new RuntimeException('Solo se pueden completar ventas en estado borrador.');
            }

            if ($sale->items()->count() === 0) {
                throw new RuntimeException('La venta debe contener al menos un producto.');
            }

            $pairs = $sale->items->map(fn ($item) => [$item->product_id, $sale->warehouse_id])->all();

            $this->lockInventoryRowsInOrder($pairs);

            $subtotal = 0.0;
            $costTotal = 0.0;

            foreach ($sale->items as $item) {
                $movement = $this->doDecrease(
                    $item->product,
                    $sale->warehouse_id,
                    (float) $item->quantity,
                    'sale',
                    $sale,
                    "Venta {$sale->reference}",
                    $userId,
                );

                $item->update([
                    'unit_cost' => (float) $movement->unit_cost,
                    'total_cost' => (float) $movement->total_cost,
                    'total_price' => (float) $item->quantity * (float) $item->unit_price,
                ]);

                $subtotal += (float) $item->total_price;
                $costTotal += (float) $movement->total_cost;
            }

            $tax = (float) $sale->tax;
            $discount = max(0.0, (float) $sale->discount);
            $total = round(max($subtotal - $discount, 0.0) + $tax, 6);

            $sale->update([
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'cost_total' => $costTotal,
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);

            app(JournalService::class)->recordSaleEntries($sale, $userId);
        });
    }

    /**
     * Completar una orden de producción en borrador consumiendo la receta asociada.
     */
    public function completeProduction(Production $production, ?int $userId = null): void
    {
        DB::transaction(function () use ($production, $userId) {
            if ($production->status !== 'draft') {
                throw new RuntimeException('Solo se pueden completar producciones en estado borrador.');
            }

            $ingredients = [];

            if ($production->bom) {
                $scale = (float) $production->quantity / (float) $production->bom->output_quantity;

                foreach ($production->bom->items as $item) {
                    $product = $item->product;

                    if ($product->type !== 'product' || ! $product->track_inventory) {
                        continue;
                    }

                    $ingredients[] = [
                        'product' => $product,
                        'quantity' => (float) $item->quantity * $scale,
                    ];
                }
            }

            $this->lockInventoryRowsInOrder($this->produceLockPairs($production->product, $production->warehouse_id, $ingredients));

            [$outs] = $this->doProduceInternal(
                $production->product,
                $production->warehouse_id,
                (float) $production->quantity,
                $ingredients,
                $production,
                "Producción {$production->reference}",
                $userId,
            );

            foreach ($outs as $movement) {
                ProductionItem::updateOrCreate(
                    ['production_id' => $production->id, 'product_id' => $movement->product_id],
                    [
                        'quantity' => $movement->quantity,
                        'unit_cost' => $movement->unit_cost,
                        'total_cost' => $movement->total_cost,
                    ]
                );
            }

            $production->update([
                'status' => 'completed',
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Ajusta el stock de un producto (inventario físico). type: 'in' | 'out'.
     */
    public function adjust(
        Product $product,
        int $warehouseId,
        string $type,
        float $quantity,
        float $unitCost,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        if ($type === 'in') {
            return $this->increase($product, $warehouseId, $quantity, $unitCost, 'adjustment', $reference, $reason, $createdBy);
        }

        return $this->decrease($product, $warehouseId, $quantity, 'adjustment', $reference, $reason, $createdBy);
    }

    private function doTransferInternal(
        Product $product,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        ?Model $reference,
        ?string $reason,
        ?int $userId,
    ): array {
        $out = $this->doDecrease($product, $fromWarehouseId, $quantity, 'transfer_out', $reference, $reason, $userId);
        $in = $this->doIncrease($product, $toWarehouseId, $quantity, (float) $out->unit_cost, 'transfer_in', $reference, $reason, $userId);

        return [$out, $in];
    }

    private function doProduceInternal(
        Product $outputProduct,
        int $warehouseId,
        float $outputQuantity,
        array $ingredients,
        ?Model $reference,
        ?string $reason,
        ?int $userId,
    ): array {
        if ($outputQuantity <= 0) {
            throw new InvalidArgumentException('La cantidad a producir debe ser mayor que cero.');
        }

        $outMovements = [];
        $totalCost = 0.0;

        foreach ($ingredients as $ingredient) {
            $movement = $this->doDecrease($ingredient['product'], $warehouseId, (float) $ingredient['quantity'], 'production', $reference, $reason, $userId);
            $outMovements[] = $movement;
            $totalCost += (float) $movement->total_cost;
        }

        $unitCost = $outputQuantity > 0 ? $totalCost / $outputQuantity : 0.0;

        $inMovement = $this->doIncrease($outputProduct, $warehouseId, $outputQuantity, $unitCost, 'production', $reference, $reason, $userId);

        return [$outMovements, $inMovement];
    }

    private function produceLockPairs(Product $outputProduct, int $warehouseId, array $ingredients): array
    {
        $pairs = array_map(fn ($ing) => [$ing['product']->id, $warehouseId], $ingredients);
        $pairs[] = [$outputProduct->id, $warehouseId];

        return $pairs;
    }

    private function assertDifferentWarehouses(int $from, int $to): void
    {
        if ($from === $to) {
            throw new InvalidArgumentException('El almacén de origen y destino deben ser diferentes.');
        }
    }

    private function doIncrease(
        Product $product,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $movementType,
        ?Model $reference,
        ?string $reason,
        ?int $createdBy,
    ): InventoryMovement {
        $this->assertValidMovement($product, $quantity);

        $inventory = $this->lockInventory($product->id, $warehouseId);

        $oldQuantity = (float) $inventory->quantity;
        $oldCost = (float) $inventory->average_cost;
        $newQuantity = $oldQuantity + $quantity;
        $newCost = $newQuantity > 0
            ? (($oldQuantity * $oldCost) + ($quantity * $unitCost)) / $newQuantity
            : $unitCost;

        $inventory->update([
            'quantity' => $newQuantity,
            'average_cost' => $newCost,
        ]);

        return $this->record($product, $warehouseId, 'in', $movementType, $reference, $quantity, $unitCost, $newQuantity, $reason, $createdBy);
    }

    private function doDecrease(
        Product $product,
        int $warehouseId,
        float $quantity,
        string $movementType,
        ?Model $reference,
        ?string $reason,
        ?int $createdBy,
    ): InventoryMovement {
        $this->assertValidMovement($product, $quantity);

        $inventory = $this->lockInventory($product->id, $warehouseId);

        if ((float) $inventory->quantity < $quantity) {
            throw new RuntimeException(sprintf('Stock insuficiente para "%s": disponible %.4f, solicitado %.4f.', $product->name, (float) $inventory->quantity, $quantity));
        }

        $unitCost = (float) $inventory->average_cost;
        $newQuantity = (float) $inventory->quantity - $quantity;

        $inventory->update(['quantity' => $newQuantity]);

        return $this->record($product, $warehouseId, 'out', $movementType, $reference, $quantity, $unitCost, $newQuantity, $reason, $createdBy);
    }

    private function assertValidMovement(Product $product, float $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if ($product->type === 'service' || ! $product->track_inventory) {
            throw new InvalidArgumentException("El producto \"{$product->name}\" no lleva control de inventario.");
        }
    }

    private function record(
        Product $product,
        int $warehouseId,
        string $type,
        string $movementType,
        ?Model $reference,
        float $quantity,
        float $unitCost,
        float $balanceAfter,
        ?string $reason,
        ?int $createdBy,
    ): InventoryMovement {
        return InventoryMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'movement_type' => $movementType,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference ? $reference->getKey() : null,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }

    private function lockInventory(int $productId, int $warehouseId): Inventory
    {
        // INSERT IGNORE: atómico y seguro ante carreras. Si la fila ya existe
        // (incluso si otro proceso la creó justo antes), no hace nada y evita
        // el error 1062 "Duplicate entry product_id/warehouse_id".
        DB::table('inventory')->insertOrIgnore([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'average_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Adquiere los locks de varias filas de inventario en un orden determinista
     * para evitar interbloqueos entre operaciones concurrentes.
     */
    private function lockInventoryRowsInOrder(array $pairs): void
    {
        $rows = collect($pairs)
            ->map(fn ($pair) => [$pair[0], $pair[1]])
            ->unique()
            ->values()
            ->map(function ($pair) {
                $row = Inventory::where('product_id', $pair[0])->where('warehouse_id', $pair[1])->first();

                return $row
                    ? ['id' => $row->id, 'product_id' => $pair[0], 'warehouse_id' => $pair[1]]
                    : ['id' => PHP_INT_MAX, 'product_id' => $pair[0], 'warehouse_id' => $pair[1]];
            })
            ->sortBy('id')
            ->values();

        foreach ($rows as $row) {
            $this->lockInventory($row['product_id'], $row['warehouse_id']);
        }
    }
}