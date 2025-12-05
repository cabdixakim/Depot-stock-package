@extends('depot-stock::layouts.app')

@section('content')
@php
    // Basic money + balance
    $total     = (float) ($invoice->total ?? 0);
    $paidTotal = (float) ($invoice->paid_total ?? 0);
    $balance   = round($total - $paidTotal, 2);

    $statusStr = strtolower((string) ($invoice->status ?? ''));
    $isSettled = ($balance <= 0.00) || ($statusStr === 'paid');

    $hasCredit = ($credits ?? collect())->count() > 0;

    // If we came from /depot/invoices?client=ID, keep that scope
    $clientScopeId = request()->query('client');

    // Build "offload lines" dataset for Tabulator + export
    $offloadItems = $invoice->items->where('source_type', 'offload');

    // Avoid divide-by-zero when splitting payments pro-rata
    $invoiceTotal = max(0.0000001, (float) $invoice->total);
    $invoicePaid  = max(0.0, min((float) $invoice->paid_total, $invoiceTotal));

    $offloadRows = $offloadItems->map(function ($it) use ($invoiceTotal, $invoicePaid) {
        $meta = (array) ($it->meta ?? []);

        $lineTotal = (float) ($it->amount ?? 0);
        $share     = $lineTotal > 0 ? $lineTotal / $invoiceTotal : 0;
        $linePaid  = round($invoicePaid * $share, 2);
        $lineBal   = round($lineTotal - $linePaid, 2);

        // Try a few common keys for truck / trailer. If not present, will just be null.
        $truck   = $meta['truck']      ?? $meta['truck_reg']   ?? $meta['truck_no']   ?? null;
        $trailer = $meta['trailer']    ?? $meta['trailer_reg'] ?? $meta['trailer_no'] ?? null;

        return [
            'date'       => (string) $it->date,
            'depot'      => $meta['depot']    ?? null,
            'product'    => $meta['product']  ?? null,
            'tank'       => $meta['tank_id']  ?? null,
            'truck'      => $truck,
            'trailer'    => $trailer,
            'ref'        => $meta['ref']      ?? null,
            'litres'     => (float) ($it->litres ?? 0),
            'rate'       => (float) ($it->rate_per_litre ?? 0),
            'line_total' => $lineTotal,
            'paid'       => $linePaid,
            'balance'    => $lineBal,
        ];
    })->values();

    $totalOffloadLitres = (float) $offloadRows->sum('litres');
