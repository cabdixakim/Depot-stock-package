<?php

namespace Optima\DepotStock\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Optima\DepotStock\Models\Invoice;
use Optima\DepotStock\Models\Payment;

class PortalStatementController extends Controller
{
    public function index(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;

        abort_unless($client, 403);

        $from = $request->get('from');
        $to   = $request->get('to');

        // --------------------------------------------------
        // Base queries
        // --------------------------------------------------
        $invBase = Invoice::where('client_id', $client->id);
        $payBase = Payment::where('client_id', $client->id);

        $applyWindow = function ($q) use ($from, $to) {
            return $q
                ->when($from, fn ($qq) => $qq->whereDate('date', '>=', $from))
                ->when($to,   fn ($qq) => $qq->whereDate('date', '<=', $to));
        };

        // Opening balance = invoices - payments BEFORE "from"
        if ($from) {
            $invBefore = (float) (clone $invBase)
                ->whereDate('date', '<', $from)
                ->sum('total');

            $payBefore = (float) (clone $payBase)
                ->whereDate('date', '<', $from)
                ->sum('amount');
        } else {
            $invBefore = 0.0;
            $payBefore = 0.0;
        }

        $openingBalance = $invBefore - $payBefore;

        // Invoices & payments inside the selected window
        $invWindow = $applyWindow(clone $invBase)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $payWindow = $applyWindow(clone $payBase)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        // --------------------------------------------------
        // Build ledger rows
        // --------------------------------------------------
        $rows = collect();

        foreach ($invWindow as $inv) {
            $rows->push([
                'date'        => $inv->date ? Carbon::parse($inv->date) : null,
                'raw_date'    => $inv->date,
                'id'          => $inv->id,
                'kind'        => 'invoice',
                'ref'         => $inv->number ?? ('Invoice #'.$inv->id),
                'description' => $inv->reference ?? $inv->note ?? 'Invoice',
                'debit'       => (float) ($inv->total ?? 0),
                'credit'      => 0.0,
                'model'       => $inv,
            ]);
        }

        foreach ($payWindow as $pay) {
            $rows->push([
                'date'        => $pay->date ? Carbon::parse($pay->date) : null,
                'raw_date'    => $pay->date,
                'id'          => $pay->id,
                'kind'        => 'payment',
                'ref'         => $pay->reference ?? ('Payment #'.$pay->id),
                'description' => $pay->method
                    ? strtoupper($pay->method).' payment'
                    : 'Payment',
                'debit'       => 0.0,
                'credit'      => (float) ($pay->amount ?? 0),
                'model'       => $pay,
            ]);
        }

        // Sort by date, then ID, then kind
        $rows = $rows
            ->sortBy(function ($r) {
                $d = $r['date'] ? $r['date']->format('Ymd') : '99999999';
                return $d.'-'.str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT).'-'.$r['kind'];
            })
            ->values();

        // Running balance
        $running = $openingBalance;
        $rows = $rows->map(function ($r) use (&$running) {
            $running += $r['debit'] - $r['credit'];
            $r['running'] = $running;
            return $r;
        });

        $totalDebit   = $rows->sum('debit');
        $totalCredit  = $rows->sum('credit');
        $closingBalance = $openingBalance + $totalDebit - $totalCredit;

        $currency = config('depot-stock.currency', 'USD');

        $windowLabel = ($from || $to)
            ? (($from ?: '…').' → '.($to ?: '…'))
            : 'All time';

        return view('depot-stock::portal.statements', [
            'client'          => $client,
            'rows'            => $rows,
            'openingBalance'  => $openingBalance,
            'totalDebit'      => $totalDebit,
            'totalCredit'     => $totalCredit,
            'closingBalance'  => $closingBalance,
            'from'            => $from,
            'to'              => $to,
            'windowLabel'     => $windowLabel,
            'currency'        => $currency,
        ]);
    }
}