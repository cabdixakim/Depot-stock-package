<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Optima\DepotStock\Models\Payment;
use Optima\DepotStock\Models\ClientCredit;

// ⬇️ Always use package models explicitly to avoid App\Models mix-ups
use Optima\DepotStock\Models\{Invoice, InvoiceItem, Offload, Adjustment, Client};

class InvoiceController extends Controller
{
    /**
     * List invoices.
     *
     * - /depot/invoices                         → all clients (global view)
     * - /depot/clients/{client}/invoices        → only that client's invoices
     */
  public function index(Request $request, Client $client = null)
{
    // Try to resolve a client if not injected by route-model binding
    if (!$client) {
        // 1) If route has {client} (e.g. /depot/clients/{client}/invoices)
        $routeClient = $request->route('client');

        if ($routeClient instanceof Client) {
            $client = $routeClient;
        } elseif ($routeClient) {
            $client = Client::find($routeClient);
        }

        // 2) Fallback: ?client=ID in query string (e.g. /depot/invoices?client=1)
        if (!$client && $request->filled('client')) {
            $clientId = $request->query('client');
            $client   = Client::find($clientId);
        }
    }

    // Base query
    $query = Invoice::with('client')->latest();

    // If we resolved a client, filter to that client
    if ($client) {
        $query->where('client_id', $client->id);
    }

    // Keep behaviour: limit 200 most recent
    $invoices = $query->limit(200)->get();

    // map of client_id => remaining credit (sum)
    $creditsByClient = ClientCredit::selectRaw('client_id, SUM(remaining) as remaining')
        ->groupBy('client_id')
        ->pluck('remaining', 'client_id');

    // pass context client so Blade can show scope pill
    $contextClient = $client;

    return view('depot-stock::invoices.index', compact('invoices', 'creditsByClient', 'contextClient'));
}

