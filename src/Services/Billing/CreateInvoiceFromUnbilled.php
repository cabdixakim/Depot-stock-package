<?php

namespace Optima\DepotStock\Services\Billing;

use Optima\DepotStock\Models\{Invoice, InvoiceItem, Offload, Adjustment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateInvoiceFromUnbilled
{
    /**
     * Generate an invoice for a client.
     * Supports optional explicit selections (offload_ids / adjustment_ids)
     *
     * @param int         $clientId
     * @param string|null $from
     * @param string|null $to
     * @param float|null  $ratePerLitre
     * @param array       $filters
     */
    public function handle(int $clientId, ?string $from, ?string $to, ?float $ratePerLitre, array $filters = []): Invoice
    {
        return DB::transaction(function () use ($clientId, $from, $to, $ratePerLitre, $filters) {

            $number = $this->nextNumber();

            $invoice = Invoice::create([
                'client_id'       => $clientId,
                'date'            => now()->toDateString(),
                'number'          => $number,
                'status'          => 'draft',
                'total'           => 0,
                'currency'        => 'USD',
            ]);

            $offloadIds    = $filters['offload_ids'] ?? [];
            $adjustmentIds = $filters['adjustment_ids'] ?? [];

            // ──────────────────────────────────────────────
            // 1️⃣  Offloads
            // ──────────────────────────────────────────────
            $offloadsQ = Offload::unbilledForClient($clientId);
            if ($offloadIds) {
                $offloadsQ->whereIn('id', $offloadIds);
            } else {
                if ($from) $offloadsQ->whereDate('date', '>=', $from);
                if ($to)   $offloadsQ->whereDate('date', '<=', $to);
            }
            $offloads = $offloadsQ->lockForUpdate()->get();

            // ──────────────────────────────────────────────
            // 2️⃣  Adjustments (only positive + billable)
            // ──────────────────────────────────────────────
            $adjsQ = Adjustment::billableUnbilledForClient($clientId);
            if ($adjustmentIds) {
                $adjsQ->whereIn('id', $adjustmentIds);
            } else {
                if ($from) $adjsQ->whereDate('date', '>=', $from);
                if ($to)   $adjsQ->whereDate('date', '<=', $to);
            }
            $adjs = $adjsQ->lockForUpdate()->get();

            $totalLitres = 0;
            $totalAmount = 0;

            // ──────────────────────────────────────────────
            // Build items from Offloads
            // ──────────────────────────────────────────────
            foreach ($offloads as $o) {
                $litres = (float) $o->delivered_20_l;
                $amount = $ratePerLitre ? round($litres * $ratePerLitre, 2) : 0;

                InvoiceItem::create([
                    'invoice_id'     => $invoice->id,
                    'client_id'      => $clientId,
                    'source_type'    => 'offload',
                    'source_id'      => $o->id,
                    'date'           => $o->date,
                    'description'    => "Offload – Tank #{$o->tank_id}",
                    'litres'         => $litres,
                    'rate_per_litre' => $ratePerLitre,
                    'amount'         => $amount,
                    'meta'           => [
                        'depot'    => $o->depot->name ?? null,
                        'product'  => $o->product->name ?? null,
                        'tank_id'  => $o->tank_id,
                        'reference'=> $o->reference ?? null,
                    ],
                ]);

                $o->update(['billed_invoice_id' => $invoice->id, 'billed_at' => now()]);
                $totalLitres += $litres;
                $totalAmount += $amount;
            }

            // ──────────────────────────────────────────────
            // Build items from Adjustments
            // ──────────────────────────────────────────────
            foreach ($adjs as $a) {
                $litres = (float) abs($a->amount_20_l);
                $amount = $ratePerLitre ? round($litres * $ratePerLitre, 2) : 0;

                InvoiceItem::create([
                    'invoice_id'     => $invoice->id,
                    'client_id'      => $clientId,
                    'source_type'    => 'adjustment',
                    'source_id'      => $a->id,
                    'date'           => $a->date,
                    'description'    => $a->reason ? "Adjustment (+) – {$a->reason}" : "Adjustment (+)",
                    'litres'         => $litres,
                    'rate_per_litre' => $ratePerLitre,
                    'amount'         => $amount,
                    'meta'           => [
                        'depot'    => $a->depot->name ?? null,
                        'product'  => $a->product->name ?? null,
                        'tank_id'  => $a->tank_id,
                    ],
                ]);

                $a->update(['billed_invoice_id' => $invoice->id, 'billed_at' => now()]);
                $totalLitres += $litres;
                $totalAmount += $amount;
            }

            // ──────────────────────────────────────────────
            // Finalize invoice
            // ──────────────────────────────────────────────
            $invoice->update([
                'subtotal'   => $totalAmount,
                'tax_total'  => 0,
                'total'      => $totalAmount,
                'notes'      => "Auto-generated for {$totalLitres} litres @ {$ratePerLitre}/L",
                'status'     => 'issued',
            ]);

            return $invoice->fresh(['items']);
        });
    }

    protected function nextNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        $seq = str_pad(
            (string)(DB::table('invoices')->where('number', 'like', "$prefix%")->count() + 1),
            4,
            '0',
            STR_PAD_LEFT
        );
        return $prefix . $seq;
    }
}