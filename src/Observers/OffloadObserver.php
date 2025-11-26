<?php

namespace Optima\DepotStock\Observers;

use Illuminate\Support\Facades\Auth;
use Optima\DepotStock\Models\DepotPoolEntry;
use Optima\DepotStock\Models\Offload;

class OffloadObserver
{
    // 0.3% allowance factor
    private const ALLOWANCE_RATE = 0.003;

    public function created(Offload $offload): void
    {
        // Only client offloads produce allowance
        if (empty($offload->client_id)) return;

        $amount = round((float)($offload->delivered_20_l ?? 0) * self::ALLOWANCE_RATE, 3);
        if ($amount <= 0) return;

        // One base entry per offload (guarded by unique index (ref_type, ref_id))
        DepotPoolEntry::allowance([
            'depot_id'    => $offload->depot_id,
            'product_id'  => $offload->product_id,
            'date'        => $offload->date ?: now()->toDateString(),
            'volume_20_l' => $amount,
            'ref_id'      => $offload->id,
            'note'        => '0.3% allowance from offload #'.$offload->id,
            'created_by'  => Auth::id(),
        ]);
    }

    public function updated(Offload $offload): void
    {
        // If client_id flipped to null → reverse current allowance
        if ($offload->wasChanged('client_id')) {
            $wasClient = (bool) $offload->getOriginal('client_id');
            $isClient  = (bool) $offload->client_id;

            if ($wasClient && !$isClient) {
                // reverse current allowance based on *new* delivered value or original? Use new == zero-effect: we want to wipe allowance
                $current = round((float)($offload->delivered_20_l ?? 0) * self::ALLOWANCE_RATE, 3);
                if ($current > 0) {
                    DepotPoolEntry::allowanceReversal($current, [
                        'depot_id'   => $offload->depot_id,
                        'product_id' => $offload->product_id,
                        'date'       => $offload->date ?: now()->toDateString(),
                        'ref_id'     => $offload->id,
                        'note'       => 'Reversal after offload client cleared',
                        'created_by' => Auth::id(),
                    ]);
                }
                return; // nothing else to do
            }

            if (!$wasClient && $isClient) {
                // became a client offload → create base allowance now
                $this->created($offload);
                // (no early return; if litres also changed, the delta logic below will handle it as well)
            }
        }

        // Delta for delivered_20_l → post allowance_correction
        if ($offload->wasChanged('delivered_20_l') && (bool)$offload->client_id) {
            $old = (float) $offload->getOriginal('delivered_20_l');
            $new = (float) ($offload->delivered_20_l ?? 0);
            $delta = ($new - $old) * self::ALLOWANCE_RATE;

            DepotPoolEntry::allowanceCorrection($delta, [
                'depot_id'   => $offload->depot_id,
                'product_id' => $offload->product_id,
                'date'       => $offload->date ?: now()->toDateString(),
                'ref_id'     => $offload->id,
                'note'       => 'Allowance correction from offload edit',
                'created_by' => Auth::id(),
            ]);
        }
    }

    public function deleted(Offload $offload): void
    {
        // Only reverse if it was a client offload
        if (empty($offload->client_id)) return;

        $amount = round((float)($offload->delivered_20_l ?? 0) * self::ALLOWANCE_RATE, 3);
        if ($amount <= 0) return;

        DepotPoolEntry::allowanceReversal($amount, [
            'depot_id'   => $offload->depot_id,
            'product_id' => $offload->product_id,
            'date'       => $offload->date ?: now()->toDateString(),
            'ref_id'     => $offload->id,
            'note'       => 'Allowance reversal after offload deletion',
            'created_by' => Auth::id(),
        ]);
    }
}