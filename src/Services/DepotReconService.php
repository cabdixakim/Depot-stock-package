<?php

namespace Optima\DepotStock\Services;

use Carbon\Carbon;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\DepotReconDay;
use Optima\DepotStock\Models\DepotReconDip;
use Optima\DepotStock\Models\Load;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Tank;

class DepotReconService
{
    /**
     * Ensure a recon day row exists for this tank+date.
     */
    public function getOrCreateDay(Tank $tank, Carbon $date, ?int $userId = null): DepotReconDay
    {
        $day = DepotReconDay::firstOrNew([
            'tank_id' => $tank->id,
            'date'    => $date->toDateString(),
        ]);

        if (! $day->exists) {
            $day->status = 'draft';
            $day->created_by_user_id = $userId;
            $day->save();
        }

        return $day->fresh();
    }

    /**
     * Store an opening dip and update opening_l_20.
     */
    public function saveOpeningDip(
        Tank $tank,
        Carbon $date,
        float $dipHeight,
        float $tempC,
        float $density,
        float $volume20,
        ?int $userId = null
    ): DepotReconDay {
        $day = $this->getOrCreateDay($tank, $date, $userId);

        // Delete old opening dips for this day to keep just one
        $day->dips()->where('type', 'opening')->delete();

        DepotReconDip::create([
            'recon_day_id'     => $day->id,
            'type'             => 'opening',
            'dip_height_cm'    => $dipHeight,
            'temperature_c'    => $tempC,
            'density_kg_l'     => $density,
            'volume_20_l'      => $volume20,
            'captured_at'      => now(),
            'created_by_user_id' => $userId,
        ]);

        $day->opening_l_20 = $volume20;
        $this->recomputeExpectedClosing($day, $date);
        $day->save();

        return $day->fresh('dips');
    }

    /**
     * Store a closing dip and update closing_actual_l_20 + variance.
     */
    public function saveClosingDip(
        Tank $tank,
        Carbon $date,
        float $dipHeight,
        float $tempC,
        float $density,
        float $volume20,
        ?int $userId = null
    ): DepotReconDay {
        $day = $this->getOrCreateDay($tank, $date, $userId);

        // Delete old closing dips for this day to keep just one
        $day->dips()->where('type', 'closing')->delete();

        DepotReconDip::create([
            'recon_day_id'     => $day->id,
            'type'             => 'closing',
            'dip_height_cm'    => $dipHeight,
            'temperature_c'    => $tempC,
            'density_kg_l'     => $density,
            'volume_20_l'      => $volume20,
            'captured_at'      => now(),
            'created_by_user_id' => $userId,
        ]);

        $day->closing_actual_l_20 = $volume20;

        $this->recomputeExpectedClosing($day, $date);
        $this->recomputeVariance($day);

        $day->save();

        return $day->fresh('dips');
    }

    /**
     * Mark day as locked (no more edits unless you later add an unlock flow).
     */
    public function lockDay(DepotReconDay $day, ?int $checkerUserId = null): DepotReconDay
    {
        $day->status = 'locked';
        if ($checkerUserId) {
            $day->checked_by_user_id = $checkerUserId;
        }
        $day->save();

        return $day->fresh();
    }

    /**
     * Recompute expected closing using movements for that tank+date.
     */
    public function recomputeExpectedClosing(DepotReconDay $day, Carbon $date): void
    {
        if ($day->opening_l_20 === null) {
            $day->closing_expected_l_20 = null;
            return;
        }

        $totals = $this->movementTotalsForDay($day->tank_id, $date);

        $expected = $day->opening_l_20
            + ($totals['offloads_l'] ?? 0.0)
            - ($totals['loads_l'] ?? 0.0)
            + ($totals['adj_l_20'] ?? 0.0);

        $day->closing_expected_l_20 = $expected;
    }

