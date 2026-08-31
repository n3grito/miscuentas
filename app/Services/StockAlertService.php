<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class StockAlertService
{
    /**
     * Escanea el stock de los productos con umbrales y crea/resuelve alertas.
     * Devuelve la cantidad de alertas abiertas creadas en esta pasada.
     */
    public function scan(?int $warehouseId = null): int
    {
        $created = 0;

        $warehouseIds = Warehouse::where('is_active', true)
            ->when($warehouseId, fn ($q) => $q->where('id', $warehouseId))
            ->pluck('id');

        $products = Product::with('inventory')
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('min_stock')->orWhereNotNull('max_stock');
            })
            ->get();

        foreach ($products as $product) {
            $stockByWarehouse = $product->inventory->keyBy('warehouse_id');

            foreach ($warehouseIds as $wid) {
                $inventory = $stockByWarehouse->get($wid);
                $qty = $inventory ? (float) $inventory->quantity : 0.0;

                $min = $product->min_stock !== null ? (float) $product->min_stock : null;
                $max = $product->max_stock !== null ? (float) $product->max_stock : null;

                $level = null;
                if ($min !== null && $qty < $min) {
                    $level = 'below_min';
                } elseif ($max !== null && $qty > $max) {
                    $level = 'over_max';
                }

                if ($level) {
                    $existing = StockAlert::where('product_id', $product->id)
                        ->where('warehouse_id', $wid)
                        ->where('level', $level)
                        ->where('status', 'open')
                        ->exists();

                    if (! $existing) {
                        StockAlert::create([
                            'product_id' => $product->id,
                            'warehouse_id' => $wid,
                            'current_qty' => $qty,
                            'min_stock' => $product->min_stock,
                            'max_stock' => $product->max_stock,
                            'level' => $level,
                            'status' => 'open',
                        ]);
                        $created++;
                    }
                } else {
                    StockAlert::where('product_id', $product->id)
                        ->where('warehouse_id', $wid)
                        ->where('status', 'open')
                        ->update([
                            'status' => 'resolved',
                            'resolved_at' => Carbon::now(),
                        ]);
                }
            }
        }

        return $created;
    }
}