<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        protected NumberSequenceService $sequences,
    ) {}

    /**
     * Genera la factura de una venta completada.
     * Cada venta puede facturarse una única vez; los totales se copian
     * de la venta (inmutable tras completarse).
     */
    public function createFromSale(Sale $sale, ?int $userId, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($sale, $userId, $notes) {
            $sale = Sale::query()
                ->with('invoice')
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($sale->status !== 'completed') {
                throw new RuntimeException('Solo se pueden facturar ventas completadas.');
            }

            if ($sale->invoice) {
                throw new RuntimeException("La venta {$sale->reference} ya tiene la factura {$sale->invoice->number}.");
            }

            if ((float) $sale->total <= 0) {
                throw new RuntimeException('El total de la venta debe ser mayor que cero para facturar.');
            }

            $currency = Currency::where('is_base', true)->where('is_active', true)->first();

            $invoice = Invoice::create([
                'number' => $this->sequences->next('invoice', 'FAC-'),
                'sale_id' => $sale->id,
                'third_party_id' => $sale->third_party_id,
                'currency_id' => $currency?->id,
                'issue_date' => now()->toDateString(),
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'tax' => $sale->tax,
                'total' => $sale->total,
                'status' => 'issued',
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            activity()
                ->useLog('invoice')
                ->causedBy($userId)
                ->withProperties(['ip' => request()->ip(), 'number' => $invoice->number, 'sale_reference' => $sale->reference])
                ->log("Factura {$invoice->number} generada desde venta {$sale->reference}");

            return $invoice;
        });
    }

    /**
     * Cancela una factura emitida. La operación queda auditada.
     */
    public function cancel(Invoice $invoice, ?int $userId): void
    {
        if ($invoice->status !== 'issued') {
            throw new RuntimeException('Solo se pueden cancelar facturas emitidas.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
        ]);

        activity()
            ->useLog('invoice')
            ->causedBy($userId)
            ->withProperties(['ip' => request()->ip(), 'number' => $invoice->number])
            ->log("Factura {$invoice->number} cancelada");
    }
}