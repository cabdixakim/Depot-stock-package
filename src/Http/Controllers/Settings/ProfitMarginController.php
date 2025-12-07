<?php

namespace Optima\DepotStock\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Optima\DepotStock\Models\ProfitMargin;

class ProfitMarginController extends Controller
{
    /**
     * (Optional page) Show a simple monthly grid to manage GLOBAL margins.
     * Per-client overrides can be added later; schema already supports it.
     */
    public function index(Request $r)
    {
        $monthsBack  = (int)($r->query('back', 6));
        $monthsAhead = (int)($r->query('ahead', 6));

        $start = now()->startOfMonth()->subMonths($monthsBack);
        $end   = now()->startOfMonth()->addMonths($monthsAhead);

        $margins = ProfitMargin::query()
            ->whereNull('client_id')
            ->whereBetween('effective_from', [$start, $end])
            ->orderBy('effective_from')
            ->get()
            ->keyBy(fn($pm) => $pm->effective_from->format('Y-m-01'));

        $months = [];
        $cur = $start->copy();
        while ($cur <= $end) {
            $months[] = $cur->format('Y-m-01');
            $cur->addMonth();
        }

        return view('depot-stock::settings.margins', compact('months', 'margins'));
    }

    /**
     * Bulk upsert (GLOBAL only)
     * Payload: { rows: [{month:"YYYY-MM", margin:12.34}, ...] }
     */
    public function save(Request $r)
    {
        $data = $r->validate([
            'rows'          => 'required|array',
            'rows.*.month'  => 'required|date_format:Y-m',
            'rows.*.margin' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rows'] as $row) {
                $monthDate = Carbon::createFromFormat('Y-m', $row['month'])->startOfMonth()->toDateString();
                ProfitMargin::updateOrCreate(
                    ['client_id' => null, 'effective_from' => $monthDate],
                    ['margin_per_litre' => (float)$row['margin']]
                );
            }
        });

        return response()->json(['ok' => true, 'message' => 'Margins saved']);
    }

    /**
     * Compact month→margin map for a window, with carry-forward.
     * Query: ?from=YYYY-MM-01&to=YYYY-MM-30
     * Returns: { ok:true, map: { "YYYY-MM": margin, ... } }
     */
    public function map(Request $r)
    {
        $from = Carbon::parse($r->query('from', now()->startOfMonth()));
        $to   = Carbon::parse($r->query('to', now()->endOfMonth()));

        $rows = ProfitMargin::query()
            ->whereNull('client_id')
            ->where('effective_from', '<=', $to->endOfMonth()->toDateString())
            ->orderBy('effective_from')
            ->get();

        // Build month list inside requested window
        $months = [];
        $cur = $from->copy()->startOfMonth();
        while ($cur <= $to) {
            $months[] = $cur->format('Y-m');
            $cur->addMonth();
        }

        // Map all rows and find last known value up to "from"
        $byMonth = [];
        $last = null;
        foreach ($rows as $pm) {
            $ym = $pm->effective_from->format('Y-m');
            $byMonth[$ym] = (float)$pm->margin_per_litre;
            if ($pm->effective_from <= $from) {
                $last = (float)$pm->margin_per_litre;
            }
        }

        // Carry forward
        $out = [];
        foreach ($months as $ym) {
            if (array_key_exists($ym, $byMonth)) {
                $last = $byMonth[$ym];
                $out[$ym] = $byMonth[$ym];
            } else {
                $out[$ym] = $last ?? 0.0;
            }
        }

        return response()->json(['ok' => true, 'map' => $out]);
    }

    /**
     * 👇 Convenience: get the CURRENT (carry-forward) global margin as a single number.
     * Returns: { ok:true, margin: 12.34, month: 'YYYY-MM' }
     */
    public function current(Request $r)
    {
        $asOf = Carbon::parse($r->query('as_of', now()));
        $row = ProfitMargin::query()
            ->whereNull('client_id')
            ->where('effective_from', '<=', $asOf->endOfMonth()->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        return response()->json([
            'ok'     => true,
            'month'  => ($row?->effective_from?->format('Y-m') ?? now()->format('Y-m')),
            'margin' => (float)($row->margin_per_litre ?? 0.0),
        ]);
    }

    /**
     * 👇 Convenience: set the margin for the CURRENT month (global).
     * Payload: { margin: 12.34 }
     */
    public function setCurrent(Request $r)
    {
        $data = $r->validate([
            'margin' => 'required|numeric|min:0'
        ]);

        $monthDate = now()->startOfMonth()->toDateString();

        $pm = ProfitMargin::updateOrCreate(
            ['client_id' => null, 'effective_from' => $monthDate],
            ['margin_per_litre' => (float)$data['margin']]
        );

        return response()->json([
            'ok'     => true,
            'month'  => $pm->effective_from->format('Y-m'),
            'margin' => (float)$pm->margin_per_litre,
            'message'=> 'Current month margin updated.',
        ]);
    }
}