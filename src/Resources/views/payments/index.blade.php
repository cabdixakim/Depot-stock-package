@extends('depot-stock::layouts.app')

@section('title', 'Payments')

@section('content')
@php
  // ---------- Helpers ----------
  $money = function($v, $ccy = 'USD'){
    return $ccy.' '.number_format((float)$v, 2, '.', ',');
  };

  $payments = $payments ?? collect();

  $totalAmt = $payments->sum(fn($p)=>(float)($p->amount ?? 0));
  // If your Payment has a currency column per row, this summary assumes same ccy
  $ccy = count($payments) ? ($payments->first()->currency ?? 'USD') : 'USD';
@endphp

<div class="min-h-[100dvh] bg-[#F7FAFC]">
  {{-- Sticky header --}}
  <div class="sticky top-0 z-20 bg-white/70 backdrop-blur border-b border-gray-100">
    <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between">
      <div class="leading-tight">
        <div class="text-[11px] uppercase tracking-wide text-gray-500">Billing</div>
        <h1 class="font-semibold text-gray-900">Payments</h1>
      </div>

      <div class="flex items-center gap-2">
        <button type="button" id="btnOpenNewPayment"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 text-white px-3 py-2 text-sm hover:bg-indigo-700 shadow-sm">
          <svg class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M12 5v14M5 12h14"/>
          </svg>
          New Payment
        </button>
      </div>
    </div>
  </div>

  <div class="mx-auto max-w-7xl px-4 md:px-6 py-6 space-y-6">

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="text-[11px] uppercase tracking-wide text-gray-500">Payments</div>
        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($payments->count()) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Total records</div>
      </div>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="text-[11px] uppercase tracking-wide text-gray-500">Received</div>
        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($totalAmt, $ccy) }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Sum of payments shown</div>
      </div>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="text-[11px] uppercase tracking-wide text-gray-500">Currency</div>
        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $ccy }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Display currency</div>
      </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 p-4">
      <div class="flex flex-col md:flex-row md:items-end gap-3">
        <div class="w-full md:w-72">
          <label class="block text-[11px] uppercase tracking-wide text-gray-500">Search</label>
          <div class="mt-1 relative">
            <input id="q" type="text" placeholder="Invoice number, client name, mode, reference…"
                   class="w-full rounded-xl border border-gray-200 pl-9 pr-3 py-2 focus:outline-none focus:border-gray-400">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/>
            </svg>
          </div>
        </div>

        <div>
          <label class="block text-[11px] uppercase tracking-wide text-gray-500">From</label>
          <input id="from" type="date"
                 class="mt-1 rounded-xl border border-gray-200 py-2 px-3 focus:border-gray-400">
        </div>
        <div>
          <label class="block text-[11px] uppercase tracking-wide text-gray-500">To</label>
          <input id="to" type="date"
                 class="mt-1 rounded-xl border border-gray-200 py-2 px-3 focus:border-gray-400">
        </div>
        <div class="md:ml-auto flex gap-2">
          <button id="btnReset"
                  class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm hover:bg-gray-50">
            Reset
          </button>
        </div>
      </div>
    </div>

    {{-- List --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
      {{-- Desktop table --}}
      <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gradient-to-b from-gray-50 to-white border-b border-gray-200">
            <tr>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Invoice</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Client</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Date</th>
              <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide">Amount</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Mode</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Reference</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Notes</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody id="tblBody" class="divide-y divide-gray-100">
            @forelse($payments as $p)
              @php
                $inv = $p->invoice;
                $cli = $p->client;
                $ccyRow = $p->currency ?? ($inv->currency ?? 'USD');
              @endphp
              <tr class="pay-row hover:bg-gray-50 transition"
                  data-inv="{{ $inv->number ?? '' }}"
                  data-cli="{{ $cli->name ?? '' }}"
                  data-mode="{{ strtolower($p->mode ?? '') }}"
                  data-ref="{{ strtolower($p->reference ?? '') }}"
                  data-date="{{ optional($p->date)->format('Y-m-d') }}">
                <td class="px-3 py-3">
                  @if($inv)
                    <a href="{{ route('depot.invoices.show', $inv) }}"
                       class="text-indigo-600 hover:underline font-medium">{{ $inv->number }}</a>
                  @else
                    <span class="text-gray-400">—</span>
                  @endif
                </td>
                <td class="px-3 py-3 text-gray-800">{{ $cli->name ?? '—' }}</td>
                <td class="px-3 py-3 text-gray-700">{{ optional($p->date)->format('Y-m-d') ?? '—' }}</td>
                <td class="px-3 py-3 text-right font-semibold text-gray-900">{{ $money($p->amount, $ccyRow) }}</td>
                <td class="px-3 py-3">
                  <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-800 px-2 py-0.5 text-[11px]">
                    {{ strtoupper($p->mode ?? '-') }}
                  </span>
                </td>
                <td class="px-3 py-3 text-gray-700">{{ $p->reference ?? '—' }}</td>
                <td class="px-3 py-3 text-gray-600">{{ $p->notes ?? '—' }}</td>
                <td class="px-3 py-3 text-right">
                  @if($inv)
                    <a href="{{ route('depot.invoices.show', $inv) }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                      View Invoice
                    </a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="px-3 py-10 text-center text-gray-500">No payments found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Mobile cards --}}
      <div id="cardsBody" class="md:hidden divide-y divide-gray-100">
        @foreach($payments as $p)
          @php
            $inv = $p->invoice; $cli = $p->client;
            $ccyRow = $p->currency ?? ($inv->currency ?? 'USD');
          @endphp
          <div class="p-4 pay-card" data-inv="{{ $inv->number ?? '' }}"
               data-cli="{{ $cli->name ?? '' }}"
               data-mode="{{ strtolower($p->mode ?? '') }}"
               data-ref="{{ strtolower($p->reference ?? '') }}"
               data-date="{{ optional($p->date)->format('Y-m-d') }}">
            <div class="flex items-center justify-between">
              <div class="font-semibold text-gray-900">
                @if($inv)
                  <a href="{{ route('depot.invoices.show', $inv) }}" class="text-indigo-600 hover:underline">{{ $inv->number }}</a>
                @else
                  —
                @endif
              </div>
              <div class="text-[11px] text-gray-500">{{ optional($p->date)->format('Y-m-d') ?? '—' }}</div>
            </div>
            <div class="text-[13px] text-gray-700 mt-0.5">{{ $cli->name ?? '—' }}</div>
            <div class="mt-2 grid grid-cols-3 gap-2 text-[12px]">
              <div>
                <div class="text-gray-400">Amount</div>
                <div class="font-medium text-gray-900">{{ $money($p->amount, $ccyRow) }}</div>
              </div>
              <div>
                <div class="text-gray-400">Mode</div>
                <div class="font-medium text-gray-800">{{ strtoupper($p->mode ?? '-') }}</div>
              </div>
              <div>
                <div class="text-gray-400">Reference</div>
                <div class="font-medium text-gray-700 truncate">{{ $p->reference ?? '—' }}</div>
              </div>
            </div>
            @if($inv)
              <div class="mt-2">
                <a href="{{ route('depot.invoices.show', $inv) }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                  View Invoice
                </a>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>

