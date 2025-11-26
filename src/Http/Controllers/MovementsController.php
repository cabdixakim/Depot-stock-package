<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load as LoadTxn;
use Optima\DepotStock\Models\DepotPoolEntry as DPE;

class MovementsController extends Controller
{
    /** Grid data for Offloads / Loads (includes `id`) */
    public function data(Request $request, Client $client)
    {
        $kind = $request->query('kind', 'offloads'); // 'offloads' | 'loads'

        $applyFilters = function ($q) use ($request) {
            return $q
                ->when($request->filled('from'), fn($qq) => $qq->whereDate('date', '>=', $request->date('from')))
                ->when($request->filled('to'),   fn($qq) => $qq->whereDate('date', '<=', $request->date('to')))
                ->when($request->filled('tank_id'),    fn($qq) => $qq->where('tank_id', (int)$request->input('tank_id')))
                ->when($request->filled('product_id'), fn($qq) => $qq->where('product_id', (int)$request->input('product_id')));
        };

        if ($kind === 'loads') {
            $rows = $applyFilters(
                LoadTxn::query()
                    ->where('client_id', $client->id)
                    ->with(['tank.depot','tank.product','createdBy'])
            )
            ->orderBy('date','desc')
            ->get()
            ->map(function ($r) {
                // ---- safe date formatting (handles Carbon or string) ----
                $rawDate = $r->date ?? null;
                if ($rawDate instanceof \Carbon\CarbonInterface) {
                    $dateStr = $rawDate->format('Y-m-d');
                } elseif (!empty($rawDate)) {
                    $dateStr = Carbon::parse($rawDate)->format('Y-m-d');
                } else {
                    $dateStr = null;
                }

                $creator = $r->createdBy; // may be null

                return [
                    'id'                => $r->id,
                    'date'              => $dateStr,
                    'depot'             => optional($r->tank->depot)->name,
                    'product'           => optional($r->tank->product)->name,
                    'tank'              => $r->tank_id ? ('T#'.$r->tank_id) : null,
                    'loaded_20_l'       => (float)($r->loaded_20_l ?? 0),
                    'temperature_c'     => (float)($r->temperature_c ?? 0),
                    'density_kg_l'      => (float)($r->density_kg_l ?? 0),
                    'truck_plate'       => $r->truck_plate,
                    'trailer_plate'     => $r->trailer_plate,
                    'reference'         => $r->reference,
                    'note'              => $r->note,

                    // 🔹 creator info for Tabulator
                    'created_by_id'     => $r->created_by,
                    'created_by_name'   => optional($creator)->name,
                    'created_by_email'  => optional($creator)->email,
                    'created_at'        => $r->created_at?->toDateTimeString(),
                ];
            })->values();

            return response()->json(['rows' => $rows]);
        }

        // default: offloads
        $rows = $applyFilters(
            Offload::query()
                ->where('client_id', $client->id)
                ->with(['tank.depot','tank.product','createdBy'])
        )
        ->orderBy('date','desc')
        ->get()
        ->map(function ($r) {
            // ---- safe date formatting (handles Carbon or string) ----
            $rawDate = $r->date ?? null;
            if ($rawDate instanceof \Carbon\CarbonInterface) {
                $dateStr = $rawDate->format('Y-m-d');
            } elseif (!empty($rawDate)) {
                $dateStr = Carbon::parse($rawDate)->format('Y-m-d');
            } else {
                $dateStr = null;
            }

            $delivered = (float)($r->delivered_20_l ?? 0);
            $loadedDoc = (float)($r->loaded_observed_l ?? 0);
            $short     = max($loadedDoc - $delivered, 0);
            $allow     = (float)($r->depot_allowance_20_l ?? ($delivered * 0.003));

            $creator = $r->createdBy; // may be null

            return [
                'id'                   => $r->id,
                'date'                 => $dateStr,
                'depot'                => optional($r->tank->depot)->name,
                'product'              => optional($r->tank->product)->name,
                'tank'                 => $r->tank_id ? ('T#'.$r->tank_id) : null,

                // Order in UI: Loaded -> Observed -> Delivered
                'loaded_observed_l'    => $loadedDoc,
                'delivered_observed_l' => (float)($r->delivered_observed_l ?? 0),
                'delivered_20_l'       => $delivered,

                'shortfall_20_l'       => $short,
                'depot_allowance_20_l' => $allow,
                'temperature_c'        => (float)($r->temperature_c ?? 0),
                'density_kg_l'         => (float)($r->density_kg_l ?? 0),
                'truck_plate'          => $r->truck_plate,
                'trailer_plate'        => $r->trailer_plate,
                'reference'            => $r->reference,
                'note'                 => $r->note,
                'billed_invoice_id'    => $r->billed_invoice_id,

                // 🔹 creator info for Tabulator
                'created_by_id'        => $r->created_by,
                'created_by_name'      => optional($creator)->name,
                'created_by_email'     => optional($creator)->email,
                'created_at'           => $r->created_at?->toDateTimeString(),
            ];
        })->values();

        return response()->json(['rows' => $rows]);
    }

