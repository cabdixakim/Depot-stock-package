<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Optima\DepotStock\Models\Client;

class StatementController extends Controller
{
    /**
     * Map your schema here.
     */
    protected array $invoiceMap = [
        'table'  => 'invoices',
        'date'   => 'date',   // column with invoice date
        'amount' => 'total',  // column with invoice total
        'number' => 'number', // invoice number
        'status' => 'status', // optional, we won't globally filter on it
        'ok_statuses' => [],  // not used anymore for invoices
    ];

    protected array $paymentMap = [
        'table'  => 'payments',
        'date'   => 'date',       // payment date
        'amount' => 'amount',     // payment amount
        'number' => 'reference',  // receipt / reference number
        'status' => 'status',     // optional
        'ok_statuses' => [],      // not used anymore for payments
    ];

    public function index(Request $request, Client $client)
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->query('to')   ?: now()->toDateString();

        return view('depot-stock::clients.statement', compact('client','from','to'));
    }

    public function data(Request $request, Client $client)
    {
        $from   = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to     = $request->query('to')   ?: now()->toDateString();
        $unpaid = (bool) $request->query('unpaid', false); // show only unpaid invoices in-range?

        // Resolve maps (hard map first, else autodetect)
        $inv = $this->resolveMap($this->invoiceMap, [
            'date'   => ['date','issued_at','created_at'],
            'amount' => ['total','total_amount','grand_total','amount','net_total'],
            'number' => ['number','invoice_no','no','ref','reference'],
            'status' => ['status'],
        ]);

        $pay = $this->resolveMap($this->paymentMap, [
            'date'   => ['date','paid_at','created_at'],
            'amount' => ['amount','paid_amount','total'],
            'number' => ['reference','ref','receipt_no','number','txn_ref'],
            'status' => ['status'],
        ]);

        // ===== Opening balance (before FROM) =====
        // Opening = invoices BEFORE from − payments BEFORE from
        $openingInvoices = 0.0;
        if ($inv['table'] && $inv['date'] && $inv['amount']) {
            $openingInvoices = (float) DB::table($inv['table'])
                ->where('client_id', $client->id)
                ->whereDate($inv['date'], '<', $from)
                ->sum($inv['amount']);
        }

        $openingPayments = 0.0;
        if ($pay['table'] && $pay['date'] && $pay['amount']) {
            $openingPaymentsQuery = DB::table($pay['table'])
                ->where('client_id', $client->id)
                ->whereDate($pay['date'], '<', $from);

            // 🔐 exclude internal credit movements (mode = credit / client_credit / credit apply)
            if (Schema::hasColumn($pay['table'], 'mode')) {
                $openingPaymentsQuery->whereNotIn('mode', [
                    'credit',
                    'client_credit',
                    'credit apply',
                ]);
            }

            $openingPayments = (float) $openingPaymentsQuery->sum($pay['amount']);
        }

        $opening = round($openingInvoices - $openingPayments, 2);

        // ===== In-range movements =====
        $rows = collect();

        // Invoices (charges)
        if ($inv['table'] && $inv['date'] && $inv['amount']) {
            $invoiceQuery = DB::table($inv['table'])
                ->select([
                    'id',
                    $inv['date'] . ' as date',
                    ($inv['number'] ?: DB::raw("NULL")) . ' as doc_no',
                    ($inv['amount'] . ' as amount'),
                    $inv['status']
                        ? ($inv['status'] . ' as status')
                        : DB::raw("NULL as status"),
                ])
                ->where('client_id', $client->id)
                ->whereDate($inv['date'], '>=', $from)
                ->whereDate($inv['date'], '<=', $to);

            // Optional: only unpaid invoices in-range
            if ($unpaid && Schema::hasColumn($inv['table'], 'status')) {
                $invoiceQuery->where('status', 'unpaid');
            }

            $invoices = $invoiceQuery
                ->get()
                ->map(function($r){
                    return [
                        'type'        => 'Invoice',
                        'id'          => $r->id,
                        'date'        => (string) $r->date,
                        'doc_no'      => $r->doc_no ?? ('INV-'.$r->id),
                        'description' => $r->status
                            ? ('Invoice • '.ucfirst($r->status))
                            : 'Invoice',
                        'debit'       => round((float)$r->amount, 2),
                        'credit'      => 0.00,
                    ];
                });

            $rows = $rows->merge($invoices);
        }

        // Payments (credits)
        if ($pay['table'] && $pay['date'] && $pay['amount']) {
            $paymentsQuery = DB::table($pay['table'])
                ->select([
                    'id',
                    $pay['date'] . ' as date',
                    ($pay['number'] ?: DB::raw("NULL")) . ' as doc_no',
                    ($pay['amount'] . ' as amount'),
                    $pay['status']
                        ? ($pay['status'] . ' as status')
                        : DB::raw("NULL as status"),
                ])
                ->where('client_id', $client->id)
                ->whereDate($pay['date'], '>=', $from)
                ->whereDate($pay['date'], '<=', $to);

            // 🔐 same exclusion in-range: don't treat internal credit moves as new payments
            if (Schema::hasColumn($pay['table'], 'mode')) {
                $paymentsQuery->whereNotIn('mode', [
                    'credit',
                    'client_credit',
                    'credit apply',
                ]);
            }

            $payments = $paymentsQuery
                ->get()
                ->map(function($r){
                    return [
                        'type'        => 'Payment',
                        'id'          => $r->id,
                        'date'        => (string) $r->date,
                        'doc_no'      => $r->doc_no ?? ('PMT-'.$r->id),
                        'description' => $r->status
                            ? ('Payment • '.ucfirst($r->status))
                            : 'Payment received',
                        'debit'       => 0.00,
                        'credit'      => round((float)$r->amount, 2),
                    ];
                });

            $rows = $rows->merge($payments);
        }

        $rows = $rows
            ->sortBy([['date','asc'],['type','asc'],['id','asc']])
            ->values();

        // ===== Running balance =====
        $balance = $opening;
        $rows = $rows->map(function($r) use (&$balance){
            $balance = round($balance + $r['debit'] - $r['credit'], 2);
            $r['balance'] = $balance;
            return $r;
        });

        return response()->json([
            'from'     => $from,
            'to'       => $to,
            'opening'  => $opening,
            'charges'  => round($rows->sum('debit'), 2),
            'credits'  => round($rows->sum('credit'), 2),
            'closing'  => $balance,
            'unpaid'   => $unpaid ? 1 : 0,
            'rows'     => $rows,
        ]);
    }

    public function export(Request $request, Client $client)
    {
        $format = $request->query('format','csv'); // csv | print
        $from   = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to     = $request->query('to')   ?: now()->toDateString();
        $unpaid = (bool) $request->query('unpaid', false);

        // Reuse data() logic so export matches screen exactly
        $json = $this->data($request, $client)->getData(true);

        if ($format === 'print') {
            return view('depot-stock::clients.statement_print', [
                'client'  => $client,
                'meta'    => $json,
                'rows'    => collect($json['rows'] ?? []),
            ]);
        }

        $suffix   = $unpaid ? 'unpaid' : 'all';
        $filename = "statement_{$client->id}_{$from}_{$to}_{$suffix}.csv";

        return response()->streamDownload(function() use ($json) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date','Type','Document','Description','Debit','Credit','Balance']);
            foreach ($json['rows'] ?? [] as $r) {
                fputcsv($out, [
                    $r['date'] ?? '',
                    $r['type'] ?? '',
                    $r['doc_no'] ?? '',
                    $r['description'] ?? '',
                    number_format((float)($r['debit'] ?? 0), 2, '.', ''),
                    number_format((float)($r['credit'] ?? 0), 2, '.', ''),
                    number_format((float)($r['balance'] ?? 0), 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolve a map (hard map first) and fall back to autodetect per column.
     */
    protected function resolveMap(array $map, array $candidates): array
    {
        $table = $map['table'] ?? null;
        if (!$table || !Schema::hasTable($table)) {
            return [
                'table'       => null,
                'date'        => null,
                'amount'      => null,
                'number'      => null,
                'status'      => null,
                'ok_statuses' => [],
            ];
        }

        $pick = function(?string $fixed, array $alts) use ($table) {
            if ($fixed && Schema::hasColumn($table, $fixed)) return $fixed;
            foreach ($alts as $c) {
                if (Schema::hasColumn($table, $c)) return $c;
            }
            return null;
        };

        return [
            'table'       => $table,
            'date'        => $pick($map['date']   ?? null, $candidates['date']),
            'amount'      => $pick($map['amount'] ?? null, $candidates['amount']),
            'number'      => $pick($map['number'] ?? null, $candidates['number']),
            'status'      => $pick($map['status'] ?? null, $candidates['status']),
            'ok_statuses' => $map['ok_statuses'] ?? [],
        ];
    }
}