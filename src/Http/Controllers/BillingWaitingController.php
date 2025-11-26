<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Optima\DepotStock\Models\{Offload, Adjustment, Client, Invoice};

class BillingWaitingController extends Controller
{
    /**
     * Client-scoped waiting view.
     * Pre-fills a suggested rate using the last invoice for this client (if any).
     */
    public function index(Client $client)
    {
        $suggestedRate = Invoice::where('client_id', $client->id)
            ->orderByDesc('id')
            ->value('rate_per_litre');

        return view('depot-stock::billing.waiting', [
            'client'        => $client,
            'suggestedRate' => $suggestedRate,
        ]);
    }

    /**
     * JSON for the waiting grid (client-scoped).
     * Returns:
     *  - Offloads: unbilled
     *  - Adjustments: unbilled, billable, positive (or > 0 when type is null)
     *
     * Includes truck & trailer plates for both (when present).
     * Route must be /depot/clients/{client}/billing/waiting/data
     */
    public function data(Client $client, Request $r)
    {
        $from = $r->query('from');
        $to   = $r->query('to');

        // ---- Offloads (unbilled)
        $offloads = Offload::query()
            ->where('client_id', $client->id)
            ->whereNull('billed_invoice_id')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('date', '<=', $to))
            ->with(['tank.depot', 'product'])
            ->get()
            ->map(function ($o) {
                return [
                    'id'            => (int) $o->id,
                    'type'          => 'offload',
                    'date'          => optional($o->date)->format('Y-m-d'),
                    'depot'         => optional(optional($o->tank)->depot)->name,
                    'tank'          => $o->tank_id,
                    'product'       => optional($o->product)->name,
                    'litres'        => (float) ($o->delivered_20_l ?? 0),
                    'reference'     => $o->reference,
                    // NEW: plates (shown in UI columns)
                    'truck_plate'   => $o->truck_plate ?? null,
                    'trailer_plate' => $o->trailer_plate ?? null,
                ];
            });

        // ---- Adjustments (unbilled + billable + positive)
        // Billable policy:
        //   - is_billable = true OR NULL (treat NULL as billable for older rows, adjust as needed)
        // Positive policy:
        //   - type = 'positive' OR (type is NULL and amount_20_l > 0)
        $adjs = Adjustment::query()
            ->where('client_id', $client->id)
            ->whereNull('billed_invoice_id')
            ->where(function ($q) {
                $q->where('is_billable', true)
                  ->orWhereNull('is_billable');
            })
            ->where(function ($q) {
                $q->where('type', 'positive')
                  ->orWhere(function ($qq) {
                      $qq->whereNull('type')->where('amount_20_l', '>', 0);
                  });
            })
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('date', '<=', $to))
            ->with(['tank.depot', 'product'])
            ->get()
            ->map(function ($a) {
                return [
                    'id'            => (int) $a->id,
                    'type'          => 'adjustment',
                    'date'          => optional($a->date)->format('Y-m-d'),
                    'depot'         => optional(optional($a->tank)->depot)->name,
                    'tank'          => $a->tank_id,
                    'product'       => optional($a->product)->name,
                    'litres'        => (float) abs($a->amount_20_l ?? 0),
                    'reference'     => $a->reason ?: $a->reference,
                    // NEW: plates if your adjustments table stores these (safe to send nulls)
                    'truck_plate'   => $a->truck_plate ?? null,
                    'trailer_plate' => $a->trailer_plate ?? null,
                ];
            });

        // Merge and sort by date desc (null dates go last)
        $rows = $offloads->merge($adjs)->sortByDesc('date')->values();

        return response()->json([
            'rows'         => $rows,
            'total_count'  => $rows->count(),
            'total_litres' => round((float) $rows->sum('litres'), 3),
        ]);
    }
}