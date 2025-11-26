{{-- resources/views/depot-stock/portal/home.blade.php --}}
@extends('depot-stock::layouts.portal')

@section('title', ($client->name ?? 'Client').' — Portal')

@section('content')
@php
  $fmtL = fn($v)=>number_format((float)$v,1,'.',',');
  $fmtM = fn($v,$c='USD')=>$c.' '.number_format((float)$v,2,'.',',');

  $stock    = (float)($currentStock ?? 0);
  $totIn    = (float)($totIn ?? 0);
  $totOut   = (float)($totOut ?? 0);
  $totAdj   = (float)($totAdj ?? 0);

  $openInvoices   = $openInvoices   ?? collect();
  $recentOffloads = $recentOffloads ?? collect();
  $recentLoads    = $recentLoads    ?? collect();
  $recentPayments = $recentPayments ?? collect();

  $openTotal     = (float)($openTotal ?? 0);
  $paidTotal     = (float)($paidTotal ?? 0);
  $paymentsTotal = (float)($paymentsTotal ?? 0);
  $balance       = (float)($balance ?? 0);

  $currency      = config('depot-stock.currency','USD');

  $filters     = $filters ?? [];
  $fromFilter  = $filters['from'] ?? null;
  $toFilter    = $filters['to'] ?? null;
  $windowLabel = $windowLabel ?? 'All time';
