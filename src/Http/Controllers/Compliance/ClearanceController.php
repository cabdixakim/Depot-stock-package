<?php

namespace Optima\DepotStock\Http\Controllers\Compliance;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Optima\DepotStock\Models\Clearance;
use Optima\DepotStock\Models\ClearanceEvent;
use Optima\DepotStock\Models\Client;

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
}