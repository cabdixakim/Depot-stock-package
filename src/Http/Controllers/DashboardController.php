<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load as LoadTxn;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\Invoice;
use Optima\DepotStock\Models\Payment;
use Optima\DepotStock\Models\ProfitMargin;
use Optima\DepotStock\Models\DepotPoolEntry;
use Optima\DepotStock\Models\Depot;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $today = Carbon::today();

        // -------------------------------------------------
        // Quick presets vs manual From/To
        // -------------------------------------------------
        $userSetFrom = $r->filled('from');
        $userSetTo   = $r->filled('to');
        $preset      = $r->input('preset');

        $rangeFrom = null;   // window for metrics
        $rangeTo   = null;   // window for metrics
        $filterFrom = null;  // what appears inside date inputs (UI)
        $filterTo   = null;
        $activePreset = null;

        if ($userSetFrom || $userSetTo) {
            // Manual date range overrides any preset
            $rangeFrom  = $userSetFrom ? Carbon::parse($r->input('from'))->startOfDay() : null;
            $rangeTo    = $userSetTo   ? Carbon::parse($r->input('to'))->endOfDay()     : null;
            $filterFrom = $rangeFrom;
            $filterTo   = $rangeTo;
            $activePreset = null;
        } else {
            // Use quick presets
            if (!in_array($preset, ['all_time','this_month','this_year'], true)) {
                $preset = 'all_time';
            }
            $activePreset = $preset;

            switch ($preset) {
                case 'this_month':
                    $rangeFrom = $today->copy()->startOfMonth()->startOfDay();
                    $rangeTo   = $today->copy()->endOfDay();
                    break;

                case 'this_year':
                    $rangeFrom = $today->copy()->startOfYear()->startOfDay();
                    $rangeTo   = $today->copy()->endOfDay();
                    break;

                case 'all_time':
                default:
                    $rangeFrom = null;
                    $rangeTo   = null;
                    break;
            }

            // For presets, we keep the manual date inputs empty
            $filterFrom = null;
            $filterTo   = null;
        }

        $usingPreset = !$userSetFrom && !$userSetTo;

        // -------------------------------------------------
        // Depot scope
        // -------------------------------------------------
        $activeDepotId = session('depot.active_id'); // null => all
        $activeDepot   = $activeDepotId ? Depot::find($activeDepotId) : null;

        $byDepotMove = function ($q) use ($activeDepotId) {
            if ($activeDepotId) {
                $q->whereHas('tank', fn($t) => $t->where('depot_id', $activeDepotId));
            }
            return $q;
        };

        $byDepotPool = function ($q) use ($activeDepotId) {
            if ($activeDepotId) {
                $q->where('depot_id', $activeDepotId);
            }
            return $q;
        };

        // -------------------------------------------------
        // Helpers
        // -------------------------------------------------
        $between = function ($q, ?Carbon $from, ?Carbon $to) {
            return $q
                ->when($from, fn($qq) => $qq->whereDate('date','>=',$from->toDateString()))
                ->when($to,   fn($qq) => $qq->whereDate('date','<=',$to->toDateString()));
        };

        // -------------------------------------------------
        // Profit / L (global, current month base)
        // -------------------------------------------------
        $profitPerLitre = (float) (
            ProfitMargin::query()
                ->whereNull('client_id')
                ->where('effective_from', '<=', $today->copy()->startOfMonth()->toDateString())
                ->orderByDesc('effective_from')
                ->value('margin_per_litre') ?? 0
        );

        // -------------------------------------------------
        // Period metrics (Offloads / Loads / Adjustments) - WINDOW
        // -------------------------------------------------
        $offQ = $byDepotMove(Offload::whereNotNull('client_id'));
        $outQ = $byDepotMove(LoadTxn::whereNotNull('client_id'));
        $adjQ = $byDepotMove(Adjustment::whereNotNull('client_id'));

        $between($offQ, $rangeFrom, $rangeTo);
        $between($outQ, $rangeFrom, $rangeTo);
        $between($adjQ, $rangeFrom, $rangeTo);

        $periodOffloads = (float) $offQ->sum('delivered_20_l');
        $periodLoads    = (float) $outQ->sum('loaded_20_l');
        $periodAdjs     = (float) $adjQ->sum('amount_20_l');

        // -------------------------------------------------
        // Stock as of end-of-window (or today if no window)
        // -------------------------------------------------
        $endDate = $rangeTo
            ?: ($rangeFrom ?: $today->copy()->endOfDay());

        $stockOffQ = $byDepotMove(Offload::whereNotNull('client_id'));
        $stockOutQ = $byDepotMove(LoadTxn::whereNotNull('client_id'));
        $stockAdjQ = $byDepotMove(Adjustment::whereNotNull('client_id'));

        $stockOffQ->whereDate('date','<=',$endDate->toDateString());
        $stockOutQ->whereDate('date','<=',$endDate->toDateString());
        $stockAdjQ->whereDate('date','<=',$endDate->toDateString());

        $stockAsOfEnd = round(
            (float) $stockOffQ->sum('delivered_20_l')
            - (float) $stockOutQ->sum('loaded_20_l')
            + (float) $stockAdjQ->sum('amount_20_l'),
            3
        );

        $stockLabel = 'As of '.$endDate->format('d M Y');

        // -------------------------------------------------
        // Profit window (CURRENT MONTH by default on All Time)
        // -------------------------------------------------
        // Default profit window = selected range
        $profitFrom = $rangeFrom;
        $profitTo   = $rangeTo;

        // If user is on "All Time" (no manual dates, all_time preset),
        // profit should show current month by default.
        if ($usingPreset && ($activePreset === 'all_time')) {
            $profitFrom = $today->copy()->startOfMonth()->startOfDay();
            $profitTo   = $today->copy()->endOfDay();
        }

        $profitOffQ = $byDepotMove(Offload::whereNotNull('client_id'));
        $between($profitOffQ, $profitFrom, $profitTo);
        $profitOffloads = (float) $profitOffQ->sum('delivered_20_l');

        $profitAmount = round($profitPerLitre * $profitOffloads, 2);

        // -------------------------------------------------
        // Profit vs previous month (previous calendar month)
        // -------------------------------------------------
        $prevFrom = $today->copy()->subMonth()->startOfMonth();
        $prevTo   = $today->copy()->subMonth()->endOfMonth();

        $prevOffloads = (float) $byDepotMove(
            Offload::query()->whereNotNull('client_id')
        )->whereDate('date','>=',$prevFrom->toDateString())
         ->whereDate('date','<=',$prevTo->toDateString())
         ->sum('delivered_20_l');

        $profitPrev  = round($profitPerLitre * $prevOffloads, 2);
        $profitDelta = round($profitAmount - $profitPrev, 2);
        $profitPct   = $profitPrev != 0.0
            ? round(($profitDelta / abs($profitPrev)) * 100, 1)
            : ($profitAmount > 0 ? 100.0 : 0.0);

        // -------------------------------------------------
        // Depot pool (window + all-time)
        // -------------------------------------------------
        $poolIn  = (float) $byDepotPool(
                        DepotPoolEntry::query()->where('type','in')
                    )->when($rangeFrom || $rangeTo, fn($q)=>$between($q, $rangeFrom, $rangeTo))
                     ->sum('volume_20_l');

        $poolOut = (float) $byDepotPool(
                        DepotPoolEntry::query()->where('type','out')
                    )->when($rangeFrom || $rangeTo, fn($q)=>$between($q, $rangeFrom, $rangeTo))
                     ->sum('volume_20_l');

        $poolInAll  = (float) $byDepotPool(DepotPoolEntry::where('type','in'))->sum('volume_20_l');
        $poolOutAll = (float) $byDepotPool(DepotPoolEntry::where('type','out'))->sum('volume_20_l');
        $poolNow    = round($poolInAll - $poolOutAll, 3);

        // -------------------------------------------------
        // Counts
        // -------------------------------------------------
        $clientsCount    = Client::count();
        $openInvoices    = Invoice::whereIn('status', ['issued','partial'])->count();
        $overdueInvoices = Invoice::whereIn('status', ['issued','partial'])
                                  ->whereDate('date', '<', Carbon::today()->subDays(3))
                                  ->count();

        // -------------------------------------------------
        // Client snapshot (window movements + all-time stock)
        // -------------------------------------------------
        $clients = Client::select('id','name','code')->orderBy('name')->get();

        $invoiceSums = Invoice::select('client_id', DB::raw('SUM(total) as tot'))
            ->groupBy('client_id')->pluck('tot','client_id');

        $paymentSums = Payment::select('client_id', DB::raw('SUM(amount) as tot'))
            ->groupBy('client_id')->pluck('tot','client_id');

        $winOffByClient = $byDepotMove(
            Offload::select('client_id', DB::raw('SUM(delivered_20_l) as l'))
                ->whereNotNull('client_id')
        );
        $between($winOffByClient, $rangeFrom, $rangeTo);
        $winOffByClient = $winOffByClient->groupBy('client_id')->pluck('l','client_id');

        $winOutByClient = $byDepotMove(
            LoadTxn::select('client_id', DB::raw('SUM(loaded_20_l) as l'))
                ->whereNotNull('client_id')
        );
        $between($winOutByClient, $rangeFrom, $rangeTo);
        $winOutByClient = $winOutByClient->groupBy('client_id')->pluck('l','client_id');

        $winAdjByClient = $byDepotMove(
            Adjustment::select('client_id', DB::raw('SUM(amount_20_l) as l'))
                ->whereNotNull('client_id')
        );
        $between($winAdjByClient, $rangeFrom, $rangeTo);
        $winAdjByClient = $winAdjByClient->groupBy('client_id')->pluck('l','client_id');

        $inAllByClient  = $byDepotMove(
            Offload::select('client_id', DB::raw('SUM(delivered_20_l) as l'))
                ->whereNotNull('client_id')
        )->groupBy('client_id')->pluck('l','client_id');

        $outAllByClient = $byDepotMove(
            LoadTxn::select('client_id', DB::raw('SUM(loaded_20_l) as l'))
                ->whereNotNull('client_id')
        )->groupBy('client_id')->pluck('l','client_id');

        $adjAllByClient = $byDepotMove(
            Adjustment::select('client_id', DB::raw('SUM(amount_20_l) as l'))
                ->whereNotNull('client_id')
        )->groupBy('client_id')->pluck('l','client_id');

        $snapshot = [];
        foreach ($clients as $c) {
            $cid   = $c->id;
            $curIn = (float) ($inAllByClient[$cid]  ?? 0);
            $curOut= (float) ($outAllByClient[$cid] ?? 0);
            $curAdj= (float) ($adjAllByClient[$cid] ?? 0);
            $stock = round($curIn - $curOut + $curAdj, 3);

            $winIn  = (float) ($winOffByClient[$cid] ?? 0);
            $winOut = (float) ($winOutByClient[$cid] ?? 0);
            $winAdj = (float) ($winAdjByClient[$cid] ?? 0);

            $totInv = (float) ($invoiceSums[$cid] ?? 0);
            $totPay = (float) ($paymentSums[$cid] ?? 0);
            $balance= max(0, round($totInv - $totPay, 2));

            $snapshot[] = [
                'id'          => $cid,
                'name'        => $c->name,
                'code'        => $c->code,
                'litres_in'   => $winIn,
                'litres_out'  => $winOut,
                'litres_adj'  => $winAdj,
                'stock_net'   => $stock,
                'paid'        => $totPay,
                'balance'     => $balance,
            ];
        }

        // -------------------------------------------------
        // Window label for UI
        // -------------------------------------------------
        if ($usingPreset) {
            switch ($activePreset) {
                case 'this_month':
                    $labelMode = 'This Month';
                    break;
                case 'this_year':
                    $labelMode = 'This Year';
                    break;
                case 'all_time':
                default:
                    $labelMode = 'All Time';
                    break;
            }
        } else {
            if (!$rangeFrom && !$rangeTo) {
                $labelMode = 'All Time';
            } elseif ($rangeFrom && $rangeTo && $rangeFrom->isSameDay($rangeTo)) {
                $labelMode = $rangeFrom->format('d M Y');
            } else {
                $fromStr = $rangeFrom ? $rangeFrom->format('d M Y') : '…';
                $toStr   = $rangeTo   ? $rangeTo->format('d M Y')   : '…';
                $labelMode = $fromStr.' → '.$toStr;
            }
        }

        // Profit label: default "This Month" on All Time preset;
        // otherwise same as window label
        $profitLabel = ($usingPreset && $activePreset === 'all_time')
            ? 'This Month'
            : $labelMode;

        return view('depot-stock::dashboard', [
            'filter_from'        => $filterFrom,
            'filter_to'          => $filterTo,
            'label_mode'         => $labelMode,
            'preset'             => $activePreset,

            'clients_count'      => $clientsCount,
            'open_invoices'      => $openInvoices,
            'overdue_invoices'   => $overdueInvoices,

            'pool_in'            => $poolIn,
            'pool_out'           => $poolOut,
            'pool_now'           => $poolNow,

            'stock_window'       => $stockAsOfEnd,
            'stock_label'        => $stockLabel,

            'period_offloads'    => $periodOffloads,
            'period_loads'       => $periodLoads,

            'profit_per_litre'   => $profitPerLitre,
            'profit_amount'      => $profitAmount,
            'profit_prev'        => $profitPrev,
            'profit_delta'       => $profitDelta,
            'profit_pct'         => $profitPct,
            'profit_label'       => $profitLabel,

            'clientSnapshot'     => $snapshot,
            'currency'           => config('depot-stock.currency', 'USD'),

            'activeDepot'        => $activeDepot,
        ]);
    }
}