@endphp

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
  <div class="mx-auto max-w-6xl px-4 py-6 md:py-8 space-y-6">

    {{-- TOP BAR: Filters --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Fuel Client Portal</div>
        <div class="mt-1 text-sm text-slate-400">
          Window: <span class="font-medium text-slate-200">{{ $windowLabel }}</span>
        </div>
      </div>

      <form method="GET" class="flex flex-wrap items-center gap-2 text-xs">
        <div class="flex items-center gap-1">
          <span class="text-slate-400">From</span>
          <input type="date" name="from" value="{{ $fromFilter }}"
                 class="rounded-lg border border-slate-700 bg-slate-900/70 px-2 py-1 text-xs text-slate-100 focus:border-sky-400 focus:ring-0">
        </div>
        <div class="flex items-center gap-1">
          <span class="text-slate-400">To</span>
          <input type="date" name="to" value="{{ $toFilter }}"
                 class="rounded-lg border border-slate-700 bg-slate-900/70 px-2 py-1 text-xs text-slate-100 focus:border-sky-400 focus:ring-0">
        </div>
        <button class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-medium text-white hover:bg-sky-500">
          Apply
        </button>
        <a href="{{ route('portal.home') }}"
           class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-1 text-xs text-slate-200 hover:bg-slate-800/80">
          Reset
        </a>
      </form>
    </div>

    {{-- HEADER --}}
    <header class="relative overflow-hidden rounded-3xl border border-slate-800/80 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 shadow-xl">
      <div class="absolute inset-0 pointer-events-none opacity-70">
        <div class="absolute -top-24 -left-10 h-64 w-64 bg-[radial-gradient(circle_at_top,_#22d3ee_0,_transparent_60%)] blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 bg-[radial-gradient(circle_at_bottom,_#6366f1_0,_transparent_60%)] blur-3xl"></div>
      </div>

      <div class="relative px-5 py-6 md:px-8 md:py-7 flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-300/80">
            Client Portal
          </div>
          <h1 class="mt-2 text-2xl md:text-3xl font-semibold text-slate-50 leading-tight">
            {{ $client->name ?? 'Client' }}
          </h1>
          <p class="mt-1 text-sm text-slate-300/80 max-w-xl">
            Live view of your fuel stock, recent movements and billing status.
          </p>
        </div>

        <div class="flex flex-col items-end gap-2 text-right">
          <div class="text-[11px] uppercase tracking-wide text-slate-400">Current Stock</div>
          <div class="text-2xl md:text-3xl font-semibold text-sky-300">
            {{ $fmtL($stock) }} <span class="text-sm text-slate-300">L</span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 text-[11px] text-slate-300">
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 px-2 py-1">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
              IN: {{ $fmtL($totIn) }} L
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 px-2 py-1">
              <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
              OUT: {{ $fmtL($totOut) }} L
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 px-2 py-1">
              <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
              ADJ: {{ $fmtL($totAdj) }} L
            </span>
          </div>
        </div>
      </div>
    </header>

    {{-- TOP SUMMARY CARDS --}}
    <section class="grid grid-cols-1 md:grid-cols-4 gap-4">
      {{-- Stock --}}
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-md">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Net Stock</div>
            <div class="mt-1 text-xl font-semibold text-slate-50">
              {{ $fmtL($stock) }} <span class="text-sm text-slate-400">L</span>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-sky-500/10 text-sky-300 grid place-items-center">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M4 6h16v4H4V6zm0 6h16v6H4v-6z"/>
            </svg>
          </div>
        </div>
        <p class="mt-2 text-xs text-slate-400">
          IN − OUT ± adjustments over the selected window.
        </p>
      </div>

      {{-- Offloads --}}
      <div class="rounded-2xl border border-emerald-700/40 bg-emerald-950/40 p-4 shadow-md">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-emerald-300/90">Total Offloaded</div>
            <div class="mt-1 text-xl font-semibold text-emerald-200">
              {{ $fmtL($totIn) }} <span class="text-sm text-emerald-300/80">L</span>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-emerald-500/15 text-emerald-300 grid place-items-center">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M7 10l5-5 5 5H7zM5 12h14v7H5z"/>
            </svg>
          </div>
        </div>
        <p class="mt-2 text-xs text-emerald-200/80">
          Latest OFFLOAD (IN) deliveries in this window.
        </p>
      </div>

      {{-- Loads --}}
      <div class="rounded-2xl border border-rose-700/40 bg-rose-950/40 p-4 shadow-md">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-rose-300/90">Total Loaded</div>
            <div class="mt-1 text-xl font-semibold text-rose-200">
              {{ $fmtL($totOut) }} <span class="text-sm text-rose-300/80">L</span>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-rose-500/15 text-rose-300 grid place-items-center">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17 14l-5 5-5-5h10zM19 12H5V5h14z"/>
            </svg>
          </div>
        </div>
        <p class="mt-2 text-xs text-rose-200/80">
          Latest LOAD (OUT) movements in this window.
        </p>
      </div>

      {{-- Outstanding --}}
      <div class="rounded-2xl border border-amber-700/40 bg-amber-950/40 p-4 shadow-md">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-amber-300/90">Outstanding</div>
            <div class="mt-1 text-xl font-semibold {{ $balance > 0 ? 'text-amber-200' : 'text-emerald-200' }}">
              {{ $fmtM($balance, $currency) }}
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-amber-500/15 text-amber-300 grid place-items-center">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 4a8 8 0 100 16 8 8 0 000-16zm1 11h-2v-2h2v2zm0-4h-2V7h2v4z"/>
            </svg>
          </div>
        </div>
        <p class="mt-2 text-xs text-amber-200/80">
          Approx. non-paid invoice value minus payments in this window.
        </p>
      </div>
    </section>

    {{-- MAIN GRID: MOVEMENTS + BILLING --}}
    <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)] gap-5">

      {{-- LEFT: Movements --}}
      <div class="space-y-4">
        {{-- Offloads --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md">
          <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800/80">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Recent Offloads</div>
              <p class="text-[11px] text-slate-400">Last 10 IN deliveries into your account.</p>
            </div>
          </div>

          <div class="divide-y divide-slate-800/80">
            @forelse($recentOffloads as $o)
              @php
                $litres = $o->delivered_20_l
                    ?? $o->delivered_20
                    ?? $o->loaded_20_l
                    ?? $o->volume_20_l
                    ?? 0;
              @endphp
              <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-800/70">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-slate-50 truncate">
                    {{ $o->reference ?? 'Offload #'.$o->id }}
                  </div>
                  <div class="mt-0.5 text-[11px] text-slate-400">
                    {{ optional($o->date)->format('M d, Y') ?? $o->date }}
                    @if(optional($o->tank)->depot)
                      · {{ $o->tank->depot->name }}
                    @endif
                    @if(optional($o->tank)->product)
                      · {{ $o->tank->product->name }}
                    @endif
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-semibold text-emerald-300">
                    {{ $fmtL($litres) }} L
                  </div>
                  <div class="text-[11px] text-emerald-200/80">IN</div>
                </div>
              </div>
            @empty
              <div class="px-4 py-6 text-center text-sm text-slate-400">
                No offloads recorded in this window.
              </div>
            @endforelse
          </div>
        </div>

        {{-- Loads --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md">
          <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800/80">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Recent Loads</div>
              <p class="text-[11px] text-slate-400">Last 10 OUT movements from your stock.</p>
            </div>
          </div>

          <div class="divide-y divide-slate-800/80">
            @forelse($recentLoads as $l)
              @php
                $litres = $l->loaded_20_l
                    ?? $l->delivered_20_l
                    ?? $l->volume_20_l
                    ?? 0;
              @endphp
              <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-800/70">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-slate-50 truncate">
                    {{ $l->reference ?? 'Load #'.$l->id }}
                  </div>
                  <div class="mt-0.5 text-[11px] text-slate-400">
                    {{ optional($l->date)->format('M d, Y') ?? $l->date }}
                    @if(optional($l->tank)->depot)
                      · {{ $l->tank->depot->name }}
                    @endif
                    @if(optional($l->tank)->product)
                      · {{ $l->tank->product->name }}
                    @endif
                    @if($l->truck_plate)
                      · Truck {{ $l->truck_plate }}
                    @endif
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-semibold text-rose-300">
                    {{ $fmtL($litres) }} L
                  </div>
                  <div class="text-[11px] text-rose-200/80">OUT</div>
                </div>
              </div>
            @empty
              <div class="px-4 py-6 text-center text-sm text-slate-400">
                No loads recorded in this window.
              </div>
            @endforelse
          </div>
        </div>
      </div>

      {{-- RIGHT: Billing & Payments --}}
      <div class="space-y-4">
        {{-- Invoices --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md">
          <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800/80">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Invoices</div>
              <p class="text-[11px] text-slate-400">Most recent invoices issued to you.</p>
            </div>
            <div class="text-right text-[11px] text-slate-400">
              <div>Outstanding (all)</div>
              <div class="font-semibold text-amber-300">
                {{ $fmtM($openTotal, $currency) }}
              </div>
            </div>
          </div>

          <div class="divide-y divide-slate-800/80">
            @forelse($openInvoices as $inv)
              @php
                // use total if present (your real billed amount), otherwise fall back
                $invAmount = $inv->total ?? $inv->subtotal ?? 0;
              @endphp
              <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-800/70">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-slate-50 truncate">
                    {{ $inv->number ?? ('Invoice #'.$inv->id) }}
                  </div>
                  <div class="mt-0.5 text-[11px] text-slate-400">
                    {{ optional($inv->date)->format('M d, Y') ?? $inv->date }}
                    @if($inv->due_date)
                      · Due {{ optional($inv->due_date)->format('M d, Y') ?? $inv->due_date }}
                    @endif
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-semibold text-amber-300">
                    {{ $fmtM($invAmount, $inv->currency ?? $currency) }}
                  </div>
                  <div class="text-[11px] text-amber-200/80">
                    {{ $inv->status ?? 'unpaid' }}
                  </div>
                </div>
              </div>
            @empty
              <div class="px-4 py-6 text-center text-sm text-slate-400">
                No invoices recorded in this window.
              </div>
            @endforelse
          </div>
        </div>

        {{-- Recent payments --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md">
          <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800/80">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-300">Recent Payments</div>
              <p class="text-[11px] text-slate-400">Last payments recorded against your account.</p>
            </div>
            <div class="text-right text-[11px] text-slate-400">
              <div>Total in window</div>
              <div class="font-semibold text-emerald-300">
                {{ $fmtM($paymentsTotal, $currency) }}
              </div>
            </div>
          </div>

          <div class="divide-y divide-slate-800/80">
            @forelse($recentPayments as $p)
              <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-800/70">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-slate-50 truncate">
                    {{ $p->reference ?? 'Payment #'.$p->id }}
                  </div>
                  <div class="mt-0.5 text-[11px] text-slate-400">
                    {{ optional($p->date)->format('M d, Y') ?? $p->date }}
                    @if($p->method)
                      · {{ strtoupper($p->method) }}
                    @endif
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-semibold text-emerald-300">
                    {{ $fmtM($p->amount ?? 0, $p->currency ?? $currency) }}
                  </div>
                  <div class="text-[11px] text-emerald-200/80">Received</div>
                </div>
              </div>
            @empty
              <div class="px-4 py-6 text-center text-sm text-slate-400">
                No payments recorded in this window.
              </div>
            @endforelse
          </div>
        </div>
      </div>
    </section>

  </div>
</div>
@endsection