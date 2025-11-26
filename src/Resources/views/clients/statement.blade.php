@extends('depot-stock::layouts.app')
@section('title', $client->name . ' • Statement')

@section('content')
<div class="min-h-[100dvh] bg-[#F7FAFC]">
  {{-- ===== Sticky Header ===== --}}
  <div class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="mx-auto max-w-7xl px-4 md:px-6 h-14 flex items-center justify-between">
      <div class="leading-tight">
        <div class="text-[11px] uppercase tracking-wide text-slate-500">Client Statement</div>
        <div class="font-semibold text-slate-900">{{ $client->name }}</div>
        <div class="text-[11px] text-slate-500">
          Closing = Opening + Charges − Credits
        </div>
      </div>
      <a href="{{ route('depot.clients.show', $client) }}"
         class="hidden md:inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 hover:bg-slate-50">
        ← Back
      </a>
    </div>
  </div>

  {{-- ===== Body ===== --}}
  <div class="mx-auto max-w-7xl px-4 md:px-6 py-6 space-y-6">

    {{-- Filter + Export Bar --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-4">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end">

        {{-- Quick presets --}}
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-[11px] uppercase tracking-wide text-slate-500 mr-1">Quick range</span>
          <button type="button" class="chip" data-preset="last-month">Last month</button>
          <button type="button" class="chip" data-preset="this-year">This year</button>
        </div>

        {{-- Date range + actions --}}
        <form id="flt" class="ml-auto flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-slate-500">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="mt-1 rounded-xl border-slate-200 text-sm focus:ring-0 focus:border-slate-400">
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wide text-slate-500">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="mt-1 rounded-xl border-slate-200 text-sm focus:ring-0 focus:border-slate-400">
          </div>

          <div class="flex flex-col gap-2">
            {{-- Unpaid-only toggle --}}
            <label class="inline-flex items-center gap-2 text-[11px] text-slate-600">
              <input type="checkbox" name="unpaid"
                     class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 focus:ring-offset-0">
              <span>Show only unpaid invoices</span>
            </label>

            <div class="flex flex-wrap items-center gap-2">
              <button id="btnApply"
                      class="inline-flex items-center gap-1 rounded-xl bg-slate-900 text-white px-3 py-2 text-sm hover:bg-black">
                Apply
              </button>

              <button id="btnReset"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                Reset
              </button>

              <button id="btnExportCsv"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 text-white px-3 py-2 text-sm hover:bg-emerald-700">
                Export CSV
              </button>

              <button id="btnPrint"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 text-white px-3 py-2 text-sm hover:bg-indigo-700">
                Print / PDF
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    {{-- KPIs Row --}}
    @php
      $card  = 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-4 flex flex-col gap-1';
      $label = 'text-[11px] uppercase tracking-wide text-slate-500';
      $val   = 'text-xl font-semibold';
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="{{ $card }}">
        <div class="flex items-center justify-between">
          <span class="{{ $label }}">Opening</span>
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-700 text-xs">
            Op
          </span>
        </div>
        <div id="t_open" class="{{ $val }} text-slate-900">—</div>
        <p class="text-[11px] text-slate-500">
          Balance before selected period.
        </p>
      </div>

      <div class="{{ $card }}">
        <div class="flex items-center justify-between">
          <span class="{{ $label }}">Charges (Invoices)</span>
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-rose-50 text-rose-600 text-xs">
            +
          </span>
        </div>
        <div id="t_chg" class="{{ $val }} text-rose-700">—</div>
        <p class="text-[11px] text-slate-500">
          Total invoices in range.
        </p>
      </div>

      <div class="{{ $card }}">
        <div class="flex items-center justify-between">
          <span class="{{ $label }}">Credits (Payments)</span>
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-xs">
            −
          </span>
        </div>
        <div id="t_cr" class="{{ $val }} text-emerald-700">—</div>
        <p class="text-[11px] text-slate-500">
          Total payments & credits in range.
        </p>
      </div>

      <div class="{{ $card }}">
        <div class="flex items-center justify-between">
          <span class="{{ $label }}">Closing Balance</span>
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 text-xs">
            =
          </span>
        </div>
        <div id="t_close" class="{{ $val }} text-indigo-700">—</div>
        <p class="text-[11px] text-slate-500">
          Opening + Charges − Credits.
        </p>
      </div>
    </div>

    {{-- Ledger Table --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 bg-gradient-to-b from-slate-50 to-white flex items-center justify-between">
        <div>
          <div class="text-[12px] font-semibold tracking-wide text-slate-700">Ledger</div>
          <div id="metaRange" class="text-[11px] text-slate-500"></div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="sticky top-0 z-10 bg-white/95 backdrop-blur border-b border-slate-200 shadow-[0_1px_0_0_rgba(0,0,0,0.02)]">
            <tr>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left uppercase">Date</th>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left uppercase">Type</th>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left uppercase">Document</th>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left uppercase">Description</th>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right uppercase">Debit</th>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right uppercase">Credit</th>
              <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right uppercase">Balance</th>
            </tr>
          </thead>
          <tbody id="ledgerBody" class="divide-y divide-slate-100">
            <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<style>
  .chip {
    border-radius: 9999px;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 6px 12px;
    font-size: 0.75rem;
    color: rgb(71 85 105);
    transition: background .15s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .chip:hover {
    background: rgb(248 250 252);
    box-shadow: 0 1px 4px rgba(148, 163, 184, 0.35);
    border-color: rgb(203 213 225);
  }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const routeData   = @json(route('depot.clients.statement.data', $client));
  const routeExport = @json(route('depot.clients.statement.export', $client));
  const initialFrom = @json($from);
  const initialTo   = @json($to);

  const $  = (s, r=document)=>r.querySelector(s);
  const $$ = (s, r=document)=>Array.from(r.querySelectorAll(s));
  const fmt2 = new Intl.NumberFormat('en-US',{minimumFractionDigits:2, maximumFractionDigits:2});

  const form     = $('#flt');
  const fromEl   = form.querySelector('[name="from"]');
  const toEl     = form.querySelector('[name="to"]');
  const unpaidEl = form.querySelector('[name="unpaid"]');
  const btnApply = $('#btnApply');
  const btnReset = $('#btnReset');

  // Preset chips
  const chips = $$('.chip[data-preset]');
  chips.forEach(c=>{
    c.addEventListener('click', ()=>{
      const now = new Date();
      const pad = n => n.toString().padStart(2,'0');
      const ymd = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
      const lastOfMonth  = (d)=>new Date(d.getFullYear(), d.getMonth()+1, 0);

      if(c.dataset.preset === 'last-month'){
        const lm = new Date(now.getFullYear(), now.getMonth()-1, 1);
        fromEl.value = ymd(lm);
        toEl.value   = ymd(lastOfMonth(lm));
      } else if(c.dataset.preset === 'this-year'){
        const y = now.getFullYear();
        fromEl.value = `${y}-01-01`;
        toEl.value   = ymd(now);
      }
      load();
    });
  });

  // Reset to initial server defaults
  btnReset.addEventListener('click', ()=>{
    fromEl.value   = initialFrom;
    toEl.value     = initialTo;
    unpaidEl.checked = false;
    load();
  });

  // KPIs + meta
  const tOpen  = $('#t_open');
  const tChg   = $('#t_chg');
  const tCr    = $('#t_cr');
  const tClose = $('#t_close');
  const meta   = $('#metaRange');
  const body   = $('#ledgerBody');

  // Export buttons
  const btnCsv   = $('#btnExportCsv');
  const btnPrint = $('#btnPrint');

  function buildQs(extra = {}) {
    const params = {
      from: fromEl.value,
      to:   toEl.value,
      unpaid: unpaidEl.checked ? '1' : '0',
      ...extra,
    };
    return new URLSearchParams(params).toString();
  }

  btnCsv?.addEventListener('click', ()=>{
    const qs = buildQs({format:'csv'});
    window.location = `${routeExport}?${qs}`;
  });

  btnPrint?.addEventListener('click', ()=>{
    const qs = buildQs({format:'print'});
    window.open(`${routeExport}?${qs}`, '_blank');
  });

  // Apply
  btnApply?.addEventListener('click', (e)=>{
    e.preventDefault();
    load();
  });

  async function load(){
    body.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Loading…</td></tr>`;
    try{
      const qs  = buildQs();
      const res = await fetch(`${routeData}?${qs}`, {headers:{'Accept':'application/json'}});
      const data = await res.json();

      const opening = +data.opening || 0;
      const charges = +data.charges || 0;
      const credits = +data.credits || 0;
      const closing = +data.closing || 0;

      tOpen.textContent  = fmt2.format(opening);
      tChg.textContent   = fmt2.format(charges);
      tCr.textContent    = fmt2.format(credits);
      tClose.textContent = fmt2.format(closing);

      const unpaid = data.unpaid ? true : false;
      meta.textContent   = `${data.from} → ${data.to}` + (unpaid ? ' • unpaid invoices only' : '');

      const rows = Array.isArray(data.rows) ? data.rows : [];
      if(!rows.length){
        body.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">No activity in this period.</td></tr>`;
        return;
      }

      body.innerHTML = rows.map(r => `
        <tr class="hover:bg-slate-50">
          <td class="px-3 py-2 text-slate-800 whitespace-nowrap">${r.date ?? ''}</td>
          <td class="px-3 py-2 text-slate-700 whitespace-nowrap">${r.type ?? ''}</td>
          <td class="px-3 py-2 font-mono text-xs text-slate-700 whitespace-nowrap">${r.doc_no ?? ''}</td>
          <td class="px-3 py-2 text-slate-600">${r.description ?? ''}</td>
          <td class="px-3 py-2 text-right ${(+r.debit||0)?'text-rose-700 font-medium':''}">
            ${fmt2.format(+r.debit||0)}
          </td>
          <td class="px-3 py-2 text-right ${(+r.credit||0)?'text-emerald-700 font-medium':''}">
            ${fmt2.format(+r.credit||0)}
          </td>
          <td class="px-3 py-2 text-right font-semibold text-indigo-700">
            ${fmt2.format(+r.balance||0)}
          </td>
        </tr>
      `).join('');
    }catch(e){
      body.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-rose-600">Failed to load statement.</td></tr>`;
    }
  }

  // Initial load
  load();
})();
</script>
@endpush