<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Optima\DepotStock\Models\DepotPoolEntry as DPE;
use Optima\DepotStock\Models\Product;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Depot;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\Tank;

class DepotPoolController extends Controller
{
    /**
     * Show Depot Pool summary + breakdown based on immutable ledger (depot_pool_entries).
     */
    public function index(Request $r)
    {
        $from = $r->filled('from') ? Carbon::parse($r->input('from'))->startOfDay()->toDateString() : null;
        $to   = $r->filled('to')   ? Carbon::parse($r->input('to'))->endOfDay()->toDateString()     : null;

        $between = fn($q)=> $q->when($from, fn($qq)=>$qq->whereDate('date','>=',$from))
                              ->when($to,   fn($qq)=>$qq->whereDate('date','<=',$to));

        // Respect active depot filter (top selector). null => all depots.
        $activeDepotId = session('depot.active_id');

        $byDepot = function ($q) use ($activeDepotId) {
            return $q->when($activeDepotId, fn($qq) => $qq->where('depot_id', $activeDepotId));
        };

        // Window totals (scoped by depot if selected)
        $win = $byDepot(
    DPE::query()
      ->when($from || $to, fn($q)=>$between($q))
)->select([
        DB::raw("SUM(CASE WHEN type = 'in'  THEN volume_20_l ELSE 0 END) AS win_in"),
        DB::raw("SUM(CASE WHEN type = 'out' THEN volume_20_l ELSE 0 END) AS win_out"),
        DB::raw("SUM(CASE WHEN ref_type = '".DPE::REF_ALLOWANCE."'          THEN volume_20_l ELSE 0 END) AS in_allowance"),
        DB::raw("SUM(CASE WHEN ref_type = '".DPE::REF_ALLOWANCE_CORR."'     THEN volume_20_l ELSE 0 END) AS corr_total"),
        DB::raw("SUM(CASE WHEN ref_type = '".DPE::REF_ALLOWANCE_REVERSAL."' THEN volume_20_l ELSE 0 END) AS rev_total"),
        DB::raw("SUM(CASE WHEN ref_type = '".DPE::REF_TRANSFER."'           THEN volume_20_l ELSE 0 END) AS out_transfer"),
        DB::raw("SUM(CASE WHEN ref_type = '".DPE::REF_SELL."'               THEN volume_20_l ELSE 0 END) AS out_sell"),
        // 💰 total sales amount (uses 1.1 if unit_price is NULL)
        DB::raw("SUM(CASE WHEN ref_type = '".DPE::REF_SELL."'
                          THEN volume_20_l * COALESCE(unit_price, 1.1)
                          ELSE 0 END) AS out_sell_amount"),
    ])->first();

        $total_in   = (float) ($win->win_in       ?? 0);
        $total_out  = (float) ($win->win_out      ?? 0);
        $in_allow   = (float) ($win->in_allowance ?? 0);
        $corr_total = (float) ($win->corr_total   ?? 0);
        $rev_total  = (float) ($win->rev_total    ?? 0);
        $out_transfer = (float) ($win->out_transfer ?? 0);
        $out_sell     = (float) ($win->out_sell     ?? 0);
        $out_sell_amount = (float) ($win->out_sell_amount ?? 0);

        // Current stock (all-time), scoped by depot if selected
        $stock_now = (float) $byDepot(
            DPE::query()
        )->selectRaw("
                COALESCE(SUM(CASE WHEN type='in'  THEN volume_20_l ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN type='out' THEN volume_20_l ELSE 0 END),0)
              AS cur
            ")
         ->value('cur') ?? 0.0;

        $label = ($from || $to)
            ? trim(($from ?: '…').' → '.($to ?: '…'))
            : 'All time';

        // Canonical breakdown pieces for the view
        $in_allowance = $in_allow;
        $corr_total_f = $corr_total;
        $rev_total_f  = $rev_total;

        $in_offloads = $in_allowance;
        $pos_allow   = max(0, $corr_total_f);
        $neg_allow   = max(0, -$corr_total_f);

        $pos_dip   = 0.0;
        $pos_other = 0.0;

        $neg_dip   = 0.0;
        $neg_other = $rev_total_f;

        $out_transfer_l = $out_transfer;
        $out_sell_l     = $out_sell;

        $out_loads = $out_transfer_l + $out_sell_l + max(0, -$corr_total_f) + $rev_total_f;

        // Dropdown data
        $products = Product::select('id','name')->orderBy('name')->get();
        $clients  = Client::select('id','name')->orderBy('name')->get();
        $depots   = Depot::select('id','name')->orderBy('name')->get();

        // For header chip: name of active depot (or null => all)
        $activeDepotName = $activeDepotId
            ? optional($depots->firstWhere('id', $activeDepotId))->name
            : null;

        return view('depot-stock::pool.index', [
            'from'        => $from,
            'to'          => $to,
            'label'       => $label,

            'total_in'    => $total_in,
            'total_out'   => $total_out,
            'stock_now'   => round($stock_now, 3),

            // detailed breakdown
            'in_offloads' => $in_offloads,
            'pos_allow'   => $pos_allow,
            'pos_dip'     => $pos_dip,
            'pos_other'   => $pos_other,
            'out_loads'   => $out_loads,
            'neg_allow'   => $neg_allow,
            'neg_dip'     => $neg_dip,
            'neg_other'   => $neg_other,

            // window transfers / sales (litres)
            'pool_transfer_l' => $out_transfer_l,
            'pool_sell_l'     => $out_sell_l,
            'pool_sell_amount' => $out_sell_amount,

            'products'    => $products,
            'clients'     => $clients,
            'depots'      => $depots,

            'activeDepotName' => $activeDepotName,
        ]);
    }

    /**
     * Compute available pool balance for a specific (depot, product).
     */
    protected function availableFor(int $depotId, int $productId): float
    {
        return (float) DPE::query()
            ->where('depot_id', $depotId)
            ->where('product_id', $productId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type='in'  THEN volume_20_l ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN type='out' THEN volume_20_l ELSE 0 END),0)
              AS bal
            ")
            ->value('bal') ?? 0.0;
    }