    /** Batch save from Tabulator (edits only) */
    public function save(Request $request, Client $client)
    {
        $payload = $request->validate([
            'kind'   => ['required', Rule::in(['offloads','loads'])],
            'rows'   => ['required','array'],
            'rows.*' => ['array'],
        ]);

        $kind = $payload['kind'];
        $rows = $payload['rows'];

        DB::transaction(function () use ($rows, $kind, $client) {
            foreach ($rows as $row) {
                $id = (int)($row['id'] ?? 0);
                if (!$id) continue;

                if ($kind === 'loads') {
                    /** @var LoadTxn $m */
                    $m = LoadTxn::where('client_id',$client->id)->findOrFail($id);

                    $data = array_intersect_key($row, array_flip([
                        'date','loaded_20_l','temperature_c','density_kg_l','truck_plate','trailer_plate','reference','note'
                    ]));

                    if (array_key_exists('date', $data)) {
                        $data['date'] = $data['date'] ?: $m->date;
                    }

                    $m->fill($data)->save();
                    continue;
                }

                /** @var Offload $m */
                $m = Offload::where('client_id',$client->id)->findOrFail($id);
                if ($m->billed_invoice_id) {
                    abort(422, "Offload #{$m->id} is already billed and cannot be edited.");
                }

                $data = array_intersect_key($row, array_flip([
                    'date','delivered_observed_l','delivered_20_l','loaded_observed_l',
                    'temperature_c','density_kg_l','truck_plate','trailer_plate','reference','note'
                ]));

                if (array_key_exists('date', $data)) {
                    $data['date'] = $data['date'] ?: $m->date;
                }

                // Server-side authoritative recalc (shortfall & allowance)
                $del = (float)($data['delivered_20_l']    ?? $m->delivered_20_l    ?? 0);
                $ld  = (float)($data['loaded_observed_l'] ?? $m->loaded_observed_l ?? 0);
                $data['shortfall_20_l']       = max(0, round($ld - $del, 3));
                $data['depot_allowance_20_l'] = round($del * 0.003, 3);

                $m->fill($data)->save();

                // keep Depot Pool allowance ledger in sync
                $this->syncPoolAllowanceForOffload($m, auth()->id());
            }
        });

        return response()->json(['ok'=>true,'message'=>'Changes saved']);
    }

    /*** ─────────────────────────────────────────────────────────────
     *  Local helpers to manage depot_pool_entries for allowances
     *  ──────────────────────────────────────────────────────────── */

    protected function syncPoolAllowanceForOffload(Offload $offload, ?int $userId = null): void
    {
        DB::transaction(function () use ($offload, $userId) {
            // Remove any previous allowance rows for this offload
            DPE::where('ref_type', DPE::REF_ALLOWANCE ?? 'allowance')
                ->where('ref_id', $offload->id)
                ->delete();

            $allow = round((float)($offload->delivered_20_l ?? 0) * 0.003, 3);
            if ($allow <= 0) return;

            DPE::create([
                'depot_id'    => $offload->depot_id,
                'product_id'  => $offload->product_id,
                'date'        => $offload->date ?: now()->toDateString(),
                'type'        => DPE::TYPE_IN ?? 'in',
                'volume_20_l' => $allow,
                'ref_type'    => DPE::REF_ALLOWANCE ?? 'allowance',
                'ref_id'      => $offload->id,
                'note'        => sprintf('0.3%% allowance from offload #%d', $offload->id),
                'created_by'  => $userId,
            ]);
        });
    }

    protected function removePoolAllowanceForOffload(Offload $offload): void
    {
        DPE::where('ref_type', DPE::REF_ALLOWANCE ?? 'allowance')
           ->where('ref_id', $offload->id)
           ->delete();
    }
}