@endphp

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

  {{-- HEADER --}}
  <div class="flex flex-wrap justify-between items-center gap-3">
    <div>
      {{-- Back --}}
      <button type="button"
              onclick="window.history.back()"
              class="mb-2 inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs text-gray-600 hover:bg-gray-50">
        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
          <path d="M12.293 4.293a1 1 0 010 1.414L9 9h7a1 1 0 110 2H9l3.293 3.293a1 1 0 11-1.414 1.414l-4.999-5a1 1 0 010-1.414l5-5a1 1 0 011.414 0z" />
        </svg>
        <span>Back</span>
      </button>

      <div class="flex items-center gap-2">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">
            Invoice <span class="text-amber-600">{{ $invoice->number }}</span>
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Date: {{ $invoice->date }} • Client:
            <span class="font-medium">{{ $invoice->client->name }}</span>
          </p>
        </div>

        {{-- Scope pill --}}
        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-[11px] text-gray-700">
          <span class="h-1.5 w-1.5 rounded-full {{ $clientScopeId ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
          @if($clientScopeId)
            From client scope
          @else
            From all clients
          @endif
        </span>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      {{-- All invoices keeps client scope if present --}}
      <a href="{{ $clientScopeId
                  ? route('depot.invoices.index', ['client' => $clientScopeId])
                  : route('depot.invoices.index') }}"
         class="px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm">
        All Invoices
      </a>

      {{-- Record Payment --}}
      @if(!$isSettled)
        <button id="btnRecordPayment"
                class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm shadow-sm">
          Record Payment
        </button>
      @endif

      {{-- Apply Credit --}}
      @if(!$isSettled && $hasCredit)
        <button id="btnApplyCredit"
                class="px-3 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700 text-sm shadow-sm">
          Apply Credit
        </button>
      @endif
    </div>
  </div>

  {{-- STATUS + TOTALS --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="rounded-xl border p-4 bg-gray-50">
      <div class="text-xs text-gray-500 uppercase">Status</div>
      <div class="mt-1 text-lg font-semibold text-gray-900">{{ strtoupper($invoice->status) }}</div>
    </div>
    <div class="rounded-xl border p-4 bg-gray-50">
      <div class="text-xs text-gray-500 uppercase">Subtotal</div>
      <div class="mt-1 text-lg font-semibold text-gray-900">
        USD {{ number_format($total, 2) }}
      </div>
    </div>
    <div class="rounded-xl border p-4 bg-gray-50">
      <div class="text-xs text-gray-500 uppercase">Paid</div>
      <div class="mt-1 text-lg font-semibold text-emerald-600">
        USD {{ number_format($paidTotal, 2) }}
      </div>
    </div>
    <div class="rounded-xl border p-4 bg-gray-50">
      <div class="text-xs text-gray-500 uppercase">Balance</div>
      <div class="mt-1 text-lg font-semibold {{ $isSettled ? 'text-emerald-600' : 'text-rose-600' }}">
        USD {{ number_format(max(0, $balance), 2) }}
      </div>
    </div>
  </div>

  {{-- CREDITS INFO --}}
  @if(!$isSettled && $hasCredit)
    <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
      <div class="flex justify-between items-center">
        <div>
          <h2 class="text-sky-700 font-semibold text-sm">Available Client Credits</h2>
          <p class="text-[12px] text-sky-900/80 mt-0.5">
            You can apply credit to reduce this invoice balance. Credit will never exceed the invoice total.
          </p>
        </div>
        <button id="btnApplyCredit2"
                class="px-3 py-1.5 rounded-lg bg-sky-600 text-white text-xs hover:bg-sky-700">
          Apply Credit
        </button>
      </div>
      <ul class="mt-3 text-sm text-sky-900 space-y-1">
        @foreach($credits as $c)
          <li class="flex justify-between">
            <span>#{{ $c->id }} — {{ $c->currency }} {{ number_format($c->remaining, 2) }}</span>
            <span class="text-xs text-gray-600">{{ $c->reason ?? 'Credit' }}</span>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- LINKED OFFLOADS – COLLAPSIBLE TABULATOR GRID --}}
  <div class="rounded-2xl bg-white shadow border">
    <details class="group" open>
      <summary class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none">
        <div class="flex items-center gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-500">Linked offloads</div>
            <div class="text-sm text-slate-700">
              {{ $offloadRows->count() }} lines •
              <span class="font-semibold">{{ number_format($totalOffloadLitres, 1) }} L</span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          @if($offloadRows->count())
            <div class="inline-flex items-center gap-1 rounded-full bg-slate-900 text-white px-2.5 py-1 shadow">
              <button type="button"
                      id="btnOffloadsCsv"
                      class="text-[11px] px-1.5 py-0.5 rounded-full hover:bg-slate-800">
                CSV
              </button>
              <span class="h-3 w-px bg-slate-700"></span>
              <button type="button"
                      id="btnOffloadsXlsx"
                      class="text-[11px] px-1.5 py-0.5 rounded-full hover:bg-emerald-500/20">
                Excel
              </button>
              <span class="h-3 w-px bg-slate-700"></span>
              <button type="button"
                      id="btnOffloadsPdf"
                      class="text-[11px] px-1.5 py-0.5 rounded-full hover:bg-rose-500/20">
                PDF
              </button>
            </div>
          @endif

          <div class="flex items-center gap-1 text-[11px] text-slate-500">
            <span class="group-open:hidden">Show table</span>
            <span class="hidden group-open:inline">Hide table</span>
            <svg class="h-3.5 w-3.5 text-slate-500 transition-transform duration-200 group-open:rotate-180"
                 viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                    clip-rule="evenodd" />
            </svg>
          </div>
        </div>
      </summary>

      <div class="border-t border-gray-200">
        {{-- Summary strip above the grid (filter-aware totals) --}}
        <div id="offloadSummaryBar"
             class="px-4 py-2 border-b border-gray-200 text-[11px] text-slate-600 flex flex-wrap gap-3 items-center justify-between">
          <div class="flex flex-wrap gap-3">
            <span>
              <span class="font-semibold" id="sumLitres">0.0</span> L
            </span>
            <span>
              Value:
              <span class="font-semibold" id="sumLineTotal">USD 0.00</span>
            </span>
            <span>
              Paid (pro-rata):
              <span class="font-semibold" id="sumPaid">USD 0.00</span>
            </span>
            <span>
              Balance:
              <span class="font-semibold" id="sumBalance">USD 0.00</span>
            </span>
          </div>
          <span class="text-[10px] text-slate-400">
            Totals reflect current filters
          </span>
        </div>

        <div id="offloadGrid" class="tabulator-wrapper"></div>
      </div>
    </details>
  </div>

  {{-- PAYMENTS --}}
  <div class="bg-white rounded-2xl shadow border">
    <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
      <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">Payments</div>
        <div class="text-sm text-gray-700">
          {{ $invoice->payments->count() }} entries •
          <span class="font-medium text-emerald-700">
            USD {{ number_format($invoice->payments->sum('amount'), 2) }}
          </span>
        </div>
      </div>
      @if(!$isSettled)
        <button id="btnRecordPayment2"
                class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs hover:bg-emerald-700">
          Add payment
        </button>
      @endif
    </div>
    <table class="w-full text-sm">
      <thead class="bg-gray-100 text-xs uppercase text-gray-600">
      <tr>
        <th class="p-3 text-left">Date</th>
        <th class="p-3 text-left">Method</th>
        <th class="p-3 text-right">Amount</th>
        <th class="p-3 text-left">Reference</th>
      </tr>
      </thead>
      <tbody>
      @forelse($invoice->payments as $p)
        <tr class="border-b hover:bg-gray-50">
          <td class="p-3">{{ $p->date }}</td>
          <td class="p-3">{{ $p->mode }}</td>
          <td class="p-3 text-right text-emerald-600">{{ number_format($p->amount, 2) }}</td>
          <td class="p-3">{{ $p->reference }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="p-4 text-center text-gray-400">No payments yet.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- RECORD PAYMENT MODAL --}}