    /** POST /depot/pool/transfer */
    public function transfer(Request $r)
    {
        $data = $r->validate([
            'date'        => 'required|date',
            'volume_20_l' => 'required|numeric|min:0.001',
            'product_id'  => 'required|integer|exists:products,id',
            'client_id'   => 'required|integer|exists:clients,id',
            'depot_id'    => 'required|integer|exists:depots,id',
            'note'        => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($data) {
            // 1) Check available by depot + product
            $available = $this->availableFor((int) $data['depot_id'], (int) $data['product_id']);

            if ($available < (float) $data['volume_20_l']) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Insufficient pool stock for this depot & product. Available: '.number_format($available, 3).' L',
                ], 422);
            }

            // 2) Resolve a tank for this depot + product (must exist)
            $tank = Tank::query()
                ->where('depot_id', $data['depot_id'])
                ->where('product_id', $data['product_id'])
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'active');
                })
                ->orderBy('id')
                ->first();

            if (! $tank) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No active tank configured for this depot & product. Please create a tank first.',
                ], 422);
            }

            // 3) Create pool OUT entry (tracks depletion + provenance)
            $poolEntry = DPE::create([
                'depot_id'    => $data['depot_id'],
                'product_id'  => $data['product_id'],
                'date'        => $data['date'],
                'type'        => DPE::TYPE_OUT,
                'volume_20_l' => $data['volume_20_l'],
                'ref_type'    => DPE::REF_TRANSFER,
                'ref_id'      => $data['client_id'], // store client id for provenance
                'note'        => $data['note'] ?? null,
                'created_by'  => auth()->id(),
            ]);

            // 4) Mirror into client stock via a positive adjustment
            Adjustment::create([
                'type'              => 'positive',
                'is_billable'       => 0,
                'date'              => $data['date'],
                'client_id'         => $data['client_id'],
                'truck_plate'       => null,
                'trailer_plate'     => null,
                'depot_id'          => $data['depot_id'],
                'tank_id'           => $tank->id,
                'product_id'        => $data['product_id'],
                'amount_20_l'       => $data['volume_20_l'],
                'billed_invoice_id' => null,
                'reason'            => 'Depot pool transfer',
                'reference'         => 'POOL TRANSFER #'.$poolEntry->id,
                'note'              => $data['note'] ?? null,
            ]);

            return response()->json([
                'ok'      => true,
                'message' => 'Transfer recorded.',
            ]);
        });
    }

    /** POST /depot/pool/sell */
 public function sell(Request $r)
{
    $data = $r->validate([
        'date'        => 'required|date',
        'volume_20_l' => 'required|numeric|min:0.001',
        'product_id'  => 'required|integer|exists:products,id',
        'depot_id'    => 'required|integer|exists:depots,id',
        'unit_price'  => 'nullable|numeric|min:0',
        'reference'   => 'nullable|string|max:255',
    ]);

    $available = $this->availableFor((int)$data['depot_id'], (int)$data['product_id']);
    if ($available < (float)$data['volume_20_l']) {
        return response()->json([
            'ok' => false,
            'message' => 'Insufficient pool stock for this depot & product. Available: '.number_format($available,3).' L',
        ], 422);
    }

    // 🔑 Use provided price or default to 1.1
    $price = isset($data['unit_price']) && $data['unit_price'] !== ''
        ? (float) $data['unit_price']
        : 1.1;

    $noteParts = [];
    if (!empty($data['reference'])) $noteParts[] = $data['reference'];
    $noteParts[] = 'Unit: '.config('depot-stock.currency','USD').' '.number_format($price,2);
    $note = implode(' · ', $noteParts);

    DPE::create([
        'depot_id'     => $data['depot_id'],
        'product_id'   => $data['product_id'],
        'date'         => $data['date'],
        'type'         => 'out',
        'volume_20_l'  => $data['volume_20_l'],
        'unit_price'   => $price,          // 👈 store price
        'ref_type'     => DPE::REF_SELL,
        'ref_id'       => 0,
        'note'         => $note,
        'created_by'   => auth()->id(),
    ]);

    return response()->json([
        'ok' => true,
        'message' => 'Sell recorded.',
    ]);
}
}