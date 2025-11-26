<?php

namespace Optima\DepotStock\Services;

use Illuminate\Support\Carbon;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\DepotPolicy;
use Optima\DepotStock\Models\ClientStorageCharge;

class ClientRiskService
{
    public function forCollection(iterable $clients): array
    {
        $out = [];
        foreach ($clients as $client) {
            $out[$client->id] = $this->snapshot($client);
        }
        return $out;
    }

    public function snapshot(Client $client): ClientRiskSnapshot
    {
        // ------------------------------------------------------------------
        // 0) ACTIVE DEPOT CONTEXT
        // ------------------------------------------------------------------
        $activeDepotId = session('depot.active_id');

        // Base queries with optional depot scoping
        $offQ = $client->offloads();
        $loadQ = $client->loads();
        $adjQ  = $client->adjustments();

        if ($activeDepotId) {
            // All three tables (offloads/loads/adjustments) have depot_id
            $offQ->where('depot_id', $activeDepotId);
            $loadQ->where('depot_id', $activeDepotId);
            $adjQ->where('depot_id', $activeDepotId);
        }

        // ------------------------------------------------------------------
        // 1) PHYSICAL STOCK
        //      physical = (delivered - allowance) - loads + adjustments
        // ------------------------------------------------------------------

        $offloads = $offQ->get(['delivered_20_l', 'depot_allowance_20_l', 'date']);

        // Global allowance rate + helper for "effective allowance"
        $allowanceRate = (float) DepotPolicy::getNumeric('allowance_rate', 0.003);

        $effectiveAllowance = function ($o) use ($allowanceRate) {
            // If we have a stored allowance, prefer it
            if ($o->depot_allowance_20_l !== null && $o->depot_allowance_20_l !== '') {
                return (float) $o->depot_allowance_20_l;
            }

            // Otherwise derive from delivered * policy rate
            $del = (float) ($o->delivered_20_l ?? 0);
            return round($del * $allowanceRate, 3);
        };

        $physicalFromOffloads = $offloads->sum(function ($o) use ($effectiveAllowance) {
            $del   = (float) ($o->delivered_20_l ?? 0);
            $allow = $effectiveAllowance($o);
            return $del - $allow;
        });

        $totalLoads     = (float) $loadQ->sum('loaded_20_l');
        $adjustmentsNet = (float) $adjQ->sum('amount_20_l');

        $physicalStock  = $physicalFromOffloads - $totalLoads + $adjustmentsNet;

        // ------------------------------------------------------------------
        // 2) CLEARED STOCK (LITRES THEY HAVE PAID FOR)
        //
        //  - For each invoice we:
        //      * cap paid_total at invoice total (ignore overpayment/credit)
        //      * compute ratio = effective_paid / total  (0–1)
        //      * apply ratio to ONLY fuel litres (exclude storage, etc.)
        //  - Then we add positive non-billable adjustments as always cleared.
        // ------------------------------------------------------------------

        $clearedFromInvoices = 0.0;

        $invoices = $client->invoices()
            ->with('items')
            ->where(function ($q) {
                $q->where('paid_total', '>', 0)
                  ->orWhereIn('status', ['paid', 'partial']);
            })
            ->get();

        foreach ($invoices as $inv) {
            $totalAmount = (float) ($inv->total ?? 0);
            $paidAmount  = (float) ($inv->paid_total ?? 0);

            if ($totalAmount <= 0 || $paidAmount <= 0) {
                continue;
            }

            // 💡 Ignore any overpayment – the extra goes to credit, not litres.
            $effectivePaid = min($paidAmount, $totalAmount);
            $ratio = max(0.0, min(1.0, $effectivePaid / $totalAmount));

            // Only count fuel litres (ignore pure storage / fees)
            $fuelItems = $inv->items
                ->filter(function ($item) {
                    // If you ever add more types, keep 'storage' excluded.
                    return ($item->source_type ?? '') !== 'storage';
                });

            $invoiceLitres = (float) $fuelItems->sum(function ($i) {
                return (float) ($i->litres ?? 0);
            });

            $clearedFromInvoices += $invoiceLitres * $ratio;
        }

        // Positive NON-billable adjustments are always cleared
        $clearedFromNonBillableAdj = (float) (clone $adjQ)
            ->where('amount_20_l', '>', 0)
            ->where(function ($q) {
                // boolean false or NULL = non-billable
                $q->where('is_billable', false)
                  ->orWhereNull('is_billable');
            })
            ->sum('amount_20_l');

        $clearedStock = $clearedFromInvoices + $clearedFromNonBillableAdj;

        // ------------------------------------------------------------------
        // 3) ENTITLEMENT / AVAILABLE / UNCLEARED
        //      We strip ALL allowance out of entitlement.
        // ------------------------------------------------------------------

        $totalAllowance = (float) $offloads->sum(function ($o) use ($effectiveAllowance) {
            return $effectiveAllowance($o);
        });

        // Litres they have paid for but not yet loaded (and not allowance)
        $paidNotLoaded = max(0.0, $clearedStock - $totalLoads - $totalAllowance);

        // Can only load what is both physical AND paid
        $availableToLoad = min($physicalStock, $paidNotLoaded);

        // Physical litres that are still not cleared/entitled
        $unclearedStock = max(0.0, $physicalStock - $availableToLoad);

        // Entitlement gap: entitlement vs physical
        $entitlementGap = $paidNotLoaded - $physicalStock;

        // ------------------------------------------------------------------
        // 4) IDLE STOCK (OLD PHYSICAL LITRES) – LESS STORAGE ALREADY CHARGED
        // ------------------------------------------------------------------

        $idleDays   = (int) DepotPolicy::getNumeric('max_storage_days', 30);
        $cutoffDate = Carbon::today()->subDays($idleDays);

        $oldOffloads = (clone $offQ)
            ->whereDate('date', '<=', $cutoffDate)
            ->orderBy('date')
            ->get(['date', 'delivered_20_l', 'depot_allowance_20_l']);

        $oldLitres = 0.0;
        foreach ($oldOffloads as $o) {
            $del   = (float) ($o->delivered_20_l ?? 0);
            $allow = $effectiveAllowance($o);
            $oldLitres += $del - $allow;
        }

        // Can’t have more idle than total physical in this depot
        $oldLitres = min($oldLitres, $physicalStock);

        // Rough FIFO: subtract all loads from the old block
        $idleLitres = max(0.0, $oldLitres - $totalLoads);

        // Deduct litres already charged & paid as storage
        $chargedIdleLitres = (float) ClientStorageCharge::where('client_id', $client->id)
            ->whereNotNull('paid_at')
            ->sum('cleared_litres');

        if ($chargedIdleLitres > 0) {
            $idleLitres = max(0.0, $idleLitres - $chargedIdleLitres);
        }

        // ------------------------------------------------------------------
        // 5) FLAGS / STATUS  (same codes your Blade already uses)
        // ------------------------------------------------------------------

        $flags       = [];
        $status      = 'ok';
        $primaryFlag = null;

        // A) Storage congestion – any idle litres
        if ($idleLitres > 0.0) {
            $flags[]      = 'storage_congestion';
            $primaryFlag ??= 'storage_congestion';
        }

        // B) Big uncleared stock
        if ($unclearedStock > 200000) {
            $flags[]      = 'no_entitlement_uncleared_stock';
            $primaryFlag ??= 'no_entitlement_uncleared_stock';
        }

        // C) Entitlement gap outside tolerance
        $negativeTol = (float) DepotPolicy::getNumeric('entitlement_negative_tolerance', -80000);

        if (
            $entitlementGap > 0.0001 ||                 // more entitlement than physical
            ($physicalStock <= 0 && $clearedStock > 0) ||
            $entitlementGap < $negativeTol
        ) {
            $flags[]      = 'excess_entitlement_gap';
            $primaryFlag ??= 'excess_entitlement_gap';
        }

        // D) Totally fresh client
        $isFresh = (
            abs($physicalStock)    < 0.0001 &&
            abs($clearedStock)     < 0.0001 &&
            abs($availableToLoad)  < 0.0001 &&
            abs($unclearedStock)   < 0.0001 &&
            abs($idleLitres)       < 0.0001
        );

        if ($isFresh) {
            $flags[]      = 'fresh_client';
            $primaryFlag ??= 'fresh_client';
        }

        if (!empty($flags)) {
            if (
                in_array('no_entitlement_uncleared_stock', $flags, true) ||
                in_array('excess_entitlement_gap', $flags, true)
            ) {
                $status = 'critical';
            } elseif (in_array('storage_congestion', $flags, true)) {
                $status = 'warn';
            }
        }

        // ------------------------------------------------------------------
        // 6) Optional human messages (kept minimal; UI builds its own now)
        // ------------------------------------------------------------------

        $short = null;
        $long  = null;

        if ($isFresh) {
            $short = 'New client with no movements yet.';
            $long  = 'This client has not offloaded, cleared or loaded any litres yet.';
        }

        // ------------------------------------------------------------------
        // 7) RETURN SNAPSHOT
        // ------------------------------------------------------------------

        return new ClientRiskSnapshot(
            client:           $client,
            physicalStock:    $physicalStock,
            clearedStock:     $clearedStock,
            availableToLoad:  $availableToLoad,
            unclearedStock:   $unclearedStock,
            clearedIdleStock: $idleLitres,
            entitlementGap:   $entitlementGap,
            status:           $status,
            primaryFlag:      $primaryFlag,
            flags:            $flags,
            shortMessage:     $short,
            longMessage:      $long,
        );
    }
}