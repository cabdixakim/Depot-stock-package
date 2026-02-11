<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Optima\DepotStock\Models\DepotReconDay;
use Carbon\Carbon;

class AuditController extends Controller
{
    /**
     * Show flagged variances for audit.
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->subDays(30));
        $endDate   = $request->input('end_date', Carbon::today());

        $flaggedDays = DepotReconDay::with(['tank.depot', 'tank.product'])
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('opening_variance_flag', true)
                  ->orWhereRaw('ABS(variance_pct) > ?', [config('depot-stock.closing_variance_tolerance_pct', 0.003) * 100]);
            })
            ->orderBy('date', 'desc')
            ->get();

        // Add expected opening for display
        foreach ($flaggedDays as $day) {
            $prevDay = DepotReconDay::where('tank_id', $day->tank_id)
                ->whereDate('date', Carbon::parse($day->date)->subDay()->toDateString())
                ->first();
            $day->expected_opening_l_20 = $prevDay && $prevDay->closing_actual_l_20 !== null
                ? (float) $prevDay->closing_actual_l_20
                : null;
        }

        return view('depot-stock::operations.audit', [
            'flaggedDays' => $flaggedDays,
        ]);
    }
}
