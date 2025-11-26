<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Tank;
use Optima\DepotStock\Models\Product;

use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load as LoadTxn;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\Transaction;
use Optima\DepotStock\Models\Role;

use Optima\DepotStock\Models\Invoice;
use Optima\DepotStock\Models\InvoiceItem;
use Optima\DepotStock\Models\ClientStorageCharge;

use App\Models\User;

// 🔹 Risk service
use Optima\DepotStock\Services\ClientRiskService;

class ClientController extends Controller
{
    /** Apply the same filters your page uses (from/to, tank_id, product_id) */
    protected function applyCommonFilters($query)
    {
        $req = request();
        return $query
            ->when($req->filled('from'), fn($q) => $q->whereDate('date', '>=', $req->date('from')))
            ->when($req->filled('to'),   fn($q) => $q->whereDate('date', '<=', $req->date('to')))
            ->when($req->filled('tank_id'),    fn($q) => $q->where('tank_id',    $req->input('tank_id')))
            ->when($req->filled('product_id'), fn($q) => $q->where('product_id', $req->input('product_id')));
    }

    /** OFFLOADS (IN) base query for a client */
    protected function offloadQuery(int $clientId)
    {
        if (class_exists(Offload::class)) {
            return Offload::query()
                ->where('client_id', $clientId)
                ->with(['tank.depot','tank.product']);
        }
        return Transaction::query()
            ->where('client_id', $clientId)
            ->where('type', 'IN')
            ->with(['tank.depot','tank.product']);
    }

    /** LOADS (OUT) base query for a client */
    protected function loadQuery(int $clientId)
    {
        if (class_exists(LoadTxn::class)) {
            return LoadTxn::query()
                ->where('client_id', $clientId)
                ->with(['tank.depot','tank.product']);
        }
        return Transaction::query()
            ->where('client_id', $clientId)
            ->where('type', 'OUT')
            ->with(['tank.depot','tank.product']);
    }

    /** ADJUSTMENTS base query for a client */
    protected function adjustmentQuery(int $clientId)
    {
        if (class_exists(Adjustment::class)) {
            return Adjustment::query()
                ->where('client_id', $clientId)
                ->with(['tank.depot','tank.product']);
        }
        return Transaction::query()
            ->where('client_id', $clientId)
            ->where('type', 'ADJ')
            ->with(['tank.depot','tank.product']);
    }

    /** Sum using first column that exists / has values */
    protected function safeSum($baseQuery, array $columns)
    {
        foreach ($columns as $col) {
            try {
                $q = clone $baseQuery;
                $sum = (float) $q->sum($col);
                if ($sum !== 0.0) return $sum;
            } catch (\Throwable $e) {
                // try next name
            }
        }
        return 0.0;
    }

    /**
     * Clients index + risk snapshots for the grid.
     */
    public function index(Request $request, ClientRiskService $riskService)
    {
        $q = trim((string) $request->query('q', ''));

        $clients = Client::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // 🔹 build risk snapshots using your service
        $riskSnapshots = $riskService->forCollection($clients);

        return view('depot-stock::clients.index', [
            'clients'       => $clients,
            'q'             => $q,
            'riskSnapshots' => $riskSnapshots,
        ]);
    }