@if(!$isSettled)
<div id="recordPaymentModal" class="fixed inset-0 z-[120] hidden">
  <button type="button" class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-rp-close></button>
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-emerald-100/80">
      <div class="flex items-center justify-between border-b px-5 py-3 bg-emerald-50/70 rounded-t-2xl">
        <div>
          <h3 class="font-semibold text-gray-900 text-sm">Record Payment</h3>
          <p class="text-[11px] text-emerald-800/80 mt-0.5">
            Log a payment for this invoice. Any amount above the balance will be stored as client credit.
          </p>
        </div>
        <button type="button" class="text-gray-400 hover:text-gray-700" data-rp-close>✕</button>
      </div>

      <form id="recordPaymentForm" class="p-5 space-y-4" action="{{ route('depot.payments.store') }}" method="POST">
        @csrf
        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
        <input type="hidden" name="client_id"  value="{{ $invoice->client_id }}">

        <div class="flex justify-between items-center mb-1">
          <span class="text-xs text-gray-500">
            Outstanding: <span class="font-semibold text-gray-800">USD {{ number_format(max(0,$balance),2) }}</span>
          </span>
          <button type="button"
                  id="btnPayFull"
                  class="text-[11px] inline-flex items-center gap-1 rounded-full bg-emerald-600/10 text-emerald-700 px-2.5 py-0.5 border border-emerald-200 hover:bg-emerald-600/15">
            <span>Pay full invoice</span>
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-500">Date</label>
            <input type="date" name="date" value="{{ now()->toDateString() }}"
                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
          </div>
          <div>
            <label class="text-xs text-gray-500">Method</label>
            <select name="mode"
                    class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
              <option value="Cash">Cash</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Cheque">Cheque</option>
              <option value="Mobile Money">Mobile Money</option>
            </select>
          </div>
        </div>

        <div>
          <label class="text-xs text-gray-500">Amount (USD)</label>
          <input id="rpAmount" type="number" step="0.01" name="amount"
                 class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                 placeholder="0.00">
          <div id="rpHint" class="hidden mt-1 rounded-md border border-amber-200 bg-amber-50 text-amber-800 px-2 py-1 text-[12px]"></div>
        </div>

        <div>
          <label class="text-xs text-gray-500">Reference / Notes</label>
          <input type="text" name="reference"
                 class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                 placeholder="Optional reference">
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 mt-1">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm" data-rp-close>
            Cancel
          </button>
          <button id="rpSubmit" type="submit"
                  class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow text-sm">
            Save Payment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

