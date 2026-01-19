<?php

namespace Optima\DepotStock\Http\Controllers\Compliance;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Optima\DepotStock\Models\Clearance;
use Optima\DepotStock\Models\ClearanceEvent;
use Optima\DepotStock\Models\Client;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;  

class ClearanceController extends Controller
{
    public function index(Request $request)
    {
        $q = Clearance::with('client')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $q->where('client_id', $request->client_id);
        }

        return view('depot-stock::compliance.clearances.index', [
            'clearances' => $q->paginate(20),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('depot-stock::compliance.clearances.create', [
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'truck_number' => 'required|string',
            'trailer_number' => 'nullable|string',
            'is_bonded' => 'boolean',
            'loaded_20_l' => 'nullable|numeric',
            'invoice_number' => 'nullable|string',
            'delivery_note_number' => 'nullable|string',
            'border_point' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();
        $data['status'] = 'draft';

        $clearance = Clearance::create($data);

        ClearanceEvent::create([
            'clearance_id' => $clearance->id,
            'event' => 'created',
            'to_status' => 'draft',
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('depot.compliance.clearances.show', $clearance)
            ->with('success', 'Clearance created');
    }

    public function show(Clearance $clearance)
    {
        return view('depot-stock::compliance.clearances.show', [
            'clearance' => $clearance->load(['client', 'documents']),
            'events' => ClearanceEvent::where('clearance_id', $clearance->id)
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function submit(Clearance $clearance)
    {
        $this->transition($clearance, 'submitted');
        return back();
    }

    public function issueTr8(Request $request, Clearance $clearance)
    {
        $request->validate([
            'tr8_number' => 'required|string',
        ]);

        $clearance->update([
            'tr8_number' => $request->tr8_number,
            'tr8_issued_at' => now(),
        ]);

        $this->transition($clearance, 'tr8_issued');
        return back();
    }

    public function markArrived(Clearance $clearance)
    {
        $clearance->update(['arrived_at' => now()]);
        $this->transition($clearance, 'arrived');
        return back();
    }

    public function cancel(Clearance $clearance)
    {
        $this->transition($clearance, 'cancelled');
        return back();
    }

    protected function transition(Clearance $clearance, string $to)
    {
        ClearanceEvent::create([
            'clearance_id' => $clearance->id,
            'event' => $to,
            'from_status' => $clearance->status,
            'to_status' => $to,
            'user_id' => Auth::id(),
        ]);

        $clearance->update(['status' => $to]);
    }

    public function data(Request $request)
{
    $q = Clearance::query()
        ->leftJoin('clients', 'clients.id', '=', 'clearances.client_id');

    // latest event per clearance (for Updated By)
    $latestEventSub = DB::table('clearance_events')
        ->selectRaw('MAX(id) as id, clearance_id')
        ->groupBy('clearance_id');

    $q->leftJoinSub($latestEventSub, 'le', function ($join) {
            $join->on('le.clearance_id', '=', 'clearances.id');
        })
        ->leftJoin('clearance_events as ce', 'ce.id', '=', 'le.id')
        ->leftJoin('users as u', 'u.id', '=', 'ce.user_id');

    if ($request->filled('status')) {
        $q->where('clearances.status', $request->status);
    }

    if ($request->filled('client_id')) {
        $q->where('clearances.client_id', $request->client_id);
    }

    if ($request->filled('q')) {
        $term = trim($request->q);
        $q->where(function ($w) use ($term) {
            $w->where('clearances.truck_number', 'like', "%{$term}%")
              ->orWhere('clearances.trailer_number', 'like', "%{$term}%")
              ->orWhere('clearances.tr8_number', 'like', "%{$term}%")
              ->orWhere('clearances.invoice_number', 'like', "%{$term}%")
              ->orWhere('clearances.delivery_note_number', 'like', "%{$term}%");
        });
    }

    if ($request->filled('from')) {
        $q->whereDate('clearances.created_at', '>=', $request->from);
    }

    if ($request->filled('to')) {
        $q->whereDate('clearances.created_at', '<=', $request->to);
    }

    $rows = $q->orderByDesc('clearances.id')
        ->select([
            'clearances.id',
            'clearances.status',
            'clearances.truck_number',
            'clearances.trailer_number',
            'clearances.loaded_20_l',
            'clearances.tr8_number',
            'clearances.border_point',
            'clearances.submitted_at',
            'clearances.tr8_issued_at',
            'clearances.updated_at',
            DB::raw('clients.name as client_name'),
            DB::raw('COALESCE(u.name, u.email, "") as updated_by_name'),
        ])
        ->paginate(20);

    // Add age_human + format dates for display
    $rows->getCollection()->transform(function ($r) {
        $r->submitted_at = $r->submitted_at ? Carbon::parse($r->submitted_at)->format('Y-m-d H:i') : '';
        $r->tr8_issued_at = $r->tr8_issued_at ? Carbon::parse($r->tr8_issued_at)->format('Y-m-d H:i') : '';
        $r->age_human = $r->updated_at ? Carbon::parse($r->updated_at)->diffForHumans(null, true) : '';
        return $r;
    });

    return response()->json([
        'data' => $rows->items(),
        'meta' => [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ],
    ]);
}
}