    /**
     * Compute variance fields.
     */
    public function recomputeVariance(DepotReconDay $day): void
    {
        if ($day->closing_actual_l_20 === null || $day->closing_expected_l_20 === null) {
            $day->variance_l_20 = null;
            $day->variance_pct  = null;
            return;
        }

        $var = $day->closing_actual_l_20 - $day->closing_expected_l_20;
        $day->variance_l_20 = $var;

        if ($day->closing_expected_l_20 != 0.0) {
            $day->variance_pct = ($var / $day->closing_expected_l_20) * 100.0;
        } else {
            $day->variance_pct = null;
        }
    }

    /**
     * Get expected opening for a day: yesterday's closing dip, fallback to null.
     */
    public function getExpectedOpening(Tank $tank, Carbon $date): ?float
    {
        $prevDate = $date->copy()->subDay();
        $prevDay = DepotReconDay::where('tank_id', $tank->id)
            ->whereDate('date', $prevDate->toDateString())
            ->first();
        if ($prevDay && $prevDay->closing_actual_l_20 !== null) {
            return (float) $prevDay->closing_actual_l_20;
        }
        return null;
    }

    /**
     * Flag opening variance for a day: actual opening vs expected opening.
     * Adds opening_variance_l_20 and opening_variance_flag fields (if present).
     */
    public function flagOpeningVariance(DepotReconDay $day, Tank $tank, Carbon $date): void
    {
        // Calculate expected opening (previous day's closing_actual_l_20)
        $prevDate = $date->copy()->subDay();
        $prevDay = DepotReconDay::where('tank_id', $tank->id)
            ->whereDate('date', $prevDate->toDateString())
            ->first();
        $expectedOpening = $prevDay?->closing_actual_l_20;
        if ($expectedOpening === null || $day->opening_l_20 === null) {
            $day->opening_variance_l_20 = null;
            $day->opening_variance_flag = false;
            return;
        }
        $var = $day->opening_l_20 - $expectedOpening;
        $day->opening_variance_l_20 = $var;
        // Flag if variance is nonzero (or set a tolerance if needed)
        $day->opening_variance_flag = abs($var) > 0.0;
    }

    /**
     * Compute opening balance for a tank and date.
     * Returns float|null
     */
    public function openingBalanceForDay(int $tankId, Carbon $date): ?float
    {
        // Opening dip for this day
        $day = DepotReconDay::where('tank_id', $tankId)
            ->whereDate('date', $date->toDateString())
            ->first();
        if ($day && $day->opening_l_20 !== null) {
            return (float) $day->opening_l_20;
        }
        return null;
    }

    /**
     * Get movement totals for one tank and one day.
     *
     * Returns:
     * [
     *   'in_l_20'  => float,  // offloads in
     *   'out_l_20' => float,  // loads out
     *   'adj_l_20' => float,  // adjustments (signed)
     *   'offloads_l' => float, // total offloads
     *   'loads_l'    => float, // total loads
     *   'net_l'    => float,  // net movement (offloads - loads + adjustments)
     * ]
     */
    public function movementTotalsForDay(int $tankId, Carbon $date): array
    {
        $d = $date->toDateString();

        // IN: offloads into tank
        $offloadsIn = (float) Offload::query()
            ->where('tank_id', $tankId)
            ->whereDate('date', $d)
            ->sum('delivered_20_l');

        // OUT: loads from tank
        $loadsOut = (float) Load::query()
            ->where('tank_id', $tankId)
            ->whereDate('date', $d)
            ->sum('loaded_20_l');

        // ADJ: signed (+ adds stock, - reduces stock)
        $adj = (float) Adjustment::query()
            ->where('tank_id', $tankId)
            ->whereDate('date', $d)
            ->sum('amount_20_l');

        // Correct logic: offloads = IN, loads = OUT
        $in  = $offloadsIn;
        $out = $loadsOut;
        $net = $offloadsIn - $loadsOut + $adj;

        return [
            'in_l_20'  => round($in, 4),
            'out_l_20' => round($out, 4),
            'adj_l_20' => round($adj, 4),
            'offloads_l' => round($offloadsIn, 4),
            'loads_l'    => round($loadsOut, 4),
            'net_l'      => round($net, 4),
        ];
    }
}