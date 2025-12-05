<?php

namespace Optima\DepotStock\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Optima\DepotStock\Models\Depot;
use Optima\DepotStock\Models\DepotReconDay;
use Optima\DepotStock\Models\Tank;
use Optima\DepotStock\Services\DepotReconService;
use Optima\DepotStock\Services\StrappingChartService;
use Optima\DepotStock\Services\VolumeCorrectionService;

class DepotReconController extends Controller
{
    public function __construct(
        protected DepotReconService $recon,
        protected StrappingChartService $strap,
        protected VolumeCorrectionService $vc
    ) {
    }

    public function index(Request $request): View
    {
            $date    = $this->resolveDate($request->input('date'));
            $depotId = $request->input('depot', 'all');

            $depots = Depot::orderBy('name')->get();

            // Tanks filtered by depot (or all)
            $tanksQuery = Tank::with(['depot', 'product'])
                ->orderBy('depot_id')
                ->orderBy('id');

            if ($depotId !== 'all') {
                $tanksQuery->where('depot_id', (int) $depotId);
            }

            $tanks = $tanksQuery->get();

            // Recon days keyed by tank_id, with dips eager loaded
            $daysByTank = DepotReconDay::with('dips')
                ->whereDate('date', $date->toDateString())
                ->whereIn('tank_id', $tanks->pluck('id'))
                ->get()
                ->keyBy('tank_id');

            // ----------------------------------------------------
            // Movement summary per tank (offloads / loads / net)
            // NOTE: adjust column names to match your schema.
            // ----------------------------------------------------
            $movementByTank = collect();

            if (class_exists(\Optima\DepotStock\Models\Offload::class)
                && class_exists(\Optima\DepotStock\Models\Load::class)) {

                try {
                    $tankIds = $tanks->pluck('id');

                    $offloadsByTank = \Optima\DepotStock\Models\Offload::query()
                        ->whereIn('tank_id', $tankIds)
                        ->whereDate('date', $date->toDateString())     // ⬅️ adjust date column if needed
                        ->get(['tank_id', 'delivered_20_l'])           // ⬅️ adjust volume column if needed
                        ->groupBy('tank_id')
                        ->map(fn ($rows) => (float) $rows->sum('delivered_20_l'));

                    $loadsByTank = \Optima\DepotStock\Models\Load::query()
                        ->whereIn('tank_id', $tankIds)
                        ->whereDate('date', $date->toDateString())     // ⬅️ adjust date column if needed
                        ->get(['tank_id', 'loaded_20_l'])              // ⬅️ adjust volume column if needed
                        ->groupBy('tank_id')
                        ->map(fn ($rows) => (float) $rows->sum('loaded_20_l'));

                    $movementByTank = $tankIds->mapWithKeys(function ($tankId) use ($offloadsByTank, $loadsByTank) {
                        $off  = $offloadsByTank[$tankId] ?? 0.0;
                        $load = $loadsByTank[$tankId]   ?? 0.0;

                        return [
                            $tankId => [
                                'offloads_l' => $off,
                                'loads_l'    => $load,
                                'net_l'      => $off - $load,
                            ],
                        ];
                    });
                } catch (\Throwable $e) {
                    $movementByTank = collect(); // fail-safe, UI will just show "—"
                }
            }

            return view('depot-stock::operations.daily-dips', [
                'date'           => $date,
                'depotId'        => $depotId,
                'depots'         => $depots,
                'tanks'          => $tanks,
                'daysByTank'     => $daysByTank,
                'movementByTank' => $movementByTank,
            ]);
        }

