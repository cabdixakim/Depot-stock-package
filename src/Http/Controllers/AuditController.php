<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Optima\DepotStock\Models\DepotReconDay;
use Optima\DepotStock\Models\DepotReconDip;
use Optima\DepotStock\Models\Dip;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load;
use Optima\DepotStock\Models\DepotPoolEntry;
use Optima\DepotStock\Models\Tank;
use Optima\DepotStock\Models\Depot;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditController extends Controller
{
    /**
     * Show flagged variances for audit.
     */
    public function index(Request $request)
    {
        $date = $request->input('date');
        $userId = $request->input('user');
        $type = $request->input('type');
        $start = $date ? Carbon::parse($date)->startOfDay() : Carbon::today()->subDays(30);
        $end = $date ? Carbon::parse($date)->endOfDay() : Carbon::today()->endOfDay();

        // Users for filter dropdown
        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        $users = $userModel::orderBy('name')->get();

        $entries = collect();

        // Dips (manual dips table)
        $dips = Dip::with(['tank.depot', 'createdBy'])
            ->whereBetween('date', [$start, $end])
            ->when($userId, fn($q) => $q->where('created_by_id', $userId))
            ->get()
            ->map(function($d) {
                return (object) [
                    'created_at' => $d->created_at ?? $d->date,
                    'user' => $d->createdBy,
                    'depot' => $d->tank->depot ?? null,
                    'tank' => $d->tank ?? null,
                    'type' => 'dip',
                    'details' => 'Dip: '.($d->volume_20 ? number_format($d->volume_20,0).' L @20°C' : '').($d->note ? ' ('.$d->note.')' : ''),
                ];
            });

        // Recon dips (opening/closing)
        $reconDips = DepotReconDip::with(['day.tank.depot', 'day.tank', 'createdBy'])
            ->whereBetween('created_at', [$start, $end])
            ->when($userId, fn($q) => $q->where('created_by_user_id', $userId))
            ->get()
            ->map(function($d) {
                $type = $d->type === 'opening' ? 'dip' : 'dip';
                return (object) [
                    'created_at' => $d->created_at,
                    'user' => $d->createdBy,
                    'depot' => $d->day->tank->depot ?? null,
                    'tank' => $d->day->tank ?? null,
                    'type' => $type,
                    'details' => ucfirst($d->type).' dip: '.($d->volume_20_l ? number_format($d->volume_20_l,0).' L @20°C' : '').($d->note ? ' ('.$d->note.')' : ''),
                ];
            });

        // Adjustments
        $adjustments = Adjustment::with(['tank.depot', 'tank', 'createdBy'])
            ->whereBetween('date', [$start, $end])
            ->when($userId, fn($q) => $q->where('created_by_user_id', $userId))
            ->get()
            ->map(function($a) {
                return (object) [
                    'created_at' => $a->created_at ?? $a->date,
                    'user' => $a->createdBy,
                    'depot' => $a->depot ?? ($a->tank->depot ?? null),
                    'tank' => $a->tank ?? null,
                    'type' => 'adjustment',
                    'details' => 'Adjustment: '.number_format($a->amount_20_l,0).' L'.($a->reason ? ' ('.$a->reason.')' : ''),
                ];
            });

        // Offloads
        $offloads = Offload::with(['tank.depot', 'tank', 'createdBy'])
            ->whereBetween('date', [$start, $end])
            ->when($userId, fn($q) => $q->where('created_by_user_id', $userId))
            ->get()
            ->map(function($o) {
                return (object) [
                    'created_at' => $o->created_at ?? $o->date,
                    'user' => $o->createdBy,
                    'depot' => $o->depot ?? ($o->tank->depot ?? null),
                    'tank' => $o->tank ?? null,
                    'type' => 'offload',
                    'details' => 'Offload: '.number_format($o->delivered_20_l,0).' L'.($o->note ? ' ('.$o->note.')' : ''),
                ];
            });

        // Loads
        $loads = Load::with(['tank.depot', 'tank', 'createdBy'])
            ->whereBetween('date', [$start, $end])
            ->when($userId, fn($q) => $q->where('created_by_user_id', $userId))
            ->get()
            ->map(function($l) {
                return (object) [
                    'created_at' => $l->created_at ?? $l->date,
                    'user' => $l->createdBy,
                    'depot' => $l->depot ?? ($l->tank->depot ?? null),
                    'tank' => $l->tank ?? null,
                    'type' => 'load',
                    'details' => 'Load: '.number_format($l->loaded_20_l,0).' L'.($l->note ? ' ('.$l->note.')' : ''),
                ];
            });

        // Depot pool entries (variance corrections)
        $poolEntries = DepotPoolEntry::with(['depot', 'product', 'user'])
            ->whereBetween('date', [$start, $end])
            ->when($userId, fn($q) => $q->where('created_by', $userId))
            ->get()
            ->map(function($e) {
                return (object) [
                    'created_at' => $e->created_at ?? $e->date,
                    'user' => $e->user,
                    'depot' => $e->depot,
                    'tank' => null,
                    'type' => $e->ref_type === 'allowance_correction' ? 'variance' : 'adjustment',
                    'details' => 'Depot pool: '.number_format($e->volume_20_l,0).' L ('.$e->ref_type.')'.($e->note ? ' ('.$e->note.')' : ''),
                ];
            });

        // Merge all
        $entries = $entries
            ->concat($dips)
            ->concat($reconDips)
            ->concat($adjustments)
            ->concat($offloads)
            ->concat($loads)
            ->concat($poolEntries);

        // Filter by type if requested
        if ($type) {
            $entries = $entries->filter(fn($e) => $e->type === $type);
        }

        // Sort by date/time desc
        $sorted = $entries->sortByDesc('created_at')->values();
        $page = $request->input('page', 1);
        $perPage = 30;
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $sorted->forPage($page, $perPage),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $auditEntries = $paged;

        return view('depot-stock::operations.audit', [
            'auditEntries' => $auditEntries,
            'users' => $users,
        ]);
    }
}
