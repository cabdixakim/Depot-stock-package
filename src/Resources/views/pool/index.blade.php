@extends('depot-stock::layouts.app')

@section('title','Depot Pool')

@section('content')
@php
  $fmtL = fn($v)=>number_format((float)$v,1,'.',',');
  $fmtM = fn($v,$c='USD')=>$c.' '.number_format((float)$v,2,'.',',');
  $ccy  = config('depot-stock.currency','USD');

  $now          = (float)($stock_now         ?? 0);
  $inAllowance  = (float)($in_offloads       ?? 0);          // allowances from offloads
  $posAllow     = (float)($pos_allow         ?? 0);          // + corrections
  $negAllow     = (float)($neg_allow         ?? 0);          // - corrections

  $poolTransfer = (float)($pool_transfer_l   ?? 0);          // transfers to clients (L)
  $poolSell     = (float)($pool_sell_l       ?? 0);          // sales from pool (L)
  $poolAmount   = (float)($pool_sell_amount  ?? 0);          // total sales amount

  $posDip       = (float)($pos_dip   ?? 0);
  $posOther     = (float)($pos_other ?? 0);
  $negDip       = (float)($neg_dip   ?? 0);
  $negOther     = (float)($neg_other ?? 0);

  // Totals for breakdown bars
  $incTotal   = max(0, $inAllowance + $posAllow + $posDip + $posOther);
  $decTotal   = max(0, $poolTransfer + $poolSell + $negAllow + $negDip + $negOther);
  $netWindowL = $incTotal - $decTotal;
  $flowTotal  = max(1, $incTotal + $decTotal);
  $pct = function($v) use ($flowTotal) {
      if ($flowTotal <= 0) return 0;
      return round(($v / $flowTotal) * 100);
  };

  $activeName = $activeDepotName ?? null;
@endphp

