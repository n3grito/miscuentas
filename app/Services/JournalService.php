<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class JournalService
{
    public const CODE_CASH = '110000';

    public const CODE_INVENTORY = '140000';

    public const CODE_SUPPLIERS = '210000';

    public const CODE_SALES = '410000';

    public const CODE_COST_OF_SALES = '510000';

    public function nextReference(): string
    {
        return app(NumberSequenceService::class)->next('journal_entry', 'ASI-');
    }

    /**
     * Indica si la contabilidad automática está habilitada
     * (ajuste activo y cuentas requeridas presentes).
     */
    public static function isAutoEnabled(): bool
    {
        if (! Setting::get('accounting', 'auto_entries', false)) {
            return false;
        }

        $required = [
            self::CODE_CASH,
            self::CODE_INVENTORY,
            self::CODE_SUPPLIERS,
            self::CODE_SALES,
            self::CODE_COST_OF_SALES,
        ];

        $found = Account::query()->whereIn('code', $required)->pluck('code');

        return $found->count() === count($required);
    }

    /**
     * Contabiliza (publica) un asiento en borrador validando partida doble.
     */
    public function post(JournalEntry $entry, ?int $userId = null): void
    {
        DB::transaction(function () use ($entry, $userId) {
            if ($entry->status !== 'draft') {
                throw new RuntimeException('Solo se pueden contabilizar asientos en estado borrador.');
            }

            $lines = $entry->lines()->with('account')->get();

            if ($lines->isEmpty()) {
                throw new RuntimeException('El asiento debe contener al menos una línea.');
            }

            $debit = round((float) $lines->sum('debit'), 6);
            $credit = round((float) $lines->sum('credit'), 6);

            if ($debit <= 0) {
                throw new RuntimeException('El asiento debe tener un monto mayor que cero.');
            }

            if (abs($debit - $credit) > 0.000001) {
                throw new RuntimeException(sprintf('El asiento no está cuadrado: debe %.2f, haber %.2f.', $debit, $credit));
            }

            foreach ($lines as $line) {
                if ((float) $line->debit > 0 && (float) $line->credit > 0) {
                    throw new InvalidArgumentException('Cada línea solo puede llevar cargo (debe) o abono (haber), no ambos.');
                }
            }

            $entry->update([
                'status' => 'posted',
                'total_debit' => $debit,
                'total_credit' => $credit,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);
        });
    }

    /**
     * Crea y contabiliza automáticamente el asiento de una compra recibida:
     * Debe Inventario / Haber Proveedores (o Caja si no tiene proveedor).
     */
    public function recordPurchaseEntry(Purchase $purchase, ?int $userId = null): ?JournalEntry
    {
        if (! self::isAutoEnabled()) {
            return null;
        }

        $total = round((float) $purchase->total, 6);

        if ($total <= 0) {
            return null;
        }

        $creditCode = $purchase->third_party_id ? self::CODE_SUPPLIERS : self::CODE_CASH;

        return $this->createPostedEntry(
            date: $purchase->received_at ?? now(),
            description: "Compra {$purchase->reference}",
            lines: [
                ['code' => self::CODE_INVENTORY, 'debit' => $total, 'credit' => 0, 'memo' => "Ingreso a inventario {$purchase->reference}"],
                ['code' => $creditCode, 'debit' => 0, 'credit' => $total, 'memo' => $creditCode === self::CODE_SUPPLIERS ? 'Proveedor' : 'Pago en efectivo'],
            ],
            userId: $userId,
        );
    }

    /**
     * Crea y contabiliza los asientos de una venta completada:
     * cobro (Debe Caja / Haber Ventas) y costo (Debe Costo de ventas / Haber Inventario).
     */
    public function recordSaleEntries(Sale $sale, ?int $userId = null): array
    {
        if (! self::isAutoEnabled()) {
            return [];
        }

        $total = round((float) $sale->total, 6);
        $cost = round((float) $sale->cost_total, 6);

        $entries = [];

        if ($total > 0) {
            $entries[] = $this->createPostedEntry(
                date: $sale->completed_at ?? now(),
                description: "Venta {$sale->reference}",
                lines: [
                    ['code' => self::CODE_CASH, 'debit' => $total, 'credit' => 0, 'memo' => "Cobro venta {$sale->reference}"],
                    ['code' => self::CODE_SALES, 'debit' => 0, 'credit' => $total, 'memo' => 'Ingreso por ventas'],
                ],
                userId: $userId,
            );
        }

        if ($cost > 0) {
            $entries[] = $this->createPostedEntry(
                date: $sale->completed_at ?? now(),
                description: "Costo de venta {$sale->reference}",
                lines: [
                    ['code' => self::CODE_COST_OF_SALES, 'debit' => $cost, 'credit' => 0, 'memo' => 'Costo de ventas'],
                    ['code' => self::CODE_INVENTORY, 'debit' => 0, 'credit' => $cost, 'memo' => "Salida de inventario {$sale->reference}"],
                ],
                userId: $userId,
            );
        }

        return $entries;
    }

    /**
     * Crea un asiento ya contabilizado con líneas dadas por código de cuenta.
     */
    public function createPostedEntry($date, string $description, array $lines, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($date, $description, $lines, $userId) {
            $accounts = Account::query()
                ->whereIn('code', collect($lines)->pluck('code'))
                ->get()
                ->keyBy('code');

            foreach ($lines as $line) {
                if (! $accounts->has($line['code'])) {
                    throw new RuntimeException("La cuenta {$line['code']} no existe en el catálogo.");
                }
            }

            $debit = round(collect($lines)->sum(fn ($l) => (float) $l['debit']), 6);
            $credit = round(collect($lines)->sum(fn ($l) => (float) $l['credit']), 6);

            if ($debit <= 0 || abs($debit - $credit) > 0.000001) {
                throw new RuntimeException('Asiento automático inválido: no está cuadrado.');
            }

            $entry = JournalEntry::create([
                'reference' => $this->nextReference(),
                'date' => $date,
                'description' => $description,
                'status' => 'posted',
                'total_debit' => $debit,
                'total_credit' => $credit,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts->get($line['code'])->id,
                    'debit' => (float) $line['debit'],
                    'credit' => (float) $line['credit'],
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }
}