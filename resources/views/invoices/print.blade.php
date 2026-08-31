<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $invoice->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111827; padding: 32px; font-size: 14px; }
        .head { display: flex; justify-content: space-between; border-bottom: 3px solid #111827; padding-bottom: 16px; }
        h1 { font-size: 26px; }
        .muted { color: #6b7280; }
        .meta { text-align: right; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; }
        .badge.issued { background: #d1fae5; color: #065f46; }
        .badge.cancelled { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th { background: #f3f4f6; text-align: left; padding: 9px 10px; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #d1d5db; }
        td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        td.num, th.num { text-align: right; }
        .totals { width: 320px; margin-left: auto; margin-top: 16px; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand { font-size: 19px; font-weight: 800; border-top: 2px solid #111827; margin-top: 6px; padding-top: 10px !important; }
        footer { margin-top: 42px; border-top: 1px solid #e5e7eb; padding-top: 14px; color: #6b7280; font-size: 12px; text-align: center; }
        @media print { .noprint { display: none; } }
        .btn-print { position: fixed; top: 18px; right: 18px; background: #2563eb; color: #fff; border: 0; border-radius: 8px; padding: 12px 22px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <button class="btn-print noprint" onclick="window.print()">🖨 Imprimir</button>

    <div class="head">
        <div>
            <h1>{{ config('app.name', 'MisCuentas') }}</h1>
            <p class="muted">FACTURA</p>
        </div>
        <div class="meta">
            <h1>{{ $invoice->number }}</h1>
            <p>Emisión: {{ $invoice->issue_date?->format('d/m/Y') }}</p>
            <span class="badge {{ $invoice->status }}">{{ $invoice->status === 'cancelled' ? 'CANCELADA' : 'EMITIDA' }}</span>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; margin-top:20px;">
        <div>
            <strong>Cliente:</strong>
            {{ $invoice->thirdParty?->displayName() ?? 'Consumidor final' }}
            @if ($invoice->thirdParty)
                <br><span class="muted">
                    {{ $invoice->thirdParty->identity_type }}: {{ $invoice->thirdParty->identity_number }}
                    @if ($invoice->thirdParty->phone) · Tel: {{ $invoice->thirdParty->phone }} @endif
                </span>
            @endif
        </div>
        <div style="text-align:right;">
            <strong>Venta:</strong> {{ $invoice->sale?->reference }}<br>
            <strong>Moneda:</strong> {{ $invoice->currency?->code ?? '—' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th><th>Producto</th>
                <th class="num">Cantidad</th><th class="num">Precio unit.</th><th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->sale?->items ?? [] as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product?->name }}</td>
                    <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->total_price, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Sin líneas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div><span class="muted">Subtotal</span><span>{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
        <div><span class="muted">Descuento</span><span>-{{ number_format((float) $invoice->discount, 2) }}</span></div>
        <div><span class="muted">Impuestos</span><span>{{ number_format((float) $invoice->tax, 2) }}</span></div>
        <div class="grand"><span>TOTAL</span><span>{{ number_format((float) $invoice->total, 2) }}</span></div>
    </div>

    @if ($invoice->notes)
        <p style="margin-top:20px;"><strong>Notas:</strong> {{ $invoice->notes }}</p>
    @endif

    <footer>
        Documento generado el {{ now()->format('d/m/Y H:i') }} · Gracias por su compra.
    </footer>
</body>
</html>
