<?php

namespace Optima\DepotStock\Http\Controllers\Compliance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Optima\DepotStock\Models\Clearance;
use Optima\DepotStock\Models\ClearanceEvent;
use Optima\DepotStock\Models\Client;

class ClearanceController extends Controller
{
    public function index(Request $request)
    {
        // Main list query (server-side view only needs clients + stats; Tabulator uses data())
        $q = Clearance::query();

        // Apply filters for stats baseline (ignore status filter for the pills, so they remain meaningful)
        if ($request->filled('client_id')) {
            $q->where('client_id', $request->client_id);
        }

        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(function ($w) use ($term) {
                $w->where('truck_number', 'like', "%{$term}%")
                  ->orWhere('trailer_number', 'like', "%{$term}%")
                  ->orWhere('tr8_number', 'like', "%{$term}%")
                  ->orWhere('invoice_number', 'like', "%{$term}%")
                  ->orWhere('delivery_note_number', 'like', "%{$term}%");
            });
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        // Stats
        $stats = [
            'total' => (clone $q)->count(),
            'draft' => (clone $q)->where('status', 'draft')->count(),
            'submitted' => (clone $q)->where('status', 'submitted')->count(),
            'tr8_issued' => (clone $q)->where('status', 'tr8_issued')->count(),
            'arrived' => (clone $q)->where('status', 'arrived')->count(),
            'cancelled' => (clone $q)->where('status', 'cancelled')->count(),
            'stuck_submitted' => 0,
            'stuck_tr8_issued' => 0,
            'missing_tr8_number' => 0,
            'missing_documents' => 0,
        ];

        // Attention metrics (safe assumptions)
        $submittedCutoff = now()->subHours(24);
        $issuedCutoff = now()->subHours(24);

        // Stuck in submitted: submitted for > 24h and no TR8 issued date
        $stats['stuck_submitted'] = (clone $q)
            ->where('status', 'submitted')
            ->where(function ($w) use ($submittedCutoff) {
                $w->whereNull('tr8_issued_at')
                  ->where(function ($w2) use ($submittedCutoff) {
                      $w2->whereNotNull('submitted_at')->where('submitted_at', '<', $submittedCutoff);
                  });
            })
            ->count();

        // TR8 issued but not arrived: tr8_issued for > 24h and no arrived_at
        $stats['stuck_tr8_issued'] = (clone $q)
            ->where('status', 'tr8_issued')
            ->whereNull('arrived_at')
            ->whereNotNull('tr8_issued_at')
            ->where('tr8_issued_at', '<', $issuedCutoff)
            ->count();

        // Missing TR8 number while tr8_issued
        $stats['missing_tr8_number'] = (clone $q)
            ->where('status', 'tr8_issued')
            ->where(function ($w) {
                $w->whereNull('tr8_number')->orWhere('tr8_number', '=', '');
            })
            ->count();

        // Missing documents (optional: only if we can safely detect a known table)
        // If you have a documents system, wire it here with Schema::hasTable checks.
        $stats['missing_documents'] = 0;

        return view('depot-stock::compliance.clearances.index', [
            'clients' => Client::orderBy('name')->get(),
            'stats' => $stats,
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

        // record submitted_at if column exists
        if (Schema::hasColumn('clearances', 'submitted_at')) {
            $clearance->update(['submitted_at' => now()]);
        }

        return back();
    }

    public function issueTr8(Request $request, Clearance $clearance)
    {
        $validated = $request->validate([
            'tr8_number' => 'required|string',
            'tr8_reference' => 'nullable|string',
            'tr8_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $update = [
            'tr8_number' => $validated['tr8_number'],
            'tr8_issued_at' => now(),
        ];

        // Only set reference if the column exists (prevents breaking)
        if (isset($validated['tr8_reference']) && Schema::hasColumn('clearances', 'tr8_reference')) {
            $update['tr8_reference'] = $validated['tr8_reference'];
        }

        $clearance->update($update);

        // Optional file handling: store file safely without breaking schema
        if ($request->hasFile('tr8_document')) {
            $file = $request->file('tr8_document');
            $path = $file->store('clearances/tr8', ['disk' => 'public']);

            // If you have a documents table, wire it here safely.
            // Example guarded insert (no crash if table/columns missing):
            if (Schema::hasTable('documents')) {
                $cols = Schema::getColumnListing('documents');

                // Only insert if documents table has minimum expected fields
                $canInsert = in_array('clearance_id', $cols) && in_array('path', $cols);
                if ($canInsert) {
                    $payload = [
                        'clearance_id' => $clearance->id,
                        'path' => $path,
                    ];

                    if (in_array('type', $cols)) $payload['type'] = 'tr8';
                    if (in_array('original_name', $cols)) $payload['original_name'] = $file->getClientOriginalName();
                    if (in_array('uploaded_by', $cols)) $payload['uploaded_by'] = Auth::id();
                    if (in_array('created_at', $cols)) $payload['created_at'] = now();
                    if (in_array('updated_at', $cols)) $payload['updated_at'] = now();

                    DB::table('documents')->insert($payload);
                }
            }
        }

        $this->transition($clearance, 'tr8_issued');
        return back();
    }

    public function markArrived(Clearance $clearance)
    {
        if (Schema::hasColumn('clearances', 'arrived_at')) {
            $clearance->update(['arrived_at' => now()]);
        }

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