<div class="space-y-6">

  {{-- Sticky Header --}}
  <div class="sticky top-14 z-30 -mx-4 md:-mx-6 px-4 md:px-6 py-3 bg-white/70 backdrop-blur border-b border-gray-100">
    <div class="mx-auto max-w-7xl flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-500 text-white grid place-items-center shadow-sm">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M4 6h16v4H4V6zm0 6h16v6H4v-6z"/>
          </svg>
        </div>
        <div>
          <div class="text-[11px] uppercase tracking-wider text-gray-500">Inventory</div>
          <h1 class="text-lg font-semibold text-gray-900">Depot Pool</h1>
          <div class="mt-1 inline-flex items-center gap-2 text-[11px] text-gray-500">
            <span class="px-2 py-0.5 rounded-full bg-gray-100">
              Window: {{ $label }}
            </span>
            @if($activeName)
              <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 flex items-center gap-1">
                <span class="h-4 w-4 rounded-full bg-indigo-600 text-white text-[10px] grid place-items-center">
                  {{ mb_substr($activeName,0,1) }}
                </span>
                <span class="max-w-[8rem] truncate">{{ $activeName }}</span>
              </span>
            @else
              <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                All Depots
              </span>
            @endif
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button type="button" id="openTransfer"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-xs sm:text-sm shadow-sm
                 hover:bg-indigo-700 hover:-translate-y-0.5 hover:shadow-md transition-all">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M8 5v4h8V5l5 5-5 5v-4H8v4L3 10l5-5z"/>
          </svg>
          Transfer
        </button>
        <button type="button" id="openSell"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-600 text-white text-xs sm:text-sm shadow-sm
                 hover:bg-rose-700 hover:-translate-y-0.5 hover:shadow-md transition-all">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>
          </svg>
          Sell
        </button>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 p-4">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
      <div>
        <label class="block text-[11px] uppercase tracking-wide text-gray-500">From</label>
        <input name="from" type="date" value="{{ request('from') }}"
               class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
      </div>
      <div>
        <label class="block text-[11px] uppercase tracking-wide text-gray-500">To</label>
        <input name="to" type="date" value="{{ request('to') }}"
               class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
      </div>
      <div class="flex gap-2">
        <button class="flex-1 rounded-xl bg-gray-900 text-white px-4 py-2 text-sm hover:bg-black transition">
          Apply
        </button>
        <a href="{{ route('depot.pool.index') }}"
           class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm hover:bg-gray-50 transition">
          Reset
        </a>
      </div>
      <div class="hidden sm:block text-right text-[11px] text-gray-500">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-50">
          Net (L): <span class="font-semibold {{ $netWindowL>=0?'text-emerald-700':'text-rose-700' }}">{{ $fmtL($netWindowL) }}</span>
        </span>
      </div>
    </form>
  </div>

  {{-- TOP: Compact Snapshot --}}
  <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    {{-- Current pool stock --}}
    <div
      class="rounded-2xl p-4 bg-gradient-to-br from-emerald-50 via-white to-teal-50 ring-1 ring-emerald-200/70 shadow-sm
             transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div class="flex items-center justify-between gap-2">
        <div>
          <div class="text-[11px] uppercase tracking-wide text-emerald-700">Pool Stock</div>
          <div class="mt-1 text-3xl font-semibold text-gray-900">
            {{ $fmtL($now) }} <span class="text-sm text-gray-500">L</span>
          </div>
        </div>
        <div class="flex flex-col items-end text-[11px] text-gray-500">
          <span>All-time</span>
          <span class="mt-0.5 px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
            House litres
          </span>
        </div>
      </div>
    </div>

    {{-- Allowances in window --}}
    <div
      class="rounded-2xl p-4 bg-white ring-1 ring-gray-100 shadow-sm
             transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div class="flex items-center justify-between">
        <div class="text-[11px] uppercase tracking-wide text-gray-600">Allowances (Window)</div>
        <span class="text-[11px] text-gray-500">{{ $label }}</span>
      </div>
      <div class="mt-1 text-2xl font-semibold text-gray-900">
        {{ $fmtL($inAllowance + $posAllow) }} <span class="text-sm text-gray-500">L</span>
      </div>
      <div class="mt-2 flex items-center gap-2 text-[11px] text-gray-600">
        <span class="px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
          Base: {{ $fmtL($inAllowance) }} L
        </span>
        <span class="px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
          + Corr: {{ $fmtL($posAllow) }} L
        </span>
      </div>
    </div>

    {{-- Transfers to clients --}}
    <div
      class="rounded-2xl p-4 bg-white ring-1 ring-gray-100 shadow-sm
             transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div class="flex items-center justify-between">
        <div class="text-[11px] uppercase tracking-wide text-gray-600">Transfers to Clients</div>
        <span class="text-[11px] text-gray-500">{{ $label }}</span>
      </div>
      <div class="mt-1 text-2xl font-semibold text-gray-900">
        {{ $fmtL($poolTransfer) }} <span class="text-sm text-gray-500">L</span>
      </div>
      <div class="mt-2 text-[11px] text-gray-600">
        Shows on client side as positive adjustments.
      </div>
    </div>

    {{-- Sales from pool --}}
    <div
      class="rounded-2xl p-4 bg-white ring-1 ring-gray-100 shadow-sm
             transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div class="flex items-center justify-between">
        <div class="text-[11px] uppercase tracking-wide text-gray-600">Sales from Pool</div>
        <span class="text-[11px] text-gray-500">{{ $label }}</span>
      </div>
      <div class="mt-1 text-2xl font-semibold text-gray-900">
        {{ $fmtL($poolSell) }} <span class="text-sm text-gray-500">L</span>
      </div>
      <div class="mt-1 text-[13px] font-semibold text-gray-800">
        {{ $fmtM($poolAmount, $ccy) }}
      </div>
      <div class="mt-1 text-[11px] text-gray-500">
        Uses saved unit price or defaults to 1.10.
      </div>
    </div>
  </section>

  {{-- Movement Breakdown --}}
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-white text-[11px]">Δ</span>
        <div>
          <div class="text-sm font-semibold text-gray-800">Movement (Window)</div>
          <div class="text-[11px] text-gray-500 flex items-center gap-2">
            <span>In: {{ $fmtL($incTotal) }} L</span>
            <span class="text-gray-300">•</span>
            <span>Out: {{ $fmtL($decTotal) }} L</span>
            <span class="text-gray-300">•</span>
            <span>Net: <b class="{{ $netWindowL>=0?'text-emerald-700':'text-rose-700' }}">{{ $fmtL($netWindowL) }} L</b></span>
          </div>
        </div>
      </div>
      <div class="hidden sm:flex items-center gap-1 text-[11px] text-gray-500">
        <span class="h-2 w-2 rounded-full bg-emerald-500"></span><span>In</span>
        <span class="h-2 w-2 rounded-full bg-rose-500 ml-2"></span><span>Out</span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-px bg-gray-100">
      {{-- Increases --}}
      <div class="bg-white p-4 space-y-2">
        <div class="flex items-center justify-between text-[11px] mb-1">
          <span class="uppercase tracking-wide text-emerald-700">In</span>
          <span class="text-gray-500">{{ $label }}</span>
        </div>

        @php
          $incRows = [
            ['label' => 'Offload Allowances', 'value' => $inAllowance],
            ['label' => 'Allowance Corrections (+)', 'value' => $posAllow],
            ['label' => 'Dip / Other (+)', 'value' => $posDip + $posOther],
          ];
        @endphp

        <ul class="space-y-1.5 text-sm">
          @foreach($incRows as $row)
            <li class="flex items-center gap-3">
              <div class="flex-1">
                <div class="flex justify-between text-[12px] text-gray-600">
                  <span>{{ $row['label'] }}</span>
                  <span class="font-medium text-gray-900">{{ $fmtL($row['value']) }} L</span>
                </div>
                <div class="mt-1 h-1.5 rounded-full bg-emerald-50 overflow-hidden">
                  <div class="h-1.5 bg-emerald-500 transition-all"
                       style="width: {{ $pct($row['value']) }}%"></div>
                </div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>

      {{-- Decreases --}}
      <div class="bg-white p-4 space-y-2">
        <div class="flex items-center justify-between text-[11px] mb-1">
          <span class="uppercase tracking-wide text-rose-700">Out</span>
          <span class="text-gray-500">{{ $label }}</span>
        </div>

        @php
          $decRows = [
            ['label' => 'Transfers to Clients', 'value' => $poolTransfer],
            ['label' => 'Sales from Pool',      'value' => $poolSell],
            ['label' => 'Allowance Corrections (−)', 'value' => $negAllow],
            ['label' => 'Dip / Other (−)',      'value' => $negDip + $negOther],
          ];
        @endphp

        <ul class="space-y-1.5 text-sm">
          @foreach($decRows as $row)
            <li class="flex items-center gap-3">
              <div class="flex-1">
                <div class="flex justify-between text-[12px] text-gray-600">
                  <span>{{ $row['label'] }}</span>
                  <span class="font-medium text-gray-900">{{ $fmtL($row['value']) }} L</span>
                </div>
                <div class="mt-1 h-1.5 rounded-full bg-rose-50 overflow-hidden">
                  <div class="h-1.5 bg-rose-500 transition-all"
                       style="width: {{ $pct($row['value']) }}%"></div>
                </div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</div>