    /** SHOW client page (with optional filters) */
    public function show(Request $request, Client $client)
    {
        // Filters from query string (optional)
        $filters = [
            'from'       => $request->query('from'),
            'to'         => $request->query('to'),
            'tank_id'    => $request->query('tank_id'),
            'product_id' => $request->query('product_id'),
        ];

        // Build base queries then apply filters consistently
        $applyFilters = function ($q) use ($filters) {
            return $q
                ->when($filters['from'], fn($qq, $v) => $qq->whereDate('date', '>=', $v))
                ->when($filters['to'], fn($qq, $v)   => $qq->whereDate('date', '<=', $v))
                ->when($filters['tank_id'], fn($qq, $v) => $qq->where('tank_id', $v))
                ->when($filters['product_id'], fn($qq, $v) => $qq->where('product_id', $v));
        };

        $inQ   = $applyFilters($this->offloadQuery($client->id));
        $outQ  = $applyFilters($this->loadQuery($client->id));
        $adjQ  = $applyFilters($this->adjustmentQuery($client->id));

        // Latest items for each stream (limit 8, not paginated)
        $incoming = (clone $inQ)->latest('date')->limit(8)->get();
        $outgoing = (clone $outQ)->latest('date')->limit(8)->get();
        $adjusts  = (clone $adjQ)->latest('date')->limit(8)->get();

        // Totals
        $totIn  = $this->safeSum($inQ,  ['delivered_20_l','delivered_20','loaded_20_l','volume_20_l','delivered_observed_l','observed_volume']);
        $totOut = $this->safeSum($outQ, ['loaded_20_l','delivered_20_l','delivered_20','volume_20_l','observed_volume','loaded_observed_l']);
        $totAdj = $this->safeSum($adjQ, ['amount_20_l','delivered_20_l','delivered_20','qty_20_l','qty_l','observed_volume']);

        // ===== Depot Loss (Shrinkage) + Truck Shortfall =====
        $offloadBase = $this->applyCommonFilters($this->offloadQuery($client->id));
        $loadBase    = $this->applyCommonFilters($this->loadQuery($client->id));

        // Depot Shrinkage (allowance) — authoritative if column exists, else 0.3%
        try {
            $depotShrink = (float) (clone $offloadBase)->sum('depot_allowance_20_l');
        } catch (\Throwable $e) {
            $depotShrink = 0.0;
        }
        if ($depotShrink == 0.0) {
            $depotShrink = (clone $offloadBase)->get()->sum(function ($r) {
                return (float)($r->delivered_20_l ?? 0) * 0.003;
            });
        }

        // Truck Shortfall (anything above delivered @20)
        $truckShort = (clone $offloadBase)->get()->sum(function ($r) {
            $deliv  = (float)($r->delivered_20_l ?? 0);
            $loaded = (float)($r->loaded_20_l ?? 0);
            if ($loaded == 0.0) $loaded = (float)($r->observed_20_l ?? 0);
            if ($loaded == 0.0) $loaded = (float)($r->loaded_observed_l ?? 0);
            return $loaded > $deliv ? ($loaded - $deliv) : 0.0;
        });

        $loss = [
            'depot_shrink' => round($depotShrink, 3),
            'truck_short'  => round($truckShort, 3),
            'total_loss'   => round($depotShrink + $truckShort, 3),
        ];

        $lossByProduct = (clone $offloadBase)
            ->with('product:id,name')
            ->get()
            ->groupBy('product_id')
            ->map(function ($rows) {
                $depot = $rows->sum(function ($r) {
                    if (isset($r->depot_allowance_20_l) && $r->depot_allowance_20_l !== null) {
                        return (float)$r->depot_allowance_20_l;
                    }
                    return (float)($r->delivered_20_l ?? 0) * 0.003;
                });
                $truck = $rows->sum(function ($r) {
                    $deliv  = (float)($r->delivered_20_l ?? 0);
                    $loaded = (float)($r->loaded_20_l ?? 0);
                    if ($loaded == 0.0) $loaded = (float)($r->observed_20_l ?? 0);
                    if ($loaded == 0.0) $loaded = (float)($r->loaded_observed_l ?? 0);
                    return $loaded > $deliv ? ($loaded - $deliv) : 0.0;
                });
                return [
                    'product_name' => optional($rows->first()->product)->name ?? '—',
                    'depot' => round($depot, 3),
                    'truck' => round($truck, 3),
                    'total' => round($depot + $truck, 3),
                ];
            })->values();

        // 🔹 CURRENT STOCK now accounts for depot allowance (shrinkage)
        $currentStock = $totIn - $totOut + $totAdj - $depotShrink;

        // Select options
        $tanks    = Tank::with(['depot','product'])->orderBy('id')->get();
        $products = Product::orderBy('name')->get();

        return view('depot-stock::clients.show', compact(
            'client','tanks','products','incoming','outgoing','adjusts',
            'totIn','totOut','totAdj','currentStock','filters','loss','lossByProduct'
        ));
    }

