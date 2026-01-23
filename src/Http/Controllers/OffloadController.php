<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Optima\DepotStock\Http\Requests\StoreOffloadRequest;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Tank;
use Optima\DepotStock\Models\DepotPoolEntry as DPE;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;  

class OffloadController extends Controller
{
public function store(Request $request, Client $client)
{
    $data = $request->validate([
        'date'                  => ['required', 'date'],

        'tank_id'               => ['required', 'integer'],
        'depot_id'              => ['nullable', 'integer'],
        'product_id'            => ['nullable', 'integer'],

        'delivered_observed_l'  => ['nullable', 'numeric', 'min:0'],
        'cvf'                   => ['nullable', 'numeric', 'min:0'],
        'delivered_20_l'        => ['nullable', 'numeric', 'min:0'],

        // NOTE: in your DB this is NOT NULL; we will default it safely below if missing
        'loaded_observed_l'     => ['nullable', 'numeric', 'min:0'],

        'temperature_c'         => ['nullable', 'numeric'],
        'density_kg_l'          => ['nullable', 'numeric'],

        'truck_plate'           => ['nullable', 'string', 'max:50'],
        'trailer_plate'         => ['nullable', 'string', 'max:50'],
        'reference'             => ['nullable', 'string', 'max:100'],
        'note'                  => ['nullable', 'string', 'max:255'],

        // Compliance / linkage (additive-only)
        'link_clearance'              => ['nullable', 'boolean'],
        'clearance_id'                => ['nullable', 'integer'],
        'compliance_bypass_reason'    => ['nullable', 'string', 'max:50'],
        'compliance_bypass_notes'     => ['nullable', 'string', 'max:255'],
    ]);

    $isLinking = (bool)($data['link_clearance'] ?? false);

    // Always bind to this client (walk-in default)
    $data['client_id'] = $client->id;

    // Derive depot/product from tank if missing (existing behaviour)
    $tank = Tank::with(['depot', 'product'])->findOrFail($data['tank_id']);
    $data['depot_id']   = $data['depot_id']   ?? $tank->depot_id;
    $data['product_id'] = $data['product_id'] ?? $tank->product_id;

    // -----------------------------
    // Compliance: Clearance linking
    // -----------------------------
    $clearance = null;

    if ($isLinking) {
        // Conditional validation (only when linking)
        if (empty($data['clearance_id'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Clearance link is enabled. Please select a clearance.',
                'errors'  => ['clearance_id' => ['Clearance is required when linking.']],
            ], 422);
        }

        $clearance = \Optima\DepotStock\Models\Clearance::with(['documents'])->find($data['clearance_id']);
        if (!$clearance) {
            return response()->json([
                'ok'      => false,
                'message' => 'Selected clearance was not found.',
                'errors'  => ['clearance_id' => ['Invalid clearance.']],
            ], 422);
        }

        // Must belong to the same client (audit safety)
        if ((int)$clearance->client_id !== (int)$client->id) {
            return response()->json([
                'ok'      => false,
                'message' => 'This clearance does not belong to the selected client.',
                'errors'  => ['clearance_id' => ['Clearance client mismatch.']],
            ], 422);
        }

        // Status eligibility (server truth)
        if ($clearance->status === 'cancelled') {
            return response()->json([
                'ok'      => false,
                'message' => 'This clearance is cancelled and cannot be linked.',
                'errors'  => ['clearance_id' => ['Cancelled clearance cannot be linked.']],
            ], 422);
        }

        if (!in_array($clearance->status, ['tr8_issued', 'arrived'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'This clearance is not in a linkable status.',
                'errors'  => ['clearance_id' => ['Clearance must be TR8 issued or arrived to link.']],
            ], 422);
        }

        // Rule #2 enforcement (one clearance → at most one offload) via controller check
        $alreadyLinked = Offload::where('clearance_id', $clearance->id)->exists();
        if ($alreadyLinked) {
            return response()->json([
                'ok'      => false,
                'message' => 'This clearance is already linked to an offload.',
                'errors'  => ['clearance_id' => ['Clearance already linked.']],
            ], 422);
        }

        // Attach linkage (offload may exist with or without clearance)
        $data['clearance_id'] = $clearance->id;

        // Auto-fill truck + trailer (but editable => only fill if user left them empty)
        if (empty($data['truck_plate']) && !empty($clearance->truck_number)) {
            $data['truck_plate'] = $clearance->truck_number;
        }
        if (empty($data['trailer_plate']) && !empty($clearance->trailer_number)) {
            $data['trailer_plate'] = $clearance->trailer_number;
        }

        // Auto-fill loaded qty (paperwork) from clearance (editable => only fill if empty)
        if (!isset($data['loaded_observed_l']) || $data['loaded_observed_l'] === '' || $data['loaded_observed_l'] === null) {
            if ($clearance->loaded_20_l !== null && $clearance->loaded_20_l !== '') {
                $data['loaded_observed_l'] = (float)$clearance->loaded_20_l;
            }
        }

        // Bypass fields must be clean when linked
        $data['compliance_bypass_reason'] = null;
        $data['compliance_bypass_notes']  = null;
    } else {
        // Walk-in: keep linkage null (default path)
        $data['clearance_id'] = null;

        // If user leaves bypass reason empty, auto-fill on backend for audit
        $reason = trim((string)($data['compliance_bypass_reason'] ?? ''));
        $notes  = trim((string)($data['compliance_bypass_notes'] ?? ''));

        if ($reason === '') {
            $data['compliance_bypass_reason'] = 'not_provided';

            $u = auth()->user();
            $tag = 'Compliance bypassed';
            if ($u) {
                $tag .= ' by ' . ($u->name ?? 'user') . ' (ID:' . ($u->id ?? 'n/a') . ')';
            } else {
                $tag .= ' by system';
            }
            $tag .= '. Reason not provided.';

            // Append if notes exist, otherwise set
            $data['compliance_bypass_notes'] = $notes !== '' ? ($notes . ' | ' . $tag) : $tag;
        } else {
            // keep user-provided reason; keep notes as-is
            $data['compliance_bypass_reason'] = $reason;
            $data['compliance_bypass_notes']  = $notes !== '' ? $notes : null;
        }
    }

    // DB safety: loaded_observed_l is NOT NULL in your schema; default to 0 if still missing
    if (!isset($data['loaded_observed_l']) || $data['loaded_observed_l'] === '' || $data['loaded_observed_l'] === null) {
        $data['loaded_observed_l'] = 0;
    }

    // If delivered @20 not provided, estimate from observed/cvf/temp/rho (existing behaviour)
    if (empty($data['delivered_20_l']) && !empty($data['delivered_observed_l'])) {
        $cvf = $data['cvf'] ?? $this->estimateCvf($data['temperature_c'] ?? null, $data['density_kg_l'] ?? null);
        $data['delivered_20_l'] = round(((float)$data['delivered_observed_l']) * (float)$cvf, 3);
    }

    $del = (float)($data['delivered_20_l'] ?? 0);
    $ld  = (float)($data['loaded_observed_l'] ?? 0);

    // Allowance 0.3% of delivered; shortfall = max(loaded - delivered, 0) (existing behaviour)
    $data['depot_allowance_20_l'] = round($del * 0.003, 3);
    $data['shortfall_20_l']       = max(0, round($ld - $del, 3));

    // Save offload
    $offload = Offload::create($data);

    // Idempotently sync this offload's allowance into the depot_pool_entries ledger
    $this->syncPoolAllowanceForOffload($offload, auth()->id());

    // If linked: log clearance event + move clearance status to "offloaded"
    if ($isLinking && $clearance) {
        DB::transaction(function () use ($clearance, $offload) {
            $from = (string)($clearance->status ?? '');

            // Update clearance status
            $clearance->status = 'offloaded';
            $clearance->save();

            // Log event: linked_to_offload (audit-proof)
            \Optima\DepotStock\Models\ClearanceEvent::create([
                'clearance_id' => $clearance->id,
                'user_id'      => auth()->id(),
                'event'        => 'linked_to_offload',
                'from_status'  => $from,
                'to_status'    => 'offloaded',
                'meta'         => [
                    'offload_id'           => $offload->id,
                    'client_id'            => $offload->client_id,
                    'linked_truck_plate'   => $offload->truck_plate,
                    'linked_trailer_plate' => $offload->trailer_plate,
                    'linked_loaded_20_l'   => $offload->loaded_observed_l,
                    'linked_delivered_20_l'=> $offload->delivered_20_l,
                ],
            ]);
        });
    }

    return response()->json([
        'ok'            => true,
        'message'       => 'Offload saved',
        'date'          => Carbon::parse($offload->date)->format('M d'),
        'tank_label'    => $tank->depot->name . ' / ' . $tank->product->name,
        'delivered_20_l' => $offload->delivered_20_l,
    ]);
}

    /**
     * Ensure exactly one allowance row exists in depot_pool_entries for this offload.
     * Safe to call after create or update. Uses delete-then-insert.
     */
    protected function syncPoolAllowanceForOffload(Offload $offload, ?int $userId = null): void
    {
        DB::transaction(function () use ($offload, $userId) {
            // Remove any previous allowance rows for this offload
            DPE::where('ref_type', DPE::REF_ALLOWANCE)
                ->where('ref_id', $offload->id)
                ->delete();

            $allow = round((float)($offload->delivered_20_l ?? 0) * 0.003, 3);
            if ($allow <= 0) {
                return;
            }

            DPE::create([
                'depot_id'    => $offload->depot_id,
                'product_id'  => $offload->product_id,
                'date'        => $offload->date ?: now()->toDateString(),
                'type'        => DPE::TYPE_IN,                 // expects constant in model
                'volume_20_l' => $allow,
                'ref_type'    => DPE::REF_ALLOWANCE,          // expects constant in model
                'ref_id'      => $offload->id,
                'note'        => sprintf('0.3%% allowance from offload #%d', $offload->id),
                'created_by'  => $userId,
            ]);
        });
    }

    // Very rough placeholder; good enough until we wire real API/table
    protected function estimateCvf(?float $temp, ?float $rho): float
    {
        $k = 0.00065; $base = 0.825;
        $rel = $rho ? ($rho / $base) : 1;
        $fac = 1 - $k * ((float)$temp - 20);
        return max(0.90, min(1.02, $rel * $fac));
    }

    // append to your existing OffloadController class

public function update(Request $request, Client $client, Offload $offload)
{
    abort_if($offload->client_id !== $client->id, 404);
    if ($offload->billed_invoice_id) {
        return response()->json(['ok'=>false,'message'=>'This offload is billed and cannot be edited.'], 422);
    }

    $data = $request->validate([
        'date'                 => ['sometimes','date'],
        'delivered_observed_l' => ['nullable','numeric','min:0'],
        'delivered_20_l'       => ['sometimes','numeric','min:0'],
        'loaded_observed_l'    => ['nullable','numeric','min:0'],
        'temperature_c'        => ['nullable','numeric'],
        'density_kg_l'         => ['nullable','numeric'],
        'truck_plate'          => ['nullable','string','max:50'],
        'trailer_plate'        => ['nullable','string','max:50'],
        'reference'            => ['nullable','string','max:100'],
        'note'                 => ['nullable','string','max:255'],
    ]);

    // authoritative recalcs
    $del = (float)($data['delivered_20_l'] ?? $offload->delivered_20_l ?? 0);
    $ld  = (float)($data['loaded_observed_l'] ?? $offload->loaded_observed_l ?? 0);
    $data['shortfall_20_l']       = max(0, round($ld - $del, 3));
    $data['depot_allowance_20_l'] = round($del * 0.003, 3);

    $offload->fill($data)->save();

    // sync pool ledger
    $this->syncPoolAllowanceForOffload($offload, auth()->id());

    return response()->json(['ok'=>true,'message'=>'Offload updated']);
}

public function destroy(Client $client, Offload $offload)
{
    abort_if($offload->client_id !== $client->id, 404);
    if ($offload->billed_invoice_id) {
        return response()->json(['ok'=>false,'message'=>'This offload is billed and cannot be deleted.'], 422);
    }

    $this->removePoolAllowanceForOffload($offload);
    $offload->delete();

    return response()->json(['ok'=>true,'message'=>'Offload deleted']);
}

/** helper used by MovementsController::destroy */
public function removePoolAllowanceForOffload(Offload $offload): void
{
    \Optima\DepotStock\Models\DepotPoolEntry::where('ref_type', \Optima\DepotStock\Models\DepotPoolEntry::REF_ALLOWANCE)
        ->where('ref_id', $offload->id)
        ->delete();
}
public function bulkUpdate(\Illuminate\Http\Request $request, \Optima\DepotStock\Models\Client $client)
{
    $rows  = $request->input('rows', []);
    $count = 0;

    foreach ($rows as $row) {
        if (!isset($row['id'])) continue;

        $off = \Optima\DepotStock\Models\Offload::where('client_id', $client->id)->find($row['id']);
        if (!$off) continue;

        $off->fill(Arr::only($row, [
            'date',
            'delivered_20_l',
            'shortfall_20_l',
            'depot_allowance_20_l',
            'temperature_c',
            'density_kg_l',
            'truck_plate',
            'trailer_plate',
            'reference',
            'note',
        ]));
        $off->save();

        // Keep pool allowance ledger in sync
        if (method_exists($this, 'syncPoolAllowanceForOffload')) {
            $this->syncPoolAllowanceForOffload($off, auth()->id());
        }

        $count++;
    }

    return response()->json(['ok' => true, 'updated' => $count]);
}

}