@extends('depot-stock::layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 py-6 space-y-5">

  {{-- HEADER — modern, mobile-first --}}
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    {{-- Back button (mobile-first) --}}
      <a href="{{ route('depot.clients.show', $client) }}"
        id="btnBack"
        class="inline-flex items-center gap-2 h-9 w-fit px-3 rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 shadow-sm">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="text-sm">Back</span>
      </a>
    <div class="flex items-center gap-3">
      {{-- Client avatar with initials --}}
      @php
        $initials = collect(explode(' ', $client->name))->map(fn($p)=>mb_substr($p,0,1))->join('');
      @endphp
      <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-500 text-white grid place-items-center text-sm font-semibold shadow-sm">
        {{ $initials }}
      </div>
      <div class="space-y-0.5">
        <div class="text-[11px] uppercase tracking-wider text-gray-500">Billing</div>
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="text-2xl font-semibold text-gray-900">{{ $client->name }}</h1>
          <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-700 px-2 py-0.5 text-[11px]">
            ID #{{ $client->id }}
          </span>
        </div>
        <p class="text-xs sm:text-sm text-gray-500">
          Select unbilled Offloads & billable Adjustments, set a rate, then generate an invoice.
        </p>
      </div>
    </div>

    <div class="flex items-end gap-2 sm:gap-3">
      <div>
        <label class="block text-[11px] uppercase tracking-wide text-gray-500">Rate / Litre</label>
        <input id="rate" type="number" step="0.001"
               value="{{ $suggestedRate ?? '' }}"
               placeholder="2.450"
               class="w-28 sm:w-32 px-3 py-2 rounded-xl border border-gray-200 focus:border-gray-400 focus:outline-none">
      </div>
      <button id="openConfirm"
        class="px-4 sm:px-5 py-2.5 rounded-xl text-white shadow
               bg-gradient-to-r from-indigo-600 to-blue-500 hover:opacity-95 transition">
        Generate Invoice
      </button>
    </div>
  </div>

  {{-- FILTERS + RUNNING TOTALS --}}
  <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 p-3 sm:p-4">
    <div class="flex flex-col sm:flex-row sm:items-end gap-3 sm:gap-4">
      <div class="flex-1 sm:flex-none">
        <label class="block text-[11px] uppercase tracking-wide text-gray-500">From</label>
        <input id="from" type="date"
               class="mt-1 w-full sm:w-auto px-3 py-2 rounded-xl border border-gray-200 focus:border-gray-400 focus:outline-none">
      </div>
      <div class="flex-1 sm:flex-none">
        <label class="block text-[11px] uppercase tracking-wide text-gray-500">To</label>
        <input id="to" type="date"
               class="mt-1 w-full sm:w-auto px-3 py-2 rounded-xl border border-gray-200 focus:border-gray-400 focus:outline-none">
      </div>

      <div class="sm:ml-auto flex items-center justify-between sm:justify-end gap-3 rounded-xl bg-gray-50 px-3 py-2">
        <div class="text-xs sm:text-sm text-gray-600">
          Unbilled Total:
          <span id="allLitres" class="font-semibold text-gray-900">0</span> L ·
          <span id="allAmount" class="font-semibold text-gray-900">$0.00</span>
        </div>
        <button id="loadData"
          class="px-3 py-2 rounded-xl bg-gray-900 text-white hover:bg-black transition">
          Refresh
        </button>
      </div>
    </div>
  </div>

  {{-- GRID --}}
  <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <div class="text-sm sm:text-base font-semibold text-gray-800">Unbilled Movements</div>
      <div class="text-xs sm:text-sm text-gray-500">
        <span id="countBadge" class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100">
          <span id="rowCount">0</span> rows
        </span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-[13px]">
       <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="py-2 px-3 w-10 text-center"><input id="chkAll" type="checkbox" /></th>
            <th class="py-2 px-3 text-left">Date</th>
            <th class="py-2 px-3 text-left">Depot</th>
            <th class="py-2 px-3 text-left">Tank</th>
            <th class="py-2 px-3 text-left">Product</th>
            <th class="py-2 px-3 text-left">Truck</th>
            <th class="py-2 px-3 text-left">Trailer</th>
            <th class="py-2 px-3 text-right">Litres</th>
            <th class="py-2 px-3 text-left">Type</th>
            <th class="py-2 px-3 text-left">Reference</th>
          </tr>
      </thead>
        <tbody id="tbody" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>

    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
      <div class="text-sm text-gray-600">
        Selected:
        <span id="selCount" class="font-semibold text-gray-900">0</span> rows ·
        <span id="selLitres" class="font-semibold text-gray-900">0</span> L ·
        <span id="selAmount" class="font-semibold text-gray-900">$0.00</span>
      </div>
      <div class="text-xs text-gray-400">Tip: use the header checkbox to select all.</div>
    </div>
  </div>
</div>

{{-- CONFIRM MODAL (replaces alert) --}}
<div id="confirmModal" class="fixed inset-0 z-[120] hidden">
  <button class="absolute inset-0 bg-black/50 backdrop-blur-sm" data-confirm-close></button>
  <div class="absolute inset-0 flex items-end sm:items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl ring-1 ring-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b bg-gray-50">
        <div class="text-sm text-gray-500">Invoice Preview</div>
        <div class="mt-1 text-lg font-semibold text-gray-900">{{ $client->name }}</div>
      </div>

      <div class="px-5 py-4 space-y-3 text-sm text-gray-800">
        <div class="grid grid-cols-2 gap-3">
          <div class="rounded-xl bg-gray-50 p-3">
            <div class="text-[11px] uppercase tracking-wide text-gray-500">Selected rows</div>
            <div id="c_rows" class="mt-1 text-lg font-semibold">0</div>
          </div>
          <div class="rounded-xl bg-gray-50 p-3">
            <div class="text-[11px] uppercase tracking-wide text-gray-500">Rate / Litre</div>
            <div id="c_rate" class="mt-1 text-lg font-semibold">—</div>
          </div>
          <div class="rounded-xl bg-gray-50 p-3">
            <div class="text-[11px] uppercase tracking-wide text-gray-500">Total Litres</div>
            <div id="c_litres" class="mt-1 text-lg font-semibold">0</div>
          </div>
          <div class="rounded-xl bg-gray-50 p-3">
            <div class="text-[11px] uppercase tracking-wide text-gray-500">Estimated Amount</div>
            <div id="c_amount" class="mt-1 text-lg font-semibold">$0.00</div>
          </div>
        </div>

        <div class="rounded-xl border border-gray-100">
          <div class="px-3 py-2 text-xs text-gray-500 border-b">Sample (first 5 rows)</div>
          <div id="c_sample" class="max-h-40 overflow-y-auto text-[12px]">
            {{-- filled by JS --}}
          </div>
        </div>

        <div class="text-xs text-gray-500">
          This will mark the selected Offloads/Adjustments as billed and create a new invoice.
        </div>
      </div>

      <div class="px-5 py-4 border-t bg-gray-50 flex items-center justify-end gap-2">
        <button class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-100" data-confirm-close>
          Back to Edit
        </button>
        <button id="confirmGenerate" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
          Confirm & Generate
        </button>
      </div>
    </div>
  </div>
</div>

{{-- TOAST --}}
<div id="toast" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[130] hidden">
  <div class="rounded-xl bg-gray-900 text-white px-4 py-2 text-sm shadow-lg">Saved.</div>
</div>

@push('styles')
<style>
  /* ===== Row Interaction & Highlight ===== */
  tr.selected-row {
    background: linear-gradient(to right, #f0f7ff, #e0ecff);
    box-shadow: inset 3px 0 0 #3b82f6;
    transition: all 0.2s ease;
  }
  tr.selected-row:hover {
    background: linear-gradient(to right, #e5f0ff, #d4e4ff);
  }
  tr:hover {
    background-color: #fafafa;
  }

  tr {
    cursor: pointer;
  }

  /* Smooth checkbox scale on toggle */
  input.rowChk {
    transform: scale(1.2);
    accent-color: #2563eb; /* nice indigo-blue */
  }
</style>
  @endpush

@push('scripts')
<script>
const token = document.querySelector('meta[name="csrf-token"]')?.content;

const CLIENT_ID   = {{ $client->id }};
const CLIENT_NAME = @json($client->name);
const SUGGESTED_RATE = {{ $suggestedRate !== null ? (float)$suggestedRate : 'null' }};

let rows = [];
let selected = new Map();

const tbody     = document.getElementById('tbody');
const rowCount  = document.getElementById('rowCount');
const chkAll    = document.getElementById('chkAll');
const selCount  = document.getElementById('selCount');
const selLitres = document.getElementById('selLitres');
const selAmount = document.getElementById('selAmount');
const allLitres = document.getElementById('allLitres');
const allAmount = document.getElementById('allAmount');
const rateInput = document.getElementById('rate');

if (SUGGESTED_RATE && !rateInput.value) rateInput.value = SUGGESTED_RATE;

function fmt(n){ return Number(n||0).toLocaleString(undefined,{maximumFractionDigits:3}); }
function money(n){ return (Number(n||0)).toLocaleString(undefined,{style:'currency',currency:'USD'}); }

async function loadData() {
  const q = new URLSearchParams({
    from: document.getElementById('from').value || '',
    to:   document.getElementById('to').value   || ''
  });

  const url = `/depot/clients/${CLIENT_ID}/billing/waiting/data?` + q.toString();
  const res = await fetch(url, { headers: { 'Accept':'application/json' } });
  if (!res.ok) {
    tbody.innerHTML = `<tr><td colspan="8" class="px-3 py-6 text-center text-rose-600">Failed to load data.</td></tr>`;
    rowCount.textContent = 0;
    return;
  }
  const data = await res.json();

  rows = data.rows || [];
  selected.clear();
  render();
  updateTotalsAll();
  updateTotalsSelected();
}

function render() {
  tbody.innerHTML = rows.map(r => {
    const key = `${r.type}:${r.id}`;
    const isSelected = selected.has(key);
    const chip = r.type === 'offload'
      ? '<span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">Offload</span>'
      : '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Adjustment</span>';

    return `
      <tr class="transition-all ${isSelected ? 'selected-row' : ''}" data-key="${key}">
        <td class="py-2 px-3 text-center">
          <input data-key="${key}" class="rowChk" type="checkbox" ${isSelected ? 'checked' : ''}/>
        </td>
        <td class="py-2 px-3">${r.date || ''}</td>
        <td class="py-2 px-3">${r.depot || ''}</td>
        <td class="py-2 px-3">T#${r.tank || ''}</td>
        <td class="py-2 px-3">${r.product || ''}</td>
        <td class="py-2 px-3">${r.truck_plate || '<span class="text-gray-400 italic">–</span>'}</td>
        <td class="py-2 px-3">${r.trailer_plate || '<span class="text-gray-400 italic">–</span>'}</td>
        <td class="py-2 px-3 text-right">${fmt(r.litres)}</td>
        <td class="py-2 px-3">${chip}</td>
        <td class="py-2 px-3">${r.reference || ''}</td>
      </tr>`;
  }).join('');

  rowCount.textContent = rows.length;

  tbody.querySelectorAll('tr').forEach(row => {
    const key = row.dataset.key;
    row.addEventListener('click', (e) => {
      if (e.target.classList.contains('rowChk')) return;
      const [type, id] = key.split(':');
      const r = rows.find(x => x.type === type && String(x.id) === String(id));
      if (!r) return;
      if (selected.has(key)) selected.delete(key);
      else selected.set(key, r);
      render();
      updateTotalsSelected();
    });
  });

  tbody.querySelectorAll('.rowChk').forEach(chk => {
    chk.addEventListener('change', (e) => {
      const key = e.target.dataset.key;
      if (e.target.checked) {
        const [type, id] = key.split(':');
        const r = rows.find(x => x.type === type && String(x.id) === String(id));
        if (r) selected.set(key, r);
      } else {
        selected.delete(key);
      }
      render();
      updateTotalsSelected();
    });
  });
}

chkAll.addEventListener('change', () => {
  if (chkAll.checked) rows.forEach(r => selected.set(`${r.type}:${r.id}`, r));
  else selected.clear();
  render();
  updateTotalsSelected();
});

function sumLitres(list) { return list.reduce((s,x)=>s+Number(x.litres||0),0); }
function currentRate()   { return Number(rateInput.value || 0); }

function updateTotalsAll() {
  const litres = sumLitres(rows);
  allLitres.textContent = fmt(litres);
  allAmount.textContent = money(litres * currentRate());
}
rateInput.addEventListener('input', updateTotalsAll);

function updateTotalsSelected() {
  const arr = [...selected.values()];
  const litres = sumLitres(arr);
  selCount.textContent  = arr.length;
  selLitres.textContent = fmt(litres);
  selAmount.textContent = money(litres * currentRate());
}

document.getElementById('loadData').addEventListener('click', loadData);

function toast(msg){
  const t = document.getElementById('toast');
  t.querySelector('div').textContent = msg;
  t.classList.remove('hidden');
  setTimeout(()=>t.classList.add('hidden'), 1600);
}

// ---- Confirm modal logic (no browser alert) ----
const cm   = document.getElementById('confirmModal');
const cRows= document.getElementById('c_rows');
const cRate= document.getElementById('c_rate');
const cLit = document.getElementById('c_litres');
const cAmt = document.getElementById('c_amount');
const cSmpl= document.getElementById('c_sample');

document.getElementById('openConfirm').addEventListener('click', () => {
  const rate = currentRate();
  const arr = [...selected.values()];
  if (arr.length === 0) { toast('Select at least one row.'); return; }
  if (!rate || rate <= 0) { toast('Enter a valid Rate/Litre.'); return; }

  const litres = sumLitres(arr);

  cRows.textContent = arr.length;
  cRate.textContent = rate.toFixed(3);
  cLit.textContent  = fmt(litres);
  cAmt.textContent  = money(litres * rate);

  // build small sample
  const sample = arr.slice(0,5).map(r => {
    const tag = r.type === 'offload' ? 'Off' : 'Adj';
    return `<div class="px-3 py-2 border-b last:border-b-0 text-gray-700 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100">${tag}</span>
        <span class="font-medium">${r.product || ''}</span>
        <span class="text-gray-400">T#${r.tank || ''}</span>
      </div>
      <div class="tabular-nums">${fmt(r.litres)} L</div>
    </div>`;
  }).join('');
  cSmpl.innerHTML = sample || '<div class="px-3 py-3 text-gray-500">—</div>';

  cm.classList.remove('hidden');
});

document.querySelectorAll('[data-confirm-close]').forEach(b => b.addEventListener('click', ()=> cm.classList.add('hidden')));

document.getElementById('confirmGenerate').addEventListener('click', async () => {
  const rate = currentRate();
  const offload_ids = [];
  const adjustment_ids = [];
  selected.forEach(r => (r.type === 'offload' ? offload_ids : adjustment_ids).push(r.id));

  const res = await fetch('/depot/invoices/generate', {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token },
    body: JSON.stringify({
      client_id: CLIENT_ID,
      rate_per_litre: rate,
      offload_ids,
      adjustment_ids
    })
  });

  if (res.ok) {
    cm.classList.add('hidden');
    await loadData();        // billed rows disappear
    toast('Invoice generated');
  } else {
    cm.classList.add('hidden');
    toast('Failed to generate invoice');
  }
});

// First load
loadData();

// Back button: if there's a real referrer, use it; else go to client.show
document.getElementById('btnBack')?.addEventListener('click', function (e) {
  if (document.referrer && document.referrer !== window.location.href) {
    e.preventDefault();
    history.back();
  }
});
</script>
@endpush
@endsection