{{-- ===== New Payment Modal ===== --}}
<div id="payModal" class="fixed inset-0 z-[120] hidden">
  <button type="button" class="absolute inset-0 bg-black/50 backdrop-blur-sm" data-pay-close></button>
  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl ring-1 ring-gray-100">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50 rounded-t-2xl">
        <h3 class="font-semibold text-gray-900">Record Payment</h3>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-pay-close>✕</button>
      </div>

      <form id="payForm" method="POST" action="{{ route('depot.payments.store') }}" class="p-6 space-y-5 text-sm">
        @csrf

        <div id="payAlert" class="hidden rounded-lg border px-3 py-2 text-sm"></div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Invoice ID</label>
            <input name="invoice_id" type="number" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 12">
            <p class="pay-err pay-err-invoice_id hidden text-xs text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Client ID</label>
            <input name="client_id" type="number" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 7">
            <p class="pay-err pay-err-client_id hidden text-xs text-rose-600 mt-1"></p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Amount</label>
            <input name="amount" type="number" step="0.01" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="100.00">
            <p class="pay-err pay-err-amount hidden text-xs text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Currency</label>
            <input name="currency" type="text" maxlength="3" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="USD">
            <p class="pay-err pay-err-currency hidden text-xs text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Date</label>
            <input name="date" type="date" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <p class="pay-err pay-err-date hidden text-xs text-rose-600 mt-1"></p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Mode</label>
            <input name="mode" type="text" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="cash / bank">
            <p class="pay-err pay-err-mode hidden text-xs text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Reference</label>
            <input name="reference" type="text" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="TXN ref">
          </div>
          <div>
            <label class="text-xs uppercase tracking-wide text-gray-500">Notes</label>
            <input name="notes" type="text" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional notes">
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700" data-pay-close>Cancel</button>
          <button id="paySubmit" class="relative px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow">
            <span class="inline-flex items-center gap-2">
              <svg id="paySpin" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              Save Payment
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // ------- Modal open/close -------
  const modal   = document.getElementById('payModal');
  const openBtn = document.getElementById('btnOpenNewPayment');
  const closeEls = modal.querySelectorAll('[data-pay-close]');
  const form    = document.getElementById('payForm');
  const spinner = document.getElementById('paySpin');
  const alertEl = document.getElementById('payAlert');

  const open = () => modal.classList.remove('hidden');
  const close = () => modal.classList.add('hidden');

  openBtn?.addEventListener('click', open);
  closeEls.forEach(el => el.addEventListener('click', close));

  // ------- Simple client-side filter (no backend roundtrip) -------
  const q = document.getElementById('q');
  const fromEl = document.getElementById('from');
  const toEl   = document.getElementById('to');
  const rows = Array.from(document.querySelectorAll('#tblBody .pay-row'));
  const cards= Array.from(document.querySelectorAll('#cardsBody .pay-card'));

  const match = (el, state) => {
    const inv = (el.dataset.inv || '').toLowerCase();
    const cli = (el.dataset.cli || '').toLowerCase();
    const mode= (el.dataset.mode||'').toLowerCase();
    const ref = (el.dataset.ref || '').toLowerCase();
    const dat = (el.dataset.date||'');

    const textOk = !state.q || inv.includes(state.q) || cli.includes(state.q) || mode.includes(state.q) || ref.includes(state.q);
    const dateOk = (!state.from || dat >= state.from) && (!state.to || dat <= state.to);
    return textOk && dateOk;
  };

  const state = { q:'', from:'', to:'' };
  const apply = () => {
    rows.forEach(r => r.style.display = match(r, state) ? '' : 'none');
    cards.forEach(c => c.style.display = match(c, state) ? '' : 'none');
  };

  q.addEventListener('input', ()=>{ state.q = q.value.trim().toLowerCase(); apply(); });
  fromEl.addEventListener('change', ()=>{ state.from = fromEl.value || ''; apply(); });
  toEl.addEventListener('change', ()=>{ state.to = toEl.value || ''; apply(); });
  document.getElementById('btnReset').addEventListener('click', ()=>{
    q.value=''; fromEl.value=''; toEl.value='';
    state.q=''; state.from=''; state.to='';
    apply();
  });
  apply();

  // ------- Optional: AJAX submit (keeps you on page) -------
  form.addEventListener('submit', async (e) => {
    // Remove this `return` to enable AJAX submit and stay on the page.
    // By default it will do a full POST and redirect (server-side).
    // return; // ← comment out to enable AJAX

    e.preventDefault();
    alertEl.classList.add('hidden'); alertEl.textContent='';
    spinner.classList.remove('hidden');

    try {
      const fd = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept':'application/json'},
        body: fd
      });

      if (!res.ok) {
        const data = await res.json().catch(()=>({}));
        // show top alert
        alertEl.className = 'rounded-lg border px-3 py-2 text-sm bg-rose-50 border-rose-200 text-rose-700';
        alertEl.textContent = data?.message || 'Failed to save payment. Check inputs.';
        alertEl.classList.remove('hidden');
        spinner.classList.add('hidden');
        return;
      }

      // Success → reload to reflect totals
      window.location.reload();
    } catch (err) {
      alertEl.className = 'rounded-lg border px-3 py-2 text-sm bg-rose-50 border-rose-200 text-rose-700';
      alertEl.textContent = 'Network error. Please try again.';
      alertEl.classList.remove('hidden');
      spinner.classList.add('hidden');
    }
  });
});
</script>
@endpush