{{-- APPLY CREDIT MODAL --}}
@if(!$isSettled && $hasCredit)
<div id="applyCreditModal" class="fixed inset-0 z-[120] hidden">
  <button type="button" class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-ac-close></button>
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100">
      <div class="flex items-center justify-between border-b px-5 py-3 bg-gray-50 rounded-t-2xl">
        <div>
          <h3 class="font-semibold text-gray-900 text-sm">Apply Credit</h3>
          <p class="text-[11px] text-gray-500 mt-0.5">
            Use available client credit to reduce this invoice balance.
          </p>
        </div>
        <button type="button" class="text-gray-400 hover:text-gray-700" data-ac-close>✕</button>
      </div>

      <form id="applyCreditForm" class="p-5 space-y-4"
            action="{{ route('depot.invoices.apply_credit', $invoice) }}" method="POST">
        @csrf

        <div class="flex justify-between items-center mb-1">
          <span class="text-xs text-gray-500">
            Outstanding: <span class="font-semibold text-gray-800">USD {{ number_format(max(0,$balance),2) }}</span>
          </span>
          <button type="button"
                  id="btnCreditFull"
                  class="text-[11px] inline-flex items-center gap-1 rounded-full bg-sky-600/10 text-sky-700 px-2.5 py-0.5 border border-sky-200 hover:bg-sky-600/15">
            Max for this invoice
          </button>
        </div>

        <div>
          <label class="text-xs text-gray-500">Credit</label>
          <select id="creditSelect" name="credit_id"
                  class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
            @foreach($credits as $c)
              <option value="{{ $c->id }}"
                      data-remaining="{{ number_format($c->remaining, 2, '.', '') }}"
                      data-currency="{{ $c->currency }}">
                #{{ $c->id }} — {{ $c->currency }} {{ number_format($c->remaining,2) }}
              </option>
            @endforeach
          </select>
          <div id="creditAvailNote" class="mt-1 text-[12px] text-gray-500"></div>
        </div>

        <div>
          <label class="text-xs text-gray-500">Amount</label>
          <input id="creditAmount" type="number" step="0.01" name="amount"
                 class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none"
                 placeholder="0.00">
          <div id="creditHint" class="hidden mt-1 rounded-md border border-amber-200 bg-amber-50 text-amber-800 px-2 py-1 text-[12px]"></div>
        </div>

        <div>
          <label class="text-xs text-gray-500">Notes</label>
          <input type="text" name="notes"
                 class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none"
                 placeholder="Optional internal note">
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 mt-1">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm" data-ac-close>
            Cancel
          </button>
          <button id="acSubmit" type="submit"
                  class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700 shadow text-sm">
            Apply Credit
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection

@push('styles')
<link rel="stylesheet"
      href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css">