    // ======================================================
    // BASIC CRUD
    // ======================================================

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'          => ['required','string','max:50', Rule::unique('clients', 'code')],
            'name'          => ['required','string','max:255'],
            'email'         => ['nullable','email','max:255'],
            'phone'         => ['nullable','string','max:50'],
            'billing_terms' => ['nullable','string','max:255'],
        ]);

        DB::beginTransaction();

        try {
            // Create the client first
            $client = Client::create($data);

            $portalUser   = null;
            $plainPassword = null;

            // Auto-create portal user ONLY if we have an email
            if (!empty($client->email)) {
                $plainPassword = "password";

                $portalUser = User::create([
                    'name'      => $client->name,
                    'email'     => $client->email,
                    'password'  => Hash::make($plainPassword),
                    'client_id' => $client->id,   // 🔑 link user → client
                ]);

                // Attach "client" role
                $clientRoleId = Role::where('name', 'client')->value('id');
                if ($clientRoleId) {
                    $portalUser->roles()->attach($clientRoleId);
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($request->expectsJson()) {
            $row = view('depot-stock::clients._row', ['c' => $client])->render();

            return response()->json([
                'ok'          => true,
                'message'     => 'Client added successfully',
                'client'      => $client,
                'row'         => $row,
                'portal_user' => $portalUser ? [
                    'email'    => $portalUser->email,
                    'password' => $plainPassword, // only returned in this response
                ] : null,
            ]);
        }

        $msg = 'Client added successfully';
        if ($portalUser) {
            $msg .= ' (Portal user created for '.$portalUser->email.')';
        }

        return redirect()->route('depot.clients.index')->with('success', $msg);
    }

    public function update(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        $data = $request->validate([
            'code'          => ['required','string','max:50', Rule::unique('clients', 'code')->ignore($client->id)],
            'name'          => ['required','string','max:255'],
            'email'         => ['nullable','email','max:255'],
            'phone'         => ['nullable','string','max:50'],
            'billing_terms' => ['nullable','string','max:255'],
        ]);

        $client->update($data);

        if ($request->expectsJson()) {
            $row = view('depot-stock::clients._row', ['c' => $client])->render();

            return response()->json([
                'ok'      => true,
                'message' => 'Client updated',
                'client'  => $client,
                'row'     => $row,
            ]);
        }

        return back()->with('success', 'Client updated');
    }

    public function destroy($id)
    {
        $client = Client::findOrFail($id);
        $client->delete();

        return response()->json(['ok' => true, 'message' => 'Client removed']);
    }

    // ======================================================
    // LOCK / UNLOCK LOADS & OFFLOADS
    // ======================================================

    /**
     * POST /depot/clients/{client}/lock
     * Route name: depot.clients.lock
     */
    public function updateLock(Request $request, Client $client)
    {
        $data = $request->validate([
            'can_load'    => 'sometimes|boolean',
            'can_offload' => 'sometimes|boolean',
        ]);

        if (array_key_exists('can_load', $data)) {
            $client->can_load = (bool) $data['can_load'];
        }

        if (array_key_exists('can_offload', $data)) {
            $client->can_offload = (bool) $data['can_offload'];
        }

        $client->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Client lock settings updated.',
                'client'  => [
                    'id'         => $client->id,
                    'can_load'   => $client->can_load,
                    'can_offload'=> $client->can_offload,
                ],
            ]);
        }

        return back()->with('success', 'Client lock settings updated.');
    }

    // ======================================================
    // STORAGE CHARGE + GRACE
    // ======================================================

    /**
     * Called from the index page when you click "Charge storage".
     * POST /depot/clients/{client}/storage-charge
     * Route name: depot.clients.storage.charge
     *
     * Front-end sends:
     *  - idle_litres     (number)
     *  - rate_per_1000   (number, per 1000 L per month)
     *  - months          (int)
     *  - expected_amount (number)  ← just for reference, we recompute anyway
     */
    public function storeStorageCharge(Request $request, Client $client)
    {
        // validate what the modal actually sends
        $data = $request->validate([
            'idle_litres'     => 'required|numeric|min:0.01',
            'rate_per_1000'   => 'required|numeric|min:0',
            'months'          => 'nullable|integer|min:1',
            'expected_amount' => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $idleLitres   = (float) $data['idle_litres'];
        $ratePer1000  = (float) $data['rate_per_1000']; // USD per 1000 L per month
        $months       = (int) ($data['months'] ?? 1);
        if ($months <= 0) $months = 1;

        $ratePerLitre = $ratePer1000 / 1000.0;

        // total = litres/1000 * rate_per_1000 * months
        $amount = round(($idleLitres / 1000.0) * $ratePer1000 * $months, 2);
        $currency = 'USD';

        try {
            DB::beginTransaction();

            // Storage window: simple 30 days back from today
            $today    = Carbon::today();
            $fromDate = $today->copy()->subDays(30);
            $toDate   = $today->copy();

            // === 1) Create INVOICE ===
            $storageNumber = 'STG-' . now()->format('Ymd-His');

            /** @var Invoice $invoice */
            $invoice = Invoice::create([
                'client_id' => $client->id,
                'number'    => $storageNumber,
                'date'      => $toDate->toDateString(),
                'status'    => 'draft',
                'currency'  => $currency,
                'total'     => $amount, // required (no default in DB)
                'notes'     => 'Storage invoice for idle stock created from clients dashboard.',
            ]);

            // === 2) Create INVOICE ITEM ===
            InvoiceItem::create([
                'invoice_id'     => $invoice->id,
                'client_id'      => $client->id,
                'source_type'    => 'storage',  // must exist in enum
                'source_id'      => 0,
                'date'           => $toDate->toDateString(),
                'description'    => sprintf(
                    'Storage for idle stock (%s L) for %d month(s), %s to %s',
                    number_format($idleLitres, 0),
                    $months,
                    $fromDate->format('d M Y'),
                    $toDate->format('d M Y')
                ),
                'litres'         => $idleLitres,
                'rate_per_litre' => $ratePerLitre,
                'amount'         => $amount,
                'meta'           => json_encode([
                    'origin'        => 'client_index_storage',
                    'idle_litres'   => $idleLitres,
                    'rate_per_1000' => $ratePer1000,
                    'months'        => $months,
                ]),
            ]);

            // Recalculate invoice totals if method exists
            if (method_exists($invoice, 'recalculateTotals')) {
                $invoice->refresh();
                $invoice->recalculateTotals();
            }

            // === 3) Create CLIENT STORAGE RECORD ===
            ClientStorageCharge::create([
                'client_id'        => $client->id,
                'from_date'        => $fromDate->toDateString(),
                'to_date'          => $toDate->toDateString(),
                'cleared_litres'   => 0,               // will be filled when payment is done
                'uncleared_litres' => $idleLitres,
                'total_litres'     => $idleLitres,
                'fee_amount'       => $amount,
                'currency'         => $currency,
                'notes'            => $data['notes']
                    ?? 'Auto-created from clients dashboard for idle storage.',
                'invoice_id'       => $invoice->id,
                // 'paid_at' remains null until invoice is paid
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => true,
                    'message' => 'Storage invoice created for idle stock.',
                    'data'    => [
                        'invoice_id' => $invoice->id,
                        'amount'     => $amount,
                    ],
                ]);
            }

            return back()->with('success', 'Storage invoice created for idle stock.');
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Could not create storage invoice: ' . $e->getMessage(),
                ], 500);
            }

            throw $e;
        }
    }

    /**
     * Dummy “extend grace” handler for now – you can wire real logic later.
     * POST /depot/clients/{client}/storage-extend
     * Route name: depot.clients.storage.extend
     */
    public function extendStorageGrace(Request $request, Client $client)
    {
        // For now we simply acknowledge; you can later add a per-client grace flag.
        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Grace period flagged for this client (no structural change yet).',
            ]);
        }

        return back()->with('success', 'Grace period flagged for this client.');
    }
}