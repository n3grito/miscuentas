<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use SoftDeletes, LogsActivity;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product): void {
            if (blank($product->sku)) {
                $product->sku = static::nextSku();
            }
        });
    }

    /**
     * Genera el siguiente SKU automático de forma segura ante concurrencia.
     */
    public static function nextSku(): string
    {
        return DB::transaction(function () {
            $max = static::withTrashed()
                ->lockForUpdate()
                ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(sku, 5) AS UNSIGNED)), 0) as max_num')
                ->where('sku', 'like', 'SKU-%')
                ->value('max_num');

            return 'SKU-'.str_pad((string) ((int) $max + 1), 6, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'category_id',
        'unit_id',
        'name',
        'sku',
        'barcode',
        'description',
        'type',
        'track_inventory',
        'cost_method',
        'min_stock',
        'max_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'min_stock' => 'decimal:4',
            'max_stock' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function stockQuantity(?int $warehouseId = null): float
    {
        return $this->inventory
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    /**
     * Indica si el artículo se controla por existencias (producto físico con
     * control de inventario). Los servicios y los artículos sin inventario
     * no participan en el stock ni en los cálculos de costo.
     */
    public function tracksInventory(): bool
    {
        return $this->type === 'product' && (bool) $this->track_inventory;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'category_id',
                'unit_id',
                'name',
                'sku',
                'barcode',
                'description',
                'type',
                'track_inventory',
                'cost_method',
                'min_stock',
                'max_stock',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('product');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }
}