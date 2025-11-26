<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Optima\DepotStock\Models\{Payment, Invoice, ClientCredit, ClientStorageCharge};

class PaymentController extends Controller
{
    /**
     * List recent payments (basic).
     */
    public function index()
    {
        $payments = Payment::with(['invoice', 'client'])->latest()->limit(100)->get();

        return view('depot-stock::payments.index', compact('payments'));
    }

    /**
     * Store a payment and create a ClientCredit if there is overpayment.
     *
     * Expected fields:
     * - invoice_id (int, exists)
     * - client_id  (int, exists)
     * - date       (date)
     * - amount     (numeric)
     * - mode       (string)  ← NOTE the name is "mode" (not "method")
     * - reference  (nullable string)
     * - currency   (nullable 3-char, defaults to USD if missing)
     * - notes      (nullable string)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'client_id'  => 'required|integer|exists:clients,id',
            'amount'     => 'required|numeric|min:0.01',
            'date'       => 'required|date',
            'mode'       => 'required|string|max:100',
            'reference'  => 'nullable|string|max:255',
            'currency'   => 'nullable|string|size:3',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $payload = DB::transaction(function () use ($data) {

            /** @var Invoice $invoice */
            $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);

            // Current balance BEFORE this payment
            $currentPaid   = (float) ($invoice->paid_total ?? 0);
            $currentTotal  = (float) ($invoice->total ?? 0);
            $currentBal    = max(0.0, $currentTotal - $currentPaid);

            // Create the payment (full amount)
            /** @var Payment $payment */
            $payment = Payment::create([
                'invoice_id' => $data['invoice_id'],
                'client_id'  => $data['client_id'],
                'date'       => $data['date'],
                'amount'     => (float) $data['amount'],
                'mode'       => $data['mode'],
                'reference'  => $data['reference'] ?? null,
                'currency'   => $data['currency']  ?? 'USD',
                'notes'      => $data['notes']      ?? null,
            ]);

            // Recalculate invoice after payment
            $invoice->recalculateTotals(); // updates paid_total, balance, status

            // Compute overpayment based on the balance BEFORE we applied this payment
            $overpay = max(0.0, (float)$data['amount'] - $currentBal);

            $creditCreated = null;
            if ($overpay > 0.00001) {
                // Record a client credit with remaining=overpay
                $creditCreated = ClientCredit::create([
                    'client_id'  => $invoice->client_id,
                    'payment_id' => $payment->id,
                    'amount'     => round($overpay, 2),
                    'remaining'  => round($overpay, 2),
                    'currency'   => $payment->currency ?? 'USD',
                    'reason'     => 'Overpayment on ' . ($invoice->number ?? ('INV#' . $invoice->id)),
                ]);
            }

            // ===== NEW: if this is a storage invoice, push litres into ClientStorageCharge =====
            // We treat any storage charge linked to this invoice as partly/fully "cleared"
            // based on the paid_total / total ratio.
            if ($invoice->total > 0) {
                $ratio = max(0.0, min(1.0, (float)($invoice->paid_total ?? 0) / (float)$invoice->total));

                if ($ratio > 0) {
                    $storageCharges = ClientStorageCharge::where('invoice_id', $invoice->id)->get();

                    foreach ($storageCharges as $charge) {
                        $totalLitres = (float) ($charge->total_litres ?? 0);

                        $clearedLitres   = $totalLitres * $ratio;
                        $unclearedLitres = max(0.0, $totalLitres - $clearedLitres);

                        $charge->cleared_litres   = $clearedLitres;
                        $charge->uncleared_litres = $unclearedLitres;

                        // Only stamp paid_at when the invoice is fully paid
                        if ($ratio >= 0.9999 && empty($charge->paid_at)) {
                            $charge->paid_at = $data['date'];
                        }

                        $charge->save();
                    }
                }
            }

            return [
                'ok'      => true,
                'payment' => [
                    'id'       => $payment->id,
                    'amount'   => $payment->amount,
                    'currency' => $payment->currency,
                    'mode'     => $payment->mode,
                    'date'     => $payment->date,
                ],
                'invoice' => [
                    'id'        => $invoice->id,
                    'number'    => $invoice->number,
                    'status'    => $invoice->status,
                    'total'     => (float) $invoice->total,
                    'paid_total'=> (float) $invoice->paid_total,
                    'balance'   => max(0, (float)$invoice->total - (float)$invoice->paid_total),
                ],
                'credit'  => $creditCreated ? [
                    'id'        => $creditCreated->id,
                    'amount'    => $creditCreated->amount,
                    'remaining' => $creditCreated->remaining,
                    'currency'  => $creditCreated->currency,
                ] : null,
            ];
        });

        // AJAX?
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        // Non-AJAX fallback
        return redirect()
            ->route('depot.invoices.show', $payload['invoice']['id'])
            ->with('status', $payload['credit']
                ? 'Payment recorded and excess saved as client credit.'
                : 'Payment recorded.');
    }
}