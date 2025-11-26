<?php

namespace Optima\DepotStock\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load;
use Optima\DepotStock\Models\Adjustment;
use Optima\DepotStock\Models\Invoice;
use Optima\DepotStock\Models\Payment;
use Optima\DepotStock\Models\Client;

class ClientPortalController extends Controller
{
    // =========================================================
    // HOME (Dashboard)
    // =========================================================
    public function home(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;

        abort_unless($client, 403);

        $from = $request->get('from');
        $to   = $request->get('to');

        $window = function ($q) use ($from, $to) {
            return $q
                ->when($from, fn ($qq) => $qq->whereDate('date', '>=', $from))
                ->when($to,   fn ($qq) => $qq->whereDate('date', '<=', $to));
        };

        // =========================
        // STOCK
        // =========================
        $inQ  = $window(Offload::with(['tank.depot','tank.product'])->where('client_id',$client->id));
        $outQ = $window(Load::with(['tank.depot','tank.product'])->where('client_id',$client->id));
        $adjQ = $window(Adjustment::with(['tank.depot','tank.product'])->where('client_id',$client->id));

        $totIn  = $this->safeSum($inQ,  ['delivered_20_l','delivered_20','loaded_20_l','volume_20_l','delivered_observed_l','observed_volume']);
        $totOut = $this->safeSum($outQ, ['loaded_20_l','delivered_20_l','delivered_20','volume_20_l','observed_volume','loaded_observed_l']);
        $totAdj = $this->safeSum($adjQ, ['amount_20_l','delivered_20_l','delivered_20','qty_20_l','qty_l','observed_volume']);

        $currentStock = $totIn - $totOut + $totAdj;

        $recentOffloads = (clone $inQ)->orderByDesc('date')->orderByDesc('id')->limit(10)->get();
        $recentLoads    = (clone $outQ)->orderByDesc('date')->orderByDesc('id')->limit(10)->get();

        // =========================
        // BILLING
        // =========================
        $openInvoices = Invoice::where('client_id',$client->id)
            ->where('status','!=','paid')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $openTotal = (float) Invoice::where('client_id',$client->id)
            ->where('status','!=','paid')
            ->sum('total');

        $paidTotal = (float) Invoice::where('client_id',$client->id)
            ->where('status','paid')
            ->sum('total');

        // Use same logic as statement for payments + outstanding
        [$statementRows, $statementMeta] = $this->buildStatementData($client, $request);

        $paymentsTotal = $statementMeta['credits'] ?? 0.0;  // all payments in window
        $balance       = $statementMeta['closing'] ?? 0.0;  // true outstanding

        // Recent payments (still using window helper)
        $paymentsQ = $window(Payment::where('client_id',$client->id));
        $recentPayments = (clone $paymentsQ)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Label
        $windowLabel = ($from || $to)
            ? (($from ?: '…').' → '.($to ?: '…'))
            : 'All time';

        return view('depot-stock::portal.home', compact(
            'client','currentStock','totIn','totOut','totAdj',
            'recentOffloads','recentLoads',
            'openInvoices','openTotal','paidTotal',
            'paymentsTotal','recentPayments','balance',
            'from','to','windowLabel'
        ));
    }

    // =========================================================
    // MOVEMENTS (Offloads + Loads list + export)
    // =========================================================
    public function movements(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        $from = $request->get('from');
        $to   = $request->get('to');

        $window = function ($q) use ($from, $to) {
            return $q
                ->when($from, fn ($qq) => $qq->whereDate('date','>=',$from))
                ->when($to,   fn ($qq) => $qq->whereDate('date','<=',$to));
        };

        $offloadsQ = $window(
            Offload::with(['tank.depot','tank.product'])
                ->where('client_id',$client->id)
        );

        $loadsQ = $window(
            Load::with(['tank.depot','tank.product'])
                ->where('client_id',$client->id)
        );

        $offloads = $offloadsQ
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'offloads_page')
            ->appends($request->except('offloads_page'));

