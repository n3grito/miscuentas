<x-filament-panels::page>
    <style>
        .pos-wrap { display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; }
        @media (max-width: 1024px) { .pos-wrap { grid-template-columns: 1fr; } }
        .pos-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
        .pos-input { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 15px; outline: none; }
        .pos-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgb(37 99 235 / .15); }
        .results { max-height: 300px; overflow-y: auto; margin-top: 10px; }
        .result-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 12px; border-radius: 8px; cursor: pointer; border: 1px solid transparent; }
        .result-row:hover { background: #eff6ff; border-color: #bfdbfe; }
        .cart-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .cart-table th { text-align: left; color: #6b7280; font-weight: 600; font-size: 12px; text-transform: uppercase; padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        .cart-table td { padding: 7px 4px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .qty-input, .price-input { width: 84px; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px 8px; font-size: 14px; text-align: right; }
        .totals { display: flex; justify-content: space-between; padding: 5px 0; font-size: 15px; }
        .totals.grand { font-size: 22px; font-weight: 800; border-top: 2px solid #111827; margin-top: 8px; padding-top: 12px; }
        .change-panel { background: #0f172a; color: #fff; border-radius: 12px; padding: 18px; text-align: center; margin-top: 12px; }
        .change-amount { font-size: 42px; font-weight: 900; letter-spacing: 1px; }
        .missing { color: #fca5a5; font-weight: 700; font-size: 20px; }
        .btn-cobrar { width: 100%; margin-top: 14px; background: #059669; color: #fff; border: 0; border-radius: 10px; padding: 14px; font-size: 17px; font-weight: 800; cursor: pointer; transition: background .15s; }
        .btn-cobrar:hover { background: #047857; }
        .btn-mini { border: 1px solid #d1d5db; background: #fff; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-weight: 600; }
        .btn-mini:hover { background: #f9fafb; }
        .receipt { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 14px; margin-top: 12px; font-size: 15px; }
    </style>

    <div class="pos-wrap">
        {{-- Columna izquierda: búsqueda --}}
        <div class="pos-box">
            <label style="font-weight:700; display:block; margin-bottom:8px;">Buscar producto (nombre, SKU o código de barras)</label>
            <input
                type="text"
                class="pos-input"
                placeholder="Escanee o escriba y presione Enter…"
                wire:model.debounce.300ms="search"
                wire:key="search"
            >

            <div class="results">
                @forelse ($this->results as $r)
                    <div class="result-row" wire:key="r-{{ $r['id'] }}" wire:click="addToCart({{ $r['id'] }})">
                        <div>
                            <div style="font-weight:600">{{ $r['name'] }}</div>
                            <div style="font-size:12px;color:#6b7280">SKU {{ $r['sku'] }}</div>
                        </div>
                        <div style="text-align:right;font-size:13px">
                            <span style="font-weight:800">{{ number_format($r['stock'], 2) }}</span><br>
                            <span style="color:#6b7280">en stock</span>
                        </div>
                    </div>
                @empty
                    @if (trim($search) !== '')
                        <p style="color:#6b7280;padding:10px 4px;">Sin coincidencias.</p>
                    @endif
                @endforelse
            </div>
        </div>

        {{-- Columna derecha: carrito y cobro --}}
        <div class="pos-box">
            <div style="display:flex; gap:10px;">
                <select class="pos-input" wire:model.live="warehouseId" title="Almacén">
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <select class="pos-input" wire:model.live="thirdPartyId">
                    <option value="">Cliente ocasional</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->business_name ?: $c->full_name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn-mini" wire:click="toggleNewCustomer" title="Registrar cliente nuevo">+ Cliente</button>
            </div>

            @if ($showNewCustomer)
                <div style="border:1px dashed #93c5fd;border-radius:10px;padding:12px;margin-top:10px;display:grid;gap:8px;">
                    <input type="text" class="pos-input" placeholder="Nombre completo *" wire:model.defer="newCustomerName">
                    <input type="text" class="pos-input" placeholder="Documento (CI) *" wire:model.defer="newCustomerDocument">
                    <input type="text" class="pos-input" placeholder="Teléfono" wire:model.defer="newCustomerPhone">
                    <button type="button" class="btn-mini" wire:click="quickAddCustomer">Guardar cliente</button>
                </div>
            @endif

            @if (count($this->cart) > 0)
                <table class="cart-table" style="margin-top:12px;">
                    <thead>
                        <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Importe</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($this->cart as $i => $line)
                            <tr wire:key="c-{{ $line['product_id'] }}">
                                <td>
                                    <div style="font-weight:600">{{ $line['name'] }}</div>
                                    <div style="font-size:11px;color:#6b7280">{{ $line['sku'] }} · máx {{ $line['stock'] }}</div>
                                </td>
                                <td>
                                    <input type="number" class="qty-input" min="1" max="{{ $line['stock'] }}" step="1"
                                        wire:model.live.debounce.250ms="cart.{{ $i }}.quantity">
                                </td>
                                <td>
                                    <input type="number" class="price-input" min="0" step="0.01"
                                        wire:model.live.debounce.250ms="cart.{{ $i }}.unit_price">
                                </td>
                                <td style="text-align:right;font-weight:700">
                                    {{ number_format($line['quantity'] * $line['unit_price'], 2) }}
                                </td>
                                <td><button type="button" class="btn-mini" wire:click="removeLine({{ $i }})" title="Quitar">✕</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="color:#6b7280;text-align:center;padding:26px 0;">Carrito vacío — busque productos para comenzar la venta.</p>
            @endif

            <div style="display:flex;gap:10px;margin-top:12px;">
                <div style="flex:1">
                    <label style="font-size:12px;color:#6b7280">Descuento</label>
                    <input type="number" class="pos-input" min="0" step="0.01" wire:model.live.debounce.300ms="discount">
                </div>
                <div style="flex:1">
                    <label style="font-size:12px;color:#6b7280">Impuestos</label>
                    <input type="number" class="pos-input" min="0" step="0.01" wire:model.live.debounce.300ms="taxAmount">
                </div>
            </div>

            <div style="margin-top:10px;">
                <div class="totals"><span>Subtotal</span><strong>{{ number_format($this->subtotal, 2) }}</strong></div>
                <div class="totals grand"><span>TOTAL</span><span>{{ number_format($this->payable, 2) }}</span></div>
            </div>

            <div style="margin-top:10px;">
                <label style="font-size:13px;font-weight:700;display:block;margin-bottom:4px;">Efectivo recibido</label>
                <input type="number" class="pos-input" min="0" step="0.01" placeholder="Monto con el que paga el cliente"
                    wire:model.live.debounce.200ms="cashReceived">
            </div>

            <div class="change-panel">
                <div style="font-size:13px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Cambio a entregar</div>
                @if ($this->cash > 0 && $this->missing > 0)
                    <div class="missing">Faltan {{ number_format($this->missing, 2) }}</div>
                @else
                    <div class="change-amount">{{ number_format($this->change, 2) }}</div>
                @endif
            </div>

            <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:14px;">
                <input type="checkbox" wire:model="createInvoice"> Generar factura automáticamente
            </label>

            <button type="button" class="btn-cobrar" wire:click="checkout" wire:loading.attr="disabled">
                Cobrar venta
            </button>

            @if ($lastReceipt)
                <div class="receipt">
                    ✅ <strong>{{ $lastReceipt['reference'] }}</strong> — Total: {{ $lastReceipt['total'] }}
                    · Efectivo: {{ $lastReceipt['cash'] }}
                    · <strong>Cambio entregado: {{ $lastReceipt['change'] }}</strong>
                </div>
            @endif

            @if (count($this->cart) > 0)
                <button type="button" wire:click="clearCart"
                    style="width:100%;margin-top:8px;background:none;border:0;color:#dc2626;font-weight:600;cursor:pointer;">
                    Vaciar carrito
                </button>
            @endif
        </div>
    </div>
</x-filament-panels::page>