    /**
     * Generate an invoice from selected Offloads & Adjustments
     * Expects JSON and returns JSON.
     */
    public function generate(Request $request)
    {
        // ✅ validate inputs
        $data = $request->validate([
            'client_id'        => 'required|integer|exists:clients,id',
            'rate_per_litre'   => 'required|numeric|min:0.0001',
            'offload_ids'      => 'array',
            'offload_ids.*'    => 'integer',
            'adjustment_ids'   => 'array',
            'adjustment_ids.*' => 'integer',
        ]);

        try {
            $payload = DB::transaction(function () use ($data) {

                $clientId = (int)$data['client_id'];
                $rate     = (float)$data['rate_per_litre'];

                // 🔢 Generate number INV-YYYYMM-####
                $prefix = 'INV-'.now()->format('Ym').'-';
                $seq = str_pad(
                    (string) DB::table('invoices')->where('number', 'like', "$prefix%")->count() + 1,
                    4, '0', STR_PAD_LEFT
                );

                /** @var Invoice $invoice */
                $invoice = Invoice::create([
                    'client_id'       => $clientId,
                    'date'            => now()->toDateString(),
                    'number'          => $prefix.$seq,
                    'status'          => 'issued',
                    'currency'        => 'USD',
                    'rate_per_litre'  => $rate,
                    'total'           => 0, // filled after items
                ]);

                $totalAmount = 0.0;

                // ----------------------------
                // Offloads → InvoiceItem rows
                // ----------------------------
                $offloadIds = $data['offload_ids'] ?? [];
                if (!empty($offloadIds)) {
                    $offloads = Offload::query()
                        ->where('client_id', $clientId)
                        ->whereNull('billed_invoice_id')
                        ->whereIn('id', $offloadIds)
                        ->with(['tank.depot', 'product'])
                        ->lockForUpdate()
                        ->get();

                    foreach ($offloads as $o) {
                        $litres = (float) ($o->delivered_20_l ?? 0);
                        $amount = round($litres * $rate, 2);

                        InvoiceItem::create([
                            'invoice_id'     => $invoice->id,
                            'client_id'      => $clientId,
                            'source_type'    => 'offload',
                            'source_id'      => $o->id,
                            'date'           => $o->date,
                            'description'    => "Offload – Tank #{$o->tank_id}",
                            'litres'         => $litres,
                            'rate_per_litre' => $rate,
                            'amount'         => $amount,
                            'meta'           => [
                                'depot'   => $o->tank->depot->name ?? null,
                                'product' => $o->product->name ?? null,
                                'tank_id' => $o->tank_id,
                                'ref'     => $o->reference,
                            ],
                        ]);

                        // mark billed
                        $o->update(['billed_invoice_id' => $invoice->id, 'billed_at' => now()]);
                        $totalAmount += $amount;
                    }
                }

                // ---------------------------------
                // Adjustments → InvoiceItem rows (+)
                // ---------------------------------
                $adjIds = $data['adjustment_ids'] ?? [];
                if (!empty($adjIds)) {
                    $adjs = Adjustment::query()
                        ->where('client_id', $clientId)
                        ->whereNull('billed_invoice_id')
                        ->whereIn('id', $adjIds)
                        ->with(['tank.depot', 'product'])
                        ->lockForUpdate()
                        ->get();

                    foreach ($adjs as $a) {
                        // bill only positive qty (your policy enforces that in Waiting view)
                        $litres = (float) abs($a->amount_20_l ?? 0);
                        $amount = round($litres * $rate, 2);

                        InvoiceItem::create([
                            'invoice_id'     => $invoice->id,
                            'client_id'      => $clientId,
                            'source_type'    => 'adjustment',
                            'source_id'      => $a->id,
                            'date'           => $a->date,
                            'description'    => $a->reason ? "Adjustment (+) – {$a->reason}" : "Adjustment (+)",
                            'litres'         => $litres,
                            'rate_per_litre' => $rate,
                            'amount'         => $amount,
                            'meta'           => [
                                'depot'    => $a->tank->depot->name ?? null,
                                'product'  => $a->product->name ?? null,
                                'tank_id'  => $a->tank_id,
                                'reason'   => $a->reason,
                            ],
                        ]);

                        $a->update(['billed_invoice_id' => $invoice->id, 'billed_at' => now()]);
                        $totalAmount += $amount;
                    }
                }

                // finalize invoice total
                $invoice->update(['total' => $totalAmount]);

                return [
                    'ok'         => true,
                    'invoice_id' => $invoice->id,
                    'number'     => $invoice->number,
                    'total'      => $totalAmount,
                ];
            });

            // ✅ Always JSON
            return response()->json($payload);

        } catch (\Throwable $e) {
            // Development-time visibility so we see real error in the UI
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single invoice (placeholder)
     */
    public function show($id)
    {
        $invoice = \Optima\DepotStock\Models\Invoice::with(['client', 'items', 'payments'])
            ->findOrFail($id);

        $credits = \Optima\DepotStock\Models\ClientCredit::where('client_id', $invoice->client_id)
            ->where('remaining', '>', 0)
            ->get();

        return view('depot-stock::invoices.show', compact('invoice', 'credits'));
    }

    /**
     * Apply a client credit to this invoice.
     */
    public function applyCredit(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'credit_id' => 'required|integer|exists:client_credits,id',
            'amount'    => 'required|numeric|min:0.01',
        ]);

        $payload = DB::transaction(function () use ($data, $invoice) {

            /** @var \Optima\DepotStock\Models\ClientCredit $credit */
            $credit = ClientCredit::lockForUpdate()->findOrFail($data['credit_id']);

            // Safety: credit must belong to same client as the invoice
            if ($credit->client_id !== $invoice->client_id) {
                abort(422, 'This credit does not belong to the same client as the invoice.');
            }

            $remaining = (float) $credit->remaining;
            $applyAmt  = (float) $data['amount'];

            if ($applyAmt > $remaining + 0.00001) {
                abort(422, 'You cannot apply more than the remaining credit.');
            }

            if ($applyAmt <= 0) {
                abort(422, 'Amount must be greater than zero.');
            }

            // --- Create a payment on this invoice using the credit ---
            /** @var \Optima\DepotStock\Models\Payment $payment */
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'client_id'  => $invoice->client_id,
                'date'       => now()->toDateString(),
                'amount'     => $applyAmt,
                'mode'       => 'credit',                 // important: we know this came from a credit
                'reference'  => 'Client credit #'.$credit->id,
                'currency'   => $credit->currency ?? ($invoice->currency ?? 'USD'),
                'notes'      => 'Applied from client credit ID '.$credit->id,
            ]);

            // --- Reduce the remaining credit ---
            $credit->remaining = max(0, $remaining - $applyAmt);
            $credit->save();

            // --- Recalculate invoice totals (status, balance, paid_total, etc.) ---
            if (method_exists($invoice, 'recalculateTotals')) {
                $invoice->refresh();
                $invoice->recalculateTotals();
            } else {
                // Fallback: simple recompute if your model doesn't have helper
                $invoice->paid_total = (float)($invoice->paid_total ?? 0) + $applyAmt;
                $invoice->save();
            }

            return [
                'payment' => [
                    'id'       => $payment->id,
                    'amount'   => $payment->amount,
                    'date'     => $payment->date,
                    'mode'     => $payment->mode,
                ],
                'invoice' => [
                    'id'        => $invoice->id,
                    'status'    => $invoice->status,
                    'total'     => (float) $invoice->total,
                    'paid_total'=> (float) $invoice->paid_total,
                    'balance'   => max(0, (float)$invoice->total - (float)$invoice->paid_total),
                ],
                'credit'  => [
                    'id'        => $credit->id,
                    'amount'    => (float)$credit->amount,
                    'remaining' => (float)$credit->remaining,
                    'currency'  => $credit->currency,
                ],
            ];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Credit applied to invoice.',
                'data'    => $payload,
            ]);
        }

        // Non-AJAX fallback
        return redirect()
            ->route('depot.invoices.show', $invoice->id)
            ->with('status', 'Credit applied to invoice.');
    }
}