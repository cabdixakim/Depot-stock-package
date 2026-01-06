<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Optima\DepotStock\Models\{ClientCredit, Invoice, Payment};

class ClientCreditController extends Controller
{

     public function storeCreditNote(Request $request, Invoice $invoice)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $invoice) {
            ClientCredit::create([
                'client_id'  => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'amount'     => $request->amount,
                'remaining'  => $request->amount,
                'currency'   => $invoice->currency,
                'reason'     => $request->reason,
                'source'     => 'credit_note',
                'created_by'=> auth()->id(),
                'meta'       => [
                    'invoice_no' => $invoice->invoice_no ?? null,
                ],
            ]);
        });

        return redirect()
            ->back()
            ->with('success', 'Credit note added successfully.');
    }

    /**
     * Apply an existing client credit to a specific invoice.
     */
    public function apply(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'credit_id' => 'required|integer|exists:client_credits,id',
            'amount'    => 'required|numeric|min:0.01',
            'date'      => 'nullable|date',
            'notes'     => 'nullable|string',
        ]);

        return DB::transaction(function () use ($invoice, $data) {
            $credit = ClientCredit::lockForUpdate()->findOrFail($data['credit_id']);

            // Ensure credit belongs to the same client
            if ($credit->client_id !== $invoice->client_id) {
                return response()->json(['ok' => false, 'message' => 'Credit belongs to another client.'], 422);
            }

            // Ensure credit is available
            if ($credit->remaining <= 0) {
                return response()->json(['ok' => false, 'message' => 'This credit has already been used.'], 422);
            }

            $amount = min($data['amount'], $credit->remaining);

            // Create a Payment entry tied to the credit
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'client_id'  => $invoice->client_id,
                'date'       => $data['date'] ?? now()->toDateString(),
                'amount'     => $amount,
                'mode'       => 'Credit Apply',
                'reference'  => 'Credit #' . $credit->id,
                'currency'   => $invoice->currency ?? 'USD',
                'notes'      => $data['notes'] ?? 'Applied from client credit',
            ]);

            // Deduct credit balance
            $credit->remaining -= $amount;
            $credit->remaining = max(0, $credit->remaining);
            $credit->saveQuietly();

            // Refresh invoice totals/status
            $invoice->recalculateTotals();

            return response()->json([
                'ok' => true,
                'message' => 'Credit applied successfully.',
                'invoice' => [
                    'id' => $invoice->id,
                    'status' => $invoice->status,
                    'balance' => round(($invoice->total ?? 0) - ($invoice->paid_total ?? 0), 2),
                ],
                'credit' => [
                    'id' => $credit->id,
                    'remaining' => $credit->remaining,
                ],
            ]);
        });
    }
}