<?php

namespace App\Filament\Pages;

use App\Models\ThirdParty;
use App\Models\Warehouse;
use App\Services\PosService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class PosTerminal extends Page implements HasForms
{
    use InteractsWithForms;

    /** Carrito de la venta actual. */
    public array $cart = [];

    /** Resultados de búsqueda de productos. */
    public Collection $results;

    public string $search = '';

    public int $warehouseId = 0;

    public ?int $thirdPartyId = null;

    public float|string $discount = 0;

    public float|string $taxAmount = 0;

    public float|string $cashReceived = '';

    public bool $createInvoice = false;

    public ?array $lastReceipt = null;

    /* Alta rápida de cliente */
    public bool $showNewCustomer = false;

    public string $newCustomerName = '';

    public string $newCustomerDocument = '';

    public string $newCustomerPhone = '';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string $view = 'filament.pages.pos-terminal';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $title = 'Punto de venta';

    protected static ?string $slug = 'pos';

    protected static ?int $navigationSort = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('use_pos'), 403);

        $this->results = collect();
        $this->warehouseId = (int) (Warehouse::where('is_active', true)->orderBy('id')->value('id') ?? 0);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('use_pos') ?? false;
    }

    public function updatedSearch(): void
    {
        $this->results = app(PosService::class)->searchProducts($this->warehouseId, trim($this->search));
    }

    /**
     * Agrega un producto al carrito validando existencia en el servidor.
     * Los artículos sin control de inventario (servicios y productos no
     * inventariables) pueden agregarse sin restricción de existencias.
     */
    public function addToCart(int $productId): void
    {
        $data = app(PosService::class)->searchProducts($this->warehouseId, '', 500)
            ->firstWhere('id', $productId);

        if (! $data) {
            Notification::make()->title('Artículo no disponible en este almacén.')->danger()->send();

            return;
        }

        // Los artículos sin inventario no tienen existencias que validar.
        if (! $data['tracks_inventory']) {
            foreach ($this->cart as $i => $line) {
                if ((int) $line['product_id'] === $productId) {
                    $this->cart[$i]['quantity']++;

                    return;
                }
            }

            $this->cart[] = [
                'product_id' => (int) $data['id'],
                'name' => $data['name'],
                'sku' => $data['sku'],
                'quantity' => 1,
                'unit_price' => 0,
                'stock' => null,
            ];

            return;
        }

        foreach ($this->cart as $i => $line) {
            if ((int) $line['product_id'] === $productId) {
                if ($line['quantity'] + 1 > $line['stock']) {
                    Notification::make()
                        ->title("Stock insuficiente para {$line['name']} (disponible: {$line['stock']}).")
                        ->danger()
                        ->send();

                    return;
                }

                $this->cart[$i]['quantity']++;

                return;
            }
        }

        if (((float) $data['stock']) < 1) {
            Notification::make()
                ->title("{$data['name']} sin existencias disponibles.")
                ->danger()
                ->send();

            return;
        }

        $this->cart[] = [
            'product_id' => (int) $data['id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'quantity' => 1,
            'unit_price' => 0,
            'stock' => (float) $data['stock'],
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discount = 0;
        $this->taxAmount = 0;
        $this->cashReceived = '';
    }

    public function toggleNewCustomer(): void
    {
        $this->showNewCustomer = ! $this->showNewCustomer;
    }

    /**
     * Alta rápida de cliente desde el propio terminal.
     */
    public function quickAddCustomer(): void
    {
        $name = trim($this->newCustomerName);
        $document = trim($this->newCustomerDocument);

        if ($name === '' || $document === '') {
            Notification::make()->title('Nombre y documento del cliente son obligatorios.')->danger()->send();

            return;
        }

        try {
            $customer = DB::transaction(function () use ($name, $document) {
                return ThirdParty::create([
                    'type' => 'customer',
                    'identity_type' => 'CI',
                    'identity_number' => $document,
                    'full_name' => $name,
                    'phone' => trim($this->newCustomerPhone) ?: null,
                    'is_active' => true,
                ]);
            });
        } catch (\Illuminate\Database\QueryException) {
            Notification::make()->title('Ese documento ya está registrado para otro tercero.')->danger()->send();

            return;
        }

        $this->thirdPartyId = $customer->id;
        $this->newCustomerName = '';
        $this->newCustomerDocument = '';
        $this->newCustomerPhone = '';
        $this->showNewCustomer = false;

        Notification::make()->title("Cliente {$customer->displayName()} registrado.")->success()->send();
    }

    public function getSubtotalProperty(): float
    {
        return round(collect($this->cart)->sum(fn ($l) => (float) $l['quantity'] * (float) $l['unit_price']), 2);
    }

    public function getPayableProperty(): float
    {
        $discount = max(0.0, (float) $this->discount);
        $tax = max(0.0, (float) $this->taxAmount);

        return round(max($this->subtotal - $discount, 0) + $tax, 2);
    }

    public function getCashProperty(): float
    {
        return (float) ($this->cashReceived === '' ? 0 : $this->cashReceived);
    }

    public function getChangeProperty(): float
    {
        return round(max($this->cash - $this->payable, 0), 2);
    }

    public function getMissingProperty(): float
    {
        return round(max($this->payable - $this->cash, 0), 2);
    }

    /**
     * Cobra la venta: valida efectivo y stock en el servidor y descarga inventario.
     */
    public function checkout(): void
    {
        if (count($this->cart) === 0) {
            Notification::make()->title('El carrito está vacío.')->warning()->send();

            return;
        }

        $lines = collect($this->cart)
            ->map(fn ($l): array => [
                'product_id' => (int) $l['product_id'],
                'quantity' => (float) $l['quantity'],
                'unit_price' => (float) $l['unit_price'],
            ])
            ->all();

        try {
            $sale = app(PosService::class)->checkout(
                warehouseId: $this->warehouseId,
                lines: $lines,
                thirdPartyId: $this->thirdPartyId,
                discount: (float) $this->discount,
                tax: (float) $this->taxAmount,
                cashReceived: $this->cashReceived === '' ? null : (float) $this->cashReceived,
                userId: auth()->id(),
                createInvoice: $this->createInvoice,
            );
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $changeText = number_format((float) $sale->change_given, 2);

        Notification::make()
            ->title("Venta {$sale->reference} cobrada. Cambio: {$changeText}")
            ->success()
            ->send();

        $this->lastReceipt = [
            'reference' => $sale->reference,
            'total' => number_format((float) $sale->total, 2),
            'cash' => number_format((float) $sale->cash_received, 2),
            'change' => $changeText,
        ];

        $this->clearCart();
        $this->createInvoice = false;
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('newCustomerName')->label('Nombre completo'),
        ];
    }

    public function getViewData(): array
    {
        return [
            'customers' => ThirdParty::query()
                ->whereIn('type', ['customer', 'both'])
                ->where('is_active', true)
                ->orderBy('business_name')
                ->orderBy('full_name')
                ->get(['id', 'business_name', 'full_name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
