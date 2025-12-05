<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Optima\DepotStock\Models\DepotReconDay;
use Optima\DepotStock\Models\Depot;
use Optima\DepotStock\Models\Tank;

class DepotOperationsController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $activeDepotId = session('depot.active_id');

        // Base scoped query (respect active depot if set)
        $baseQuery = DepotReconDay::query()
            ->with(['tank.depot', 'tank.product', 'createdBy', 'checkedBy']);

        if ($activeDepotId) {
            $baseQuery->whereHas('tank', function ($q) use ($activeDepotId) {
                $q->where('depot_id', $activeDepotId);
            });
        }

        // Today slice
        $todayQuery = (clone $baseQuery)->whereDate('date', $today);
        $todayDays  = $todayQuery->get();

        // 1) Tanks reconciled today (opening + closing actual present)
        $tanksReconciledToday = $todayDays
            ->filter(function (DepotReconDay $day) {
                return !is_null($day->opening_l_20) && !is_null($day->closing_actual_l_20);
            })
            ->count();

        // 2) Variance alerts (above tolerance)
        $varianceTolerancePct = 0.3; // you can move this to config later
        $varianceAlertsToday = $todayDays
            ->filter(function (DepotReconDay $day) use ($varianceTolerancePct) {
                if (is_null($day->variance_pct)) {
                    return false;
                }
                return abs((float) $day->variance_pct) > $varianceTolerancePct;
            })
            ->count();

        // 3) "Dips captured" → number of recon-day rows for today (one per tank/day)
        $dipsCapturedToday = $todayDays->count();

        // 4) Operator activity → distinct creators on today’s records
        $operatorsToday = $todayDays
            ->pluck('createdBy.name')
            ->filter()
            ->unique()
            ->count();

        // Recent recon days (for the right-hand panel)
        $recentDays = (clone $baseQuery)
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        return view('depot-stock::operations.dashboard', [
            'tanksReconciledToday' => $tanksReconciledToday,
            'varianceAlertsToday'  => $varianceAlertsToday,
            'dipsCapturedToday'    => $dipsCapturedToday,
            'operatorsToday'       => $operatorsToday,
            'recentDays'           => $recentDays,
            'varianceTolerancePct' => $varianceTolerancePct,
        ]);
    }

    public function dipsHistory(Request $request)
    {
        // ----- Date range (proper fallback handling) -----
        $fromInput = $request->input('from');
        $toInput   = $request->input('to');

        $from = $fromInput
            ? Carbon::parse($fromInput)
            : Carbon::now()->subMonth();

        $to = $toInput
            ? Carbon::parse($toInput)
            : Carbon::now();

        // ----- Filters -----
        $depotId = $request->input('depot', 'all');
        $tankId  = $request->input('tank', 'all');

        // ----- Base query -----
        $query = DepotReconDay::query()
            ->with(['tank.depot', 'tank.product', 'createdBy', 'checkedBy'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($depotId !== 'all') {
            $query->whereHas('tank', function ($q) use ($depotId) {
                $q->where('depot_id', $depotId);
            });
        }

        if ($tankId !== 'all') {
            $query->where('tank_id', $tankId);
        }

        $days = $query->orderByDesc('date')->get();

        // ----- Tabulator rows -----
        $rows = $days->map(function (DepotReconDay $day) {
            $tank  = $day->tank;
            $depot = $tank?->depot;
            $prod  = $tank?->product;

            return [
                'id'          => $day->id,
                'date'        => $day->date instanceof Carbon
                                    ? $day->date->toDateString()
                                    : (string) $day->date,
                'depot'       => $depot?->name,
                'tank_label'  => $tank ? 'T' . $tank->id : null,
                'product'     => $prod?->name,

                'opening_l_20'          => (float) ($day->opening_l_20 ?? 0),
                'closing_expected_l_20' => (float) ($day->closing_expected_l_20 ?? 0),
                'closing_actual_l_20'   => (float) ($day->closing_actual_l_20 ?? 0),

                'offloads_l' => (float) ($day->offloads_l ?? 0),
                'loads_l'    => (float) ($day->loads_l ?? 0),
                'net_l'      => (float) ($day->net_l ?? 0),

                'variance_l_20' => (float) ($day->variance_l_20 ?? 0),
                'variance_pct'  => (float) ($day->variance_pct ?? 0),

                'status_label' => $day->status,

                'recorded_by'  => $day->createdBy?->name,
                'locked_by'    => $day->checkedBy?->name,
            ];
        });

        // ----- View -----
        return view('depot-stock::operations.dips-history', [
            'rows'   => $rows,
            'depots' => Depot::orderBy('name')->get(),
            'tanks'  => Tank::with(['depot', 'product'])->get(),

            'dateFrom'        => $from->toDateString(),
            'dateTo'          => $to->toDateString(),
            'selectedDepotId' => $depotId,
            'selectedTankId'  => $tankId,
        ]);
    }
}