<style>
  /* Excel-y Tabulator look with full grid borders */
  .tabulator-wrapper .tabulator {
    border-radius: 0;
    border: 1px solid #e5e7eb; /* outer border */
    font-size: 12px;
  }

  .tabulator-wrapper .tabulator-header {
    background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
    border-bottom: 1px solid #e5e7eb;
  }

  .tabulator-wrapper .tabulator-header .tabulator-col {
    border-right: 1px solid #e5e7eb;
  }
  .tabulator-wrapper .tabulator-header .tabulator-col:first-child {
    border-left: 1px solid #e5e7eb;
  }

  .tabulator-wrapper .tabulator-row {
    border-bottom: 1px solid #e5e7eb;
  }

  .tabulator-wrapper .tabulator-row .tabulator-cell {
    border-right: 1px solid #e5e7eb;
  }
  .tabulator-wrapper .tabulator-row .tabulator-cell:first-child {
    border-left: 1px solid #e5e7eb;
  }

  .tabulator-wrapper .tabulator-row:nth-child(even) {
    background-color: #fcfcfc;
  }
  .tabulator-wrapper .tabulator-row.tabulator-row-hover {
    background-color: #eef2ff;
  }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const invoiceBalance = {{ json_encode(max(0, $balance)) }};
  const offloadData    = @json($offloadRows);

  // --- tiny toast ---
  function toast(msg, tone = 'ok') {
    let wrap = document.getElementById('toastWrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'toastWrap';
      Object.assign(wrap.style, {
        position:'fixed', top:'16px', right:'16px', zIndex:'9999',
        display:'flex', flexDirection:'column', gap:'8px'
      });
      document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    const palette = tone === 'err'
      ? {bg:'linear-gradient(135deg,#ef4444,#dc2626)', shadow:'rgba(239,68,68,0.25)'}
      : {bg:'linear-gradient(135deg,#10b981,#059669)', shadow:'rgba(16,185,129,0.25)'};
    Object.assign(el.style, {
      background: palette.bg, color:'#fff', padding:'10px 14px',
      borderRadius:'10px', boxShadow:`0 8px 20px ${palette.shadow}`,
      fontSize:'13px', fontWeight:'600'
    });
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(() => {
      el.style.transition = 'opacity .25s ease, transform .25s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 260);
    }, 2200);
  }

  // Formatting helpers
  function formatMoney(v, ccy) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: ccy
      }).format(v);
    } catch {
      const num = Number(v) || 0;
      return `${ccy} ${num.toFixed(2)}`;
    }
  }

  function formatNumber(v, decimals) {
    const num = Number(v) || 0;
    return num.toLocaleString(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    });
  }

  // --- Tabulator for linked offloads (auto-height, Excel-ish, fit to content) ---
  let offloadGridInstance = null;
  if (window.Tabulator && document.getElementById('offloadGrid')) {
    offloadGridInstance = new Tabulator("#offloadGrid", {
      data: offloadData,
      height: "fitData",       // hug content vertically
      maxHeight: "360px",      // scroll if tall
      layout: "fitData",       // columns sized to fit header/content
      columnHeaderVertAlign: "middle",
      placeholder: "No offloads linked to this invoice yet.",
      columns: [
        {title: "Date", field: "date", sorter: "date"},
        {title: "Depot", field: "depot"},
        {title: "Product", field: "product"},
        {title: "Truck", field: "truck", headerHozAlign:"center"},
        {title: "Trailer", field: "trailer", headerHozAlign:"center"},
        {title: "Ref", field: "ref"},
        {title: "Tank", field: "tank", hozAlign:"center"},
        {
          title: "Litres",
          field: "litres",
          hozAlign: "right",
          sorter: "number",
          formatter: "money",
          formatterParams: {precision: 1, thousand:",", decimal:"."},
          bottomCalc: "sum",
          bottomCalcFormatter: "money",
          bottomCalcFormatterParams: {precision: 1, thousand:",", decimal:"."}
        },
        {
          title: "Rate",
          field: "rate",
          hozAlign: "right",
          sorter: "number",
          formatter: "money",
          formatterParams: {precision: 3, symbol:"USD ", thousand:",", decimal:"."}
        },
        {
          title: "Line total",
          field: "line_total",
          hozAlign: "right",
          sorter: "number",
          formatter: "money",
          formatterParams: {precision: 2, symbol:"USD ", thousand:",", decimal:"."},
          bottomCalc: "sum",
          bottomCalcFormatter: "money",
          bottomCalcFormatterParams: {precision: 2, symbol:"USD ", thousand:",", decimal:"."}
        },
        {
          title: "Paid (pro-rata)",
          field: "paid",
          hozAlign: "right",
          sorter: "number",
          formatter: "money",
          formatterParams: {precision: 2, symbol:"USD ", thousand:",", decimal:"."},
          bottomCalc: "sum",
          bottomCalcFormatter: "money",
          bottomCalcFormatterParams: {precision: 2, symbol:"USD ", thousand:",", decimal:"."}
        },
        {
          title: "Balance",
          field: "balance",
          hozAlign: "right",
          sorter: "number",
          formatter: "money",
          formatterParams: {precision: 2, symbol:"USD ", thousand:",", decimal:"."},
          bottomCalc: "sum",
          bottomCalcFormatter: "money",
          bottomCalcFormatterParams: {precision: 2, symbol:"USD ", thousand:",", decimal:"."}
        },
      ],
    });

    // --- Summary strip above grid (filter-aware totals) ---
    const sumLitresEl    = document.getElementById('sumLitres');
    const sumLineTotalEl = document.getElementById('sumLineTotal');
    const sumPaidEl      = document.getElementById('sumPaid');
    const sumBalanceEl   = document.getElementById('sumBalance');

    function refreshOffloadSummary() {
      if (!offloadGridInstance) return;
      // "active" = filtered + sorted + visible data
      const rows = offloadGridInstance.getData("active") || [];

      let totalLitres = 0;
      let totalLine   = 0;
      let totalPaid   = 0;
      let totalBal    = 0;

      rows.forEach(r => {
        totalLitres += Number(r.litres)     || 0;
        totalLine   += Number(r.line_total) || 0;
        totalPaid   += Number(r.paid)       || 0;
        totalBal    += Number(r.balance)    || 0;
      });

      if (sumLitresEl)    sumLitresEl.textContent    = formatNumber(totalLitres, 1);
      if (sumLineTotalEl) sumLineTotalEl.textContent = formatMoney(totalLine, "USD");
      if (sumPaidEl)      sumPaidEl.textContent      = formatMoney(totalPaid, "USD");
      if (sumBalanceEl)   sumBalanceEl.textContent   = formatMoney(totalBal, "USD");
    }

    offloadGridInstance.on("dataLoaded",   refreshOffloadSummary);
    offloadGridInstance.on("dataFiltered", refreshOffloadSummary);
    offloadGridInstance.on("dataChanged",  refreshOffloadSummary);
    refreshOffloadSummary();

    // --- Exports (CSV / XLSX / PDF) ---
    const baseFilename = `invoice_{{ $invoice->number }}_offloads.csv`;

    function bindOffloadExport(buttonId, type) {
      const btn = document.getElementById(buttonId);
      if (!btn || !offloadGridInstance) return;

      btn.addEventListener('click', () => {
        if (!offloadGridInstance) return;
        if (type === 'csv') {
          offloadGridInstance.download("csv", baseFilename);
        } else if (type === 'xlsx') {
          offloadGridInstance.download("xlsx", baseFilename.replace(/\.csv$/i, '.xlsx'), {
            sheetName: "Offloads"
          });
        } else if (type === 'pdf') {
          offloadGridInstance.download("pdf", baseFilename.replace(/\.csv$/i, '.pdf'), {
            orientation: "landscape",
            title: "Offloads for invoice {{ $invoice->number }}"
          });
        }
      });
    }

    bindOffloadExport('btnOffloadsCsv',  'csv');
    bindOffloadExport('btnOffloadsXlsx', 'xlsx');
    bindOffloadExport('btnOffloadsPdf',  'pdf');
  }

  // --- Generic modal helpers (click-outside & ESC) ---
  function setupModalRoot(rootId, overlaySelector, closeAttrPrefix) {
    const root = document.getElementById(rootId);
    if (!root) return;

    const overlay      = root.querySelector(overlaySelector);
    const closeButtons = root.querySelectorAll(`[data-${closeAttrPrefix}-close]`);

    function close() {
      root.classList.add('hidden');
    }

    function open() {
      root.classList.remove('hidden');
    }

    if (overlay) {
      overlay.addEventListener('click', close);
    }
    closeButtons.forEach(btn => btn.addEventListener('click', close));

    // ESC closes if open
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !root.classList.contains('hidden')) {
        close();
      }
    });

    return { open, close };
  }

  // --- RECORD PAYMENT MODAL + full-pay helper ---
  const rpmRoot    = document.getElementById('recordPaymentModal');
  const rpAmount   = document.getElementById('rpAmount');
  const rpHint     = document.getElementById('rpHint');
  const rpControls = rpmRoot ? setupModalRoot('recordPaymentModal', '[data-rp-close]', 'rp') : null;

  function openRpm() {
    if (!rpControls) return;
    rpControls.open();
    setTimeout(() => rpAmount?.focus(), 40);
  }

  document.getElementById('btnRecordPayment')?.addEventListener('click', openRpm);
  document.getElementById('btnRecordPayment2')?.addEventListener('click', openRpm);

  document.getElementById('btnPayFull')?.addEventListener('click', () => {
    if (!rpAmount) return;
    rpAmount.value = invoiceBalance.toFixed(2);
    rpHint.classList.add('hidden');
    rpHint.textContent = '';
  });

  function reflectRpExceed() {
    if (!rpAmount || !rpHint) return;
    const raw = parseFloat(rpAmount.value || '0') || 0;
    if (raw > invoiceBalance && invoiceBalance > 0) {
      const extra = (raw - invoiceBalance).toFixed(2);
      rpHint.textContent =
        `Entered amount exceeds outstanding balance. Extra USD ${extra} will be added as client credit.`;
      rpHint.classList.remove('hidden');
    } else {
      rpHint.classList.add('hidden');
      rpHint.textContent = '';
    }
  }
  rpAmount?.addEventListener('input', reflectRpExceed);

  document.getElementById('recordPaymentForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const fd   = new FormData(form);

    // No capping anymore – allow overpayment so extra becomes credit
    const raw = parseFloat(fd.get('amount') || '0') || 0;
    const cleaned = Math.max(0, raw);
    fd.set('amount', cleaned.toFixed(2));

    const btn  = form.querySelector('button[type="submit"]');
    const prev = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    try {
      const res  = await fetch(form.action, { method:'POST', headers:{'Accept':'application/json'}, body:fd });
      if (res.ok) { location.reload(); return; }
      const t = await res.text();
      toast('Failed to save payment', 'err');
      console.error(t);
    } catch (err) {
      toast('Network error', 'err');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = prev; }
    }
  });

  // --- APPLY CREDIT MODAL + full-credit helpers ---
  const acRoot     = document.getElementById('applyCreditModal');
  const cs         = document.getElementById('creditSelect');
  const ca         = document.getElementById('creditAmount');
  const ch         = document.getElementById('creditHint');
  const cav        = document.getElementById('creditAvailNote');
  const acControls = acRoot ? setupModalRoot('applyCreditModal', '[data-ac-close]', 'ac') : null;

  function openAcm() {
    if (!acControls) return;
    acControls.open();
    refreshAvailNote();
    setTimeout(() => ca?.focus(), 40);
  }

  document.getElementById('btnApplyCredit')?.addEventListener('click', openAcm);
  document.getElementById('btnApplyCredit2')?.addEventListener('click', openAcm);

  function currentRemaining() {
    if (!cs) return 0;
    const opt = cs.options[cs.selectedIndex];
    return parseFloat(opt?.getAttribute('data-remaining') || '0') || 0;
  }
  function currentCurrency() {
    if (!cs) return 'USD';
    const opt = cs.options[cs.selectedIndex];
    return (opt?.getAttribute('data-currency') || 'USD').toUpperCase();
  }
  function maxForCurrentCredit() {
    return Math.min(currentRemaining(), invoiceBalance);
  }

  function refreshAvailNote() {
    if (!cav) return;
    const rem = currentRemaining();
    const ccy = currentCurrency();
    const max = maxForCurrentCredit();
    cav.textContent =
      `Available on this credit: ${formatMoney(rem, ccy)} • Max usable on this invoice: ${formatMoney(max, ccy)}`;
  }
  function reflectCreditExceed() {
    if (!ca || !ch) return;
    const amt = parseFloat(ca.value || '0') || 0;
    const max = maxForCurrentCredit();
    const ccy = currentCurrency();
    if (amt > max) {
      ch.textContent = `Amount cannot exceed ${formatMoney(max, ccy)} for this invoice. It will be capped automatically.`;
      ch.classList.remove('hidden');
    } else {
      ch.classList.add('hidden');
      ch.textContent = '';
    }
  }

  document.getElementById('btnCreditFull')?.addEventListener('click', () => {
    if (!ca) return;
    const max = maxForCurrentCredit();
    ca.value = max.toFixed(2);
    reflectCreditExceed();
  });

  cs?.addEventListener('change', () => { refreshAvailNote(); reflectCreditExceed(); });
  ca?.addEventListener('input', reflectCreditExceed);
  if (cs) refreshAvailNote();

  document.getElementById('applyCreditForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const fd   = new FormData(form);

    const raw = parseFloat(fd.get('amount') || '0') || 0;
    const max = maxForCurrentCredit();
    const clamped = Math.max(0, Math.min(raw, max));
    fd.set('amount', clamped.toFixed(2));

    const btn  = form.querySelector('button[type="submit"]');
    const prev = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Applying…'; }

    try {
      const res  = await fetch(form.action, { method:'POST', headers:{'Accept':'application/json'}, body:fd });
      let data = null;
      try { data = await res.json(); } catch {}
      if (res.ok && (data?.ok ?? true)) {
        toast(`Credit applied: ${clamped.toFixed(2)}`);
        location.reload();
        return;
      }
      toast((data?.message || 'Error applying credit'), 'err');
      console.error(data);
    } catch (err) {
      toast('Network error', 'err');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = prev; }
    }
  });
});
</script>
@endpush