        /**
         * Save / update OPENING dip.
         * Manual litres: observed + @20°C – no auto VC.
         */
        public function storeOpening(Request $request)
        {
            $data = $request->validate([
                'tank_id'           => ['required', 'exists:tanks,id'],
                'date'              => ['required', 'date'],
                'volume_observed_l' => ['required', 'numeric', 'min:0'],
                'volume_20_l'       => ['required', 'numeric', 'min:0'],
                'dip_height_cm'     => ['nullable', 'numeric', 'min:0'],
                'temperature_c'     => ['nullable', 'numeric'],
                'density_kg_l'      => ['nullable', 'numeric'],
                'note'              => ['nullable', 'string'],
            ]);

            $tank = Tank::with('depot')->findOrFail((int) $data['tank_id']);
            $dayDate = $this->resolveDate($data['date']);

            // Use manual litres @20 directly
            $volume20 = (float) $data['volume_20_l'];

            $day = $this->recon->saveOpeningDip(
                $tank,
                $dayDate,
                (float) ($data['dip_height_cm'] ?? 0),
                (float) ($data['temperature_c'] ?? 0),
                (float) ($data['density_kg_l'] ?? 0),
                $volume20,
                optional($request->user())->id,
                $data['note'] ?? null
            );

            // Store observed litres on the dip row (new column)
            $observed = (float) $data['volume_observed_l'];
            $dip = $day->dips()
                ->where('type', 'opening')
                ->latest('id')
                ->first();

            if ($dip) {
                $dip->volume_observed_l = $observed;
                $dip->save();
            }

        // 🔁 ensure variance updates when opening is edited after closing
            $this->recalcVariance($day);

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'  => true,
                    'day' => $day->fresh('dips'),
                ]);
            }

            return back()->with('status', 'Opening dip saved.');
        }

    /**
     * Save / update CLOSING dip.
     * Manual litres: observed + @20°C – no auto VC.
     */
    public function storeClosing(Request $request)
    {
        $data = $request->validate([
            'tank_id'           => ['required', 'exists:tanks,id'],
            'date'              => ['required', 'date'],
            'volume_observed_l' => ['required', 'numeric', 'min:0'],
            'volume_20_l'       => ['required', 'numeric', 'min:0'],
            'dip_height_cm'     => ['nullable', 'numeric', 'min:0'],
            'temperature_c'     => ['nullable', 'numeric'],
            'density_kg_l'      => ['nullable', 'numeric'],
            'note'              => ['nullable', 'string'],
        ]);

        $tank = Tank::with('depot')->findOrFail((int) $data['tank_id']);
        $dayDate = $this->resolveDate($data['date']);

        $volume20 = (float) $data['volume_20_l'];

        $day = $this->recon->saveClosingDip(
            $tank,
            $dayDate,
            (float) ($data['dip_height_cm'] ?? 0),
            (float) ($data['temperature_c'] ?? 0),
            (float) ($data['density_kg_l'] ?? 0),
            $volume20,
            optional($request->user())->id,
            $data['note'] ?? null
        );

        $observed = (float) $data['volume_observed_l'];
        $dip = $day->dips()
            ->where('type', 'closing')
            ->latest('id')
            ->first();

        if ($dip) {
            $dip->volume_observed_l = $observed;
            $dip->save();
        }

        // 🔁 ensure variance updates when opening is edited after closing
        $this->recalcVariance($day);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'  => true,
                'day' => $day->fresh('dips'),
            ]);
        }

        return back()->with('status', 'Closing dip saved.');
    }

    /**
     * Lock the day for a given tank + date.
     */
    public function lockDay(Request $request, Depot $depot, string $date)
    {
        $dayDate = $this->resolveDate($date);
        $tankId  = (int) $request->input('tank_id');

        if (! $tankId) {
            return response()->json([
                'ok'     => false,
                'reason' => 'Missing tank_id',
            ], 422);
        }

        $day = DepotReconDay::whereDate('date', $dayDate->toDateString())
            ->where('tank_id', $tankId)
            ->firstOrFail();

        $this->recon->lockDay($day, optional($request->user())->id);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'  => true,
                'day' => $day->fresh('dips'),
            ]);
        }

        return back()->with('status', 'Day locked.');
    }

    /**
     * Recalculate variance for a recon day based on opening, expected and actual closing.
     */
   
    protected function recalcVariance(DepotReconDay $day): void
    {
        // make sure we have the latest values from saveOpening/ClosingDip
        $day->refresh();

        $opening  = $day->opening_l_20;
        $expected = $day->closing_expected_l_20 ?? $opening;
        $actual   = $day->closing_actual_l_20;

        if ($expected !== null && $actual !== null) {
            $variance = (float) $actual - (float) $expected;
            $pct      = $expected > 0 ? ($variance / (float) $expected) * 100 : null;

            $day->variance_l_20 = $variance;
            $day->variance_pct  = $pct;
            $day->save();
        }
    }

    // ------------------------------------------------
    // Helpers
    // ------------------------------------------------

    protected function resolveDate(?string $raw): Carbon
    {
        if (! $raw) {
            return Carbon::today();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            return Carbon::today();
        }
    }
}