        $loads = $loadsQ
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'loads_page')
            ->appends($request->except('loads_page'));

        $totalOffloaded = $this->safeSum($offloadsQ, [
            'delivered_20_l','delivered_20','loaded_20_l','volume_20_l','delivered_observed_l','observed_volume'
        ]);

        $totalLoaded = $this->safeSum($loadsQ, [
            'loaded_20_l','delivered_20_l','delivered_20','volume_20_l','observed_volume','loaded_observed_l'
        ]);

        $windowLabel = ($from || $to)
            ? (($from ?: '…').' → '.($to ?: '…'))
            : 'All time';

        return view('depot-stock::portal.movements', [
            'client'               => $client,
            'from'                 => $from,
            'to'                   => $to,
            'offloads'             => $offloads,
            'loads'                => $loads,
            'totalOffloadsLitres'  => $totalOffloaded,
            'totalLoadsLitres'     => $totalLoaded,
            'windowLabel'          => $windowLabel,
        ]);
    }

    /**
     * PORTAL EXPORT – MOVEMENTS (CSV only, OFFLOADS then LOADS)
     */
    public function exportMovements(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        $from = $request->get('from');
        $to   = $request->get('to');

        $window = function ($q) use ($from, $to) {
            return $q
                ->when($from, fn($qq) => $qq->whereDate('date', '>=', $from))
                ->when($to,   fn($qq) => $qq->whereDate('date', '<=', $to));
        };

        // Full lists (no pagination)
        $offloads = $window(
            Offload::with(['tank.depot', 'tank.product'])
                ->where('client_id', $client->id)
        )
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $loads = $window(
            Load::with(['tank.depot', 'tank.product'])
                ->where('client_id', $client->id)
        )
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $delimiter = ",";

        $lines = [];

        // ---------------- OFFLOADS ----------------
        $lines[] = 'OFFLOADS (IN)';
        $lines[] = implode($delimiter, [
            'Date','Depot','Product','Tank','Truck','Trailer',
            'Loaded @20 (L)','Delivered @20 (L)','Allowance (L)','Shortfall (L)',
            'Reference','Note'
        ]);

        foreach ($offloads as $o) {
            $date    = optional($o->date)->format('Y-m-d') ?? $o->date;
            $depot   = optional(optional($o->tank)->depot)->name ?? '';
            $product = optional(optional($o->tank)->product)->name ?? '';
            $tank    = optional($o->tank)->name ?? optional($o->tank)->code ?? '';
            $truck   = $o->truck_plate   ?? $o->truck   ?? '';
            $trailer = $o->trailer_plate ?? $o->trailer ?? '';

            $loaded    = (float)($o->loaded_20_l    ?? $o->volume_20_l ?? 0);
            $delivered = (float)($o->delivered_20_l ?? $o->delivered_20 ?? 0);
            $allowance = (float)($o->depot_allowance_20_l ?? 0);
            $shortfall = max($loaded - $delivered, 0);

            $row = [
                $date,$depot,$product,$tank,$truck,$trailer,
                $loaded,$delivered,$allowance,$shortfall,
                $o->reference ?? '', $o->note ?? ''
            ];

            $lines[] = implode($delimiter, array_map(
                fn($v) => '"'.str_replace('"','""',$v).'"',
                $row
            ));
        }

        // blank row
        $lines[] = '';

        // ---------------- LOADS ----------------
        $lines[] = 'LOADS (OUT)';
        $lines[] = implode($delimiter, [
            'Date','Depot','Product','Tank','Truck','Trailer',
            'Loaded @20 (L)','Delivered @20 (L)',
            'Reference','Note'
        ]);

        foreach ($loads as $l) {
            $date    = optional($l->date)->format('Y-m-d') ?? $l->date;
            $depot   = optional(optional($l->tank)->depot)->name ?? '';
            $product = optional(optional($l->tank)->product)->name ?? '';
            $tank    = optional($l->tank)->name ?? optional($l->tank)->code ?? '';
            $truck   = $l->truck_plate   ?? $l->truck   ?? '';
            $trailer = $l->trailer_plate ?? $l->trailer ?? '';

            $loaded    = (float)($l->loaded_20_l ?? $l->volume_20_l ?? 0);
            $delivered = (float)($l->delivered_20_l ?? 0);

            $row = [
                $date,$depot,$product,$tank,$truck,$trailer,
                $loaded,$delivered,
                $l->reference ?? '', $l->note ?? ''
            ];

            $lines[] = implode($delimiter, array_map(
                fn($v) => '"'.str_replace('"','""',$v).'"',
                $row
            ));
        }

        $content  = implode("\r\n", $lines);
        $filename = 'movements_'.$client->id.'_'.now()->format('Ymd_His').'.csv';

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    // =========================================================
    // STATEMENT (Portal view + print + export)
    // =========================================================
    public function statements(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        [$rows, $meta] = $this->buildStatementData($client, $request);

        $from = $meta['from'] ?? null;
        $to   = $meta['to'] ?? null;

        return view('depot-stock::portal.statements', [
            'client'   => $client,
            'rows'     => $rows,
            'meta'     => $meta,
            'from'     => $from,
            'to'       => $to,
            'currency' => config('depot-stock.currency','USD'),
        ]);
    }

    public function statementPrint(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        [$rows, $meta] = $this->buildStatementData($client, $request);

        return view('depot-stock::portal.statement_print', [
            'client' => $client,
            'rows'   => $rows,
            'meta'   => $meta,
        ]);
    }

    /**
     * PORTAL EXPORT – STATEMENT (CSV only)
     */
    public function statementExport(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        [$rows, $meta] = $this->buildStatementData($client, $request);

        $delimiter = ",";

        $lines = [];

        // optional title block
        $lines[] = 'STATEMENT';
        $lines[] = 'Client,'.'"'.str_replace('"','""',$client->name).'"';
        $lines[] = 'From,'.($meta['from'] ?? '');
        $lines[] = 'To,'.($meta['to'] ?? '');
        $lines[] = 'Opening,'.($meta['opening'] ?? 0);
        $lines[] = 'Charges,'.($meta['charges'] ?? 0);
        $lines[] = 'Credits,'.($meta['credits'] ?? 0);
        $lines[] = 'Closing,'.($meta['closing'] ?? 0);
        $lines[] = ''; // blank row

        // header
        $lines[] = implode($delimiter, ['Date','Type','Doc #','Description','Debit','Credit','Balance']);

        foreach ($rows as $r) {
            $row = [
                $r['date']        ?? '',
                $r['type']        ?? '',
                $r['doc_no']      ?? '',
                $r['description'] ?? '',
                $r['debit']       ?? 0,
                $r['credit']      ?? 0,
                $r['balance']     ?? 0,
            ];

            $lines[] = implode($delimiter, array_map(
                fn($v) => '"'.str_replace('"','""',$v).'"',
                $row
            ));
        }

        $content  = implode("\r\n", $lines);
        $filename = 'statement_'.$client->id.'_'.now()->format('Ymd_His').'.csv';

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * Build flat statement lines from invoices + payments for the portal.
     *
     * NOTE: logic left EXACTLY as you provided.
     *
     * @return array{0: array<int,array>, 1: array<string,mixed>}
     */
/**
 * Build flat statement lines from invoices + payments for the portal.
 *
 * @return array{0: array<int,array>, 1: array<string,mixed>}
 */
protected function buildStatementData(Client $client, Request $request): array
{
    $from = $request->get('from');
    $to   = $request->get('to');

    $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
    $toDate   = $to   ? Carbon::parse($to)->endOfDay()     : null;

    // When no from/to given, treat as "all time":
    // opening = 0, and range = all invoices + payments.
    if (!$fromDate && !$toDate) {
        $fromDate = null;
        $toDate   = null;
    }

    // Modes that represent INTERNAL credit movement, not new money
    $creditModes = ['credit', 'credit apply', 'client credit'];

    // Helper to filter date range
    $window = function ($q) use ($fromDate, $toDate) {
        return $q
            ->when($fromDate, fn($qq) => $qq->whereDate('date','>=',$fromDate))
            ->when($toDate,   fn($qq) => $qq->whereDate('date','<=',$toDate));
    };

    // =========================
    // Opening balance
    // =========================
    if ($fromDate) {
        $invBefore = Invoice::where('client_id',$client->id)
            ->whereDate('date','<',$fromDate)
            ->sum('total');

        // Get payments before window and drop pure credit movements
        $payBeforeRows = Payment::where('client_id',$client->id)
            ->whereDate('date','<',$fromDate)
            ->get()
            ->reject(function ($p) use ($creditModes) {
                $mode = strtolower($p->mode ?? '');
                return in_array($mode, $creditModes, true);
            });

        $payBefore = (float) $payBeforeRows->sum('amount');

        $opening = (float) $invBefore - (float) $payBefore;
    } else {
        $opening = 0.0;
    }

    // =========================
    // Window invoices
    // =========================
    $invQ = $window(
        Invoice::where('client_id',$client->id)
    );

    // =========================
    // Window payments (exclude credit / credit-apply)
    // =========================
    $payRows = $window(
        Payment::where('client_id',$client->id)
    )
        ->orderBy('date')
        ->orderBy('id')
        ->get()
        ->reject(function ($p) use ($creditModes) {
            $mode = strtolower($p->mode ?? '');
            return in_array($mode, $creditModes, true);
        });

    $items = [];

    foreach ($invQ->orderBy('date')->orderBy('id')->get() as $inv) {
        $items[] = [
            'date'        => optional($inv->date)->format('Y-m-d') ?? (string)$inv->date,
            'type'        => 'Invoice',
            'doc_no'      => $inv->number ?? ('INV-'.$inv->id),
            'description' => $inv->reference ?? 'Invoice',
            'debit'       => (float) $inv->total,
            'credit'      => 0.0,
            'sort'        => sprintf(
                '1-%s-%06d',
                optional($inv->date)->format('Ymd') ?? '00000000',
                $inv->id
            ),
        ];
    }

    foreach ($payRows as $p) {
        $items[] = [
            'date'        => optional($p->date)->format('Y-m-d') ?? (string)$p->date,
            'type'        => 'Payment',
            'doc_no'      => $p->reference ?? ('PAY-'.$p->id),
            'description' => $p->mode ? strtoupper($p->mode).' payment' : 'Payment',
            'debit'       => 0.0,
            'credit'      => (float) $p->amount,
            'sort'        => sprintf(
                '2-%s-%06d',
                optional($p->date)->format('Ymd') ?? '00000000',
                $p->id
            ),
        ];
    }

    // Sort by date + type
    usort($items, fn($a,$b) => $a['sort'] <=> $b['sort']);

    // Running balance
    $rows       = [];
    $balance    = $opening;
    $charges    = 0.0;
    $credits    = 0.0;

    foreach ($items as $it) {
        $balance += $it['debit'] - $it['credit'];
        $charges += $it['debit'];
        $credits += $it['credit'];

        $rows[] = [
            'date'        => $it['date'],
            'type'        => $it['type'],
            'doc_no'      => $it['doc_no'],
            'description' => $it['description'],
            'debit'       => $it['debit'],
            'credit'      => $it['credit'],
            'balance'     => $balance,
        ];
    }

    $closing = $balance;

    $meta = [
        'from'     => $from,
        'to'       => $to,
        'opening'  => round($opening, 2),
        'charges'  => round($charges, 2),
        'credits'  => round($credits, 2),
        'closing'  => round($closing, 2),
    ];

    return [$rows, $meta];
}

    // =========================================================
    // INVOICES (portal list)
    // =========================================================
    public function invoices(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        $invoices = Invoice::where('client_id',$client->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(30);

        return view('depot-stock::portal.invoices', [
            'client'   => $client,
            'invoices' => $invoices,
            'currency' => config('depot-stock.currency','USD'),
        ]);
    }

    /**
     * PORTAL: show a single invoice (read-only).
     */
    public function invoiceShow(Request $request, Invoice $invoice)
    {
        $user   = $request->user();
        $client = $user?->client;

        abort_unless($client && $invoice->client_id === $client->id, 403);

        $invoice->loadMissing(['client', 'items', 'payments']);

        $offloads = Offload::with(['tank.depot', 'tank.product'])
            ->where('client_id', $client->id)
            ->where('billed_invoice_id', $invoice->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $currency = config('depot-stock.currency', 'USD');

        return view('depot-stock::portal.invoice_show', [
            'client'   => $client,
            'invoice'  => $invoice,
            'offloads' => $offloads,
            'currency' => $currency,
        ]);
    }

    // =========================================================
    // PAYMENTS (portal list)
    // =========================================================
    public function payments(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        $payments = Payment::where('client_id',$client->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(30);

        return view('depot-stock::portal.payments', [
            'client'   => $client,
            'payments' => $payments,
            'currency' => config('depot-stock.currency','USD'),
        ]);
    }

    // =========================================================
    // ACCOUNT / SECURITY (portal)
    // =========================================================
    public function account(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client, 403);

        return view('depot-stock::portal.account', [
            'client' => $client,
            'user'   => $user,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user   = $request->user();
        $client = $user?->client;
        abort_unless($client && $user, 403);

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('status', 'Your password has been updated successfully.');
    }

    // =========================================================
    // Helper
    // =========================================================
    protected function safeSum($q, array $columns)
    {
        foreach ($columns as $c) {
            try {
                $sum = (clone $q)->sum($c);
                if ($sum != 0) return (float) $sum;
            } catch (\Throwable $e) {}
        }
        return 0.0;
    }
}