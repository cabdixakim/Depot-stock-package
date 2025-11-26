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

class OffloadController extends Controller
{
    public function store(StoreOffloadRequest $request, Client $client)
    {
        $data = $request->validated();

        // Derive depot/product from tank if missing
        $tank = Tank::with(['depot','product'])->findOrFail($data['tank_id']);
        $data['depot_id']   = $data['depot_id']   ?? $tank->depot_id;
        $data['product_id'] = $data['product_id'] ?? $tank->product_id;

        $data['client_id'] = $client->id;

        // If delivered @20 not provided, estimate from observed/cvf/temp/rho
        if (empty($data['delivered_20_l']) && !empty($data['delivered_observed_l'])) {
            $cvf = $data['cvf'] ?? $this->estimateCvf($data['temperature_c'] ?? null, $data['density_kg_l'] ?? null);
            $data['delivered_20_l'] = round($data['delivered_observed_l'] * $cvf, 3);
        }

        $del = (float)($data['delivered_20_l'] ?? 0);
        $ld  = (float)($data['loaded_observed_l'] ?? 0);

        // Allowance 0.3% of delivered; shortfall = max(loaded - delivered, 0)
        $data['depot_allowance_20_l'] = round($del * 0.003, 3);
        $data['shortfall_20_l']       = max(0, round($ld - $del, 3));

        // Save offload
        $offload = Offload::create($data);

        // Idempotently sync this offload's allowance into the depot_pool_entries ledger
        $this->syncPoolAllowanceForOffload($offload, auth()->id());

        return response()->json([
            'ok'             => true,
            'message'        => 'Offload saved',
            'date'           => Carbon::parse($offload->date)->format('M d'),
            'tank_label'     => $tank->depot->name.' / '.$tank->product->name,
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