{{-- ========== TRANSFER MODAL ========== --}}
<div id="transferModal" class="fixed inset-0 z-[120] hidden">
  <button class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-xfer-close></button>
  <div class="absolute inset-0 p-4 sm:p-8 grid place-items-center">
    <div id="transferModalPanel"
         class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-gray-100 overflow-hidden
                transform transition-all duration-200 ease-out opacity-0 scale-95">
      <div class="px-5 py-4 bg-gray-50 border-b flex items-center justify-between">
        <div>
          <div class="text-[11px] uppercase tracking-wide text-gray-500">Depot Pool</div>
          <div class="text-lg font-semibold text-gray-900">Transfer to Client</div>
        </div>
        <button type="button" data-xfer-close
                class="h-7 w-7 inline-flex items-center justify-center rounded-full hover:bg-gray-200 transition">
          ✕
        </button>
      </div>
      <form id="transferForm" action="{{ route('depot.pool.transfer') }}" method="POST" class="p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="text-[11px] uppercase text-gray-500">Date</label>
            <input type="date" name="date" value="{{ now()->toDateString() }}"
              class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">Volume (L)</label>
            <input type="number" step="0.001" name="volume_20_l" placeholder="e.g. 10,000"
              class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">Depot</label>
            <select name="depot_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
              @foreach(($depots ?? []) as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">Product</label>
            <select name="product_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
              @foreach(($products ?? []) as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="text-[11px] uppercase text-gray-500">Client</label>
            <select name="client_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
              @foreach(($clients ?? []) as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="text-[11px] uppercase text-gray-500">Note (optional)</label>
          <input type="text" name="note" placeholder="Reference"
            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          <p class="text-[11px] text-gray-500 mt-1">Available now: <b>{{ $fmtL($now) }} L</b></p>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t">
          <button type="button" data-xfer-close
                  class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 text-sm hover:bg-gray-100 transition">
            Cancel
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition">
            Transfer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ========== SELL MODAL ========== --}}
<div id="sellModal" class="fixed inset-0 z-[120] hidden">
  <button class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-sell-close></button>
  <div class="absolute inset-0 p-4 sm:p-8 grid place-items-center">
    <div id="sellModalPanel"
         class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-gray-100 overflow-hidden
                transform transition-all duration-200 ease-out opacity-0 scale-95">
      <div class="px-5 py-4 bg-gray-50 border-b flex items-center justify-between">
        <div>
          <div class="text-[11px] uppercase tracking-wide text-gray-500">Depot Pool</div>
          <div class="text-lg font-semibold text-gray-900">Sell Stock</div>
        </div>
        <button type="button" data-sell-close
                class="h-7 w-7 inline-flex items-center justify-center rounded-full hover:bg-gray-200 transition">
          ✕
        </button>
      </div>
      <form id="sellForm" action="{{ route('depot.pool.sell') }}" method="POST" class="p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="text-[11px] uppercase text-gray-500">Date</label>
            <input type="date" name="date" value="{{ now()->toDateString() }}"
              class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">Volume (L)</label>
            <input type="number" step="0.001" name="volume_20_l" placeholder="e.g. 5,000"
              class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">Depot</label>
            <select name="depot_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
              @foreach(($depots ?? []) as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">Product</label>
            <select name="product_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
              @foreach(($products ?? []) as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="text-[11px] uppercase text-gray-500">
              Unit Price ({{ $ccy }})
            </label>
            <input type="number" step="0.01" name="unit_price" placeholder="leave blank → 1.10"
              class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          </div>
        </div>

        <div>
          <label class="text-[11px] uppercase text-gray-500">Buyer / Reference</label>
          <input type="text" name="reference" placeholder="Buyer / reference"
            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
          <p class="text-[11px] text-gray-500 mt-1">Available now: <b>{{ $fmtL($now) }} L</b></p>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t">
          <button type="button" data-sell-close
                  class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 text-sm hover:bg-gray-100 transition">
            Cancel
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm hover:bg-rose-700 transition">
            Sell
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Toast --}}
<div id="toast" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[130] hidden">
  <div class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm shadow-lg min-w-[10rem] text-center
              transform transition-all duration-200 translate-y-3 opacity-0">
    Saved.
  </div>
</div>

@push('scripts')
<script>
(function(){
  // Toast helper with microanimation
  const showToast = (msg, isError = false) => {
    const wrapper = document.getElementById('toast');
    const box     = wrapper.querySelector('div');
    box.textContent = msg || 'Saved.';
    box.classList.remove('bg-emerald-600','bg-rose-600');
    box.classList.add(isError ? 'bg-rose-600' : 'bg-emerald-600');
    wrapper.classList.remove('hidden');

    // animate in
    requestAnimationFrame(() => {
      box.classList.remove('translate-y-3','opacity-0');
      box.classList.add('translate-y-0','opacity-100');
    });

    clearTimeout(wrapper._hideTimer);
    wrapper._hideTimer = setTimeout(()=>{
      // animate out
      box.classList.remove('translate-y-0','opacity-100');
      box.classList.add('translate-y-3','opacity-0');
      setTimeout(()=> wrapper.classList.add('hidden'), 180);
    }, 2200);
  };

  // Modal helpers with scale/opacity microanimation
  const openModal = (modalEl, panelEl) => {
    modalEl.classList.remove('hidden');
    requestAnimationFrame(() => {
      panelEl.classList.remove('opacity-0','scale-95');
      panelEl.classList.add('opacity-100','scale-100');
    });
  };

  const closeModal = (modalEl, panelEl) => {
    panelEl.classList.remove('opacity-100','scale-100');
    panelEl.classList.add('opacity-0','scale-95');
    setTimeout(() => modalEl.classList.add('hidden'), 150);
  };

  const transferModal = document.getElementById('transferModal');
  const transferPanel = document.getElementById('transferModalPanel');
  const sellModal     = document.getElementById('sellModal');
  const sellPanel     = document.getElementById('sellModalPanel');

  document.getElementById('openTransfer')?.addEventListener('click', ()=>{
    openModal(transferModal, transferPanel);
  });
  document.getElementById('openSell')?.addEventListener('click', ()=>{
    openModal(sellModal, sellPanel);
  });

  transferModal.querySelectorAll('[data-xfer-close]').forEach(b =>
    b.addEventListener('click', ()=> closeModal(transferModal, transferPanel))
  );
  sellModal.querySelectorAll('[data-sell-close]').forEach(b =>
    b.addEventListener('click', ()=> closeModal(sellModal, sellPanel))
  );

  // AJAX submit helpers
  async function handleSubmit(formEl, modalEl, panelEl){
    const url = formEl.getAttribute('action');
    const fd  = new FormData(formEl);
    const btn = formEl.querySelector('button[type="submit"]');
    btn.disabled = true;

    try{
      const res  = await fetch(url, { method: 'POST', headers: { 'X-Requested-With':'XMLHttpRequest' }, body: fd });
      const data = await res.json().catch(()=> ({}));

      if (res.ok && data?.ok){
        closeModal(modalEl, panelEl);
        showToast(data?.message || 'Saved.', false);
        setTimeout(()=> location.reload(), 500);
      } else {
        showToast(data?.message || 'Failed. Please check inputs.', true);
      }
    } catch (e){
      showToast('Network error', true);
    } finally {
      btn.disabled = false;
    }
  }

  document.getElementById('transferForm')?.addEventListener('submit', (e)=>{
    e.preventDefault();
    const vol = Number(e.target.querySelector('[name="volume_20_l"]').value || 0);
    if (!vol || vol <= 0) { showToast('Enter a valid volume.', true); return; }
    handleSubmit(e.target, transferModal, transferPanel);
  });

  document.getElementById('sellForm')?.addEventListener('submit', (e)=>{
    e.preventDefault();
    const vol = Number(e.target.querySelector('[name="volume_20_l"]').value || 0);
    if (!vol || vol <= 0) { showToast('Enter a valid volume.', true); return; }
    handleSubmit(e.target, sellModal, sellPanel);
  });
})();
</script>
@endpush
@endsection