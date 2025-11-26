@extends('depot-stock::layouts.app')
@section('title','Dashboard')

@section('content')
@php
    $u = auth()->user();
    $roleNames = $u?->roles?->pluck('name')->map(fn ($r) => strtolower($r))->all() ?? [];

    // tweak these to match EXACTLY what you have in roles table
    $isAdmin     = in_array('admin', $roleNames) || in_array('owner', $roleNames) || in_array('superadmin', $roleNames);
    $isOps       = in_array('operations', $roleNames) || in_array('ops', $roleNames);
    $isAccounts  = in_array('accounts', $roleNames) || in_array('accounting', $roleNames);
@endphp

@php
  $fmtL = fn($v)=>number_format((float)$v,1,'.',',');
  $fmtM = fn($v,$ccy='USD')=>$ccy.' '.number_format((float)$v,2,'.',',');

  $ccy = $currency ?? 'USD';

  $ppl       = (float)($profit_per_litre ?? 0);
  $p_amt     = (float)($profit_amount ?? 0);
  $p_prev    = (float)($profit_prev ?? 0);
  $p_delta   = (float)($profit_delta ?? 0);
  $p_pct     = (float)($profit_pct ?? 0);
  $profitUp  = $p_delta >= 0;

  $pIn  = (float) ($pool_in  ?? 0);
  $pOut = (float) ($pool_out ?? 0);
  $pNow = (float) ($pool_now ?? 0);

  $winLabel     = $label_mode ?? 'All Time';
  $stockLabel   = $stock_label ?? null;          // e.g. "As of 17 Nov 2025"
  $profitLabel  = $profit_label ?? 'This Month'; // special label for Profit
  $activePreset = $preset ?? 'all_time';

  $filterFrom = $filter_from ?? null;
  $filterTo   = $filter_to ?? null;

  $activeDepotId   = session('depot.active_id');
  $activeDepotName = $activeDepotId
      ? optional(\Optima\DepotStock\Models\Depot::find($activeDepotId))->name
      : 'All Depots';

  if (!$activeDepotName) {
      $activeDepotName = 'All Depots';
  }

  $isPreset = function($key) use ($activePreset) {
      return $activePreset === $key;
  };

  $winChip = $winLabel;
@endphp

{{-- Soft background glows --}}
<div class="pointer-events-none fixed inset-0 -z-10">
  <div class="absolute -top-24 -left-10 h-80 w-80 rounded-full blur-3xl opacity-30
              bg-[conic-gradient(at_top_left,_#6366f1_10%,_#22d3ee_35%,_#f472b6_60%,_transparent_70%)]"></div>
  <div class="absolute bottom-0 right-0 h-96 w-96 rounded-full blur-3xl opacity-25
              bg-[conic-gradient(at_bottom_right,_#f59e0b_10%,_#ef4444_35%,_#8b5cf6_60%,_transparent_70%)]"></div>
</div>

<div class="min-h-[100dvh]">
  <div class="mx-auto max-w-7xl py-6 grid md:grid-cols-[19rem,1fr] gap-6">

    {{-- Sidebar --}}
    <aside class="md:sticky md:top-20 md:self-start space-y-6 px-4 md:px-0">

      {{-- Filters --}}
      <div class="rounded-2xl bg-white/80 backdrop-blur shadow-sm ring-1 ring-white/60 transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
        <div class="p-4 border-b border-white/60 flex items-center justify-between">
          <div class="text-[11px] uppercase tracking-wide text-gray-600">Filters</div>
        </div>

        <div class="p-4 space-y-3">
          {{-- Quick presets --}}
          <div class="flex flex-wrap gap-2">
            <a href="{{ route('depot.dashboard', ['preset' => 'all_time']) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition
               {{ $isPreset('all_time') ? 'bg-gray-900 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
              All Time
            </a>
            <a href="{{ route('depot.dashboard', ['preset' => 'this_month']) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition
               {{ $isPreset('this_month') ? 'bg-gray-900 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
              This Month
            </a>
            <a href="{{ route('depot.dashboard', ['preset' => 'this_year']) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition
               {{ $isPreset('this_year') ? 'bg-gray-900 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
              This Year
            </a>
          </div>

          {{-- Manual range --}}
          <form method="GET" class="space-y-3">
            <div class="grid grid-cols-1 gap-3">
              <div>
                <label class="block text-[11px] text-gray-500">From</label>
                <input name="from" type="date" value="{{ optional($filterFrom)->toDateString() }}"
                       class="mt-1 w-full rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 focus:border-indigo-400 focus:outline-none focus:ring-0 text-sm">
              </div>
              <div>
                <label class="block text-[11px] text-gray-500">To</label>
                <input name="to" type="date" value="{{ optional($filterTo)->toDateString() }}"
                       class="mt-1 w-full rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 focus:border-indigo-400 focus:outline-none focus:ring-0 text-sm">
              </div>
            </div>
            <div class="flex items-center gap-2 pt-2">
              <button class="rounded-xl bg-gradient-to-r from-gray-900 via-gray-800 to-gray-700 text-white px-3 py-2 text-sm w-full shadow-sm hover:shadow-md hover:opacity-95 transition">
                Apply
              </button>
              <a href="{{ route('depot.dashboard') }}"
                 class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2 text-sm w-full text-center hover:bg-white hover:shadow-sm transition">
                Reset
              </a>
            </div>
          </form>
        </div>
      </div>

      {{-- At a glance --}}
      <div class="rounded-2xl p-[1px] bg-gradient-to-br from-indigo-200 via-sky-200 to-cyan-200">
        <div class="rounded-2xl bg-white/90 backdrop-blur p-4 shadow-sm transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div class="text-[11px] uppercase tracking-wide text-gray-600 mb-3">At a glance</div>
          <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-600">Active Clients</span>
              <span class="font-semibold text-gray-900">{{ number_format($clients_count) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-600">Open Invoices</span>
              <span class="font-semibold text-amber-700">{{ number_format($open_invoices) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-600">Overdue (3+ days)</span>
              <span class="font-semibold text-rose-700">{{ number_format($overdue_invoices) }}</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Depot Pool --}}
      @php
        $winNet = $pIn - $pOut;
        $maxBar = max(1, $pIn, $pOut, abs($winNet));
        $pct    = fn($v)=> round(min(100, ($maxBar>0 ? (abs($v)/$maxBar)*100 : 0)));
        $poolUp = $winNet >= 0;
      @endphp
      
      @if($isAdmin)<div class="rounded-2xl p-[1px] bg-gradient-to-br from-slate-200/70 via-slate-100 to-white">
        <div class="rounded-2xl bg-white/85 backdrop-blur p-4 ring-1 ring-slate-100 shadow-sm transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div class="flex items-center justify-between gap-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-600 whitespace-nowrap">Depot Pool</div>
            <span class="text-[11px] text-slate-400 whitespace-nowrap">{{ $winLabel }}</span>
          </div>

          <div class="mt-2">
            <div class="text-xs text-slate-500">Current (all time)</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
              {{ $fmtL($pNow) }} <span class="text-sm text-slate-400">L</span>
            </div>
          </div>

          <div class="mt-4 space-y-3">
            <div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-600">IN</span>
                <span class="font-medium text-slate-900">{{ $fmtL($pIn) }} L</span>
              </div>
              <div class="mt-1 h-1.5 rounded-full bg-emerald-50 overflow-hidden">
                <div class="h-1.5 rounded-full bg-emerald-400/90 transition-all duration-300" style="width: {{ $pct($pIn) }}%"></div>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-600">OUT</span>
                <span class="font-medium text-slate-900">{{ $fmtL($pOut) }} L</span>
              </div>
              <div class="mt-1 h-1.5 rounded-full bg-rose-50 overflow-hidden">
                <div class="h-1.5 rounded-full bg-rose-400/90 transition-all duration-300" style="width: {{ $pct($pOut) }}%"></div>
              </div>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <span class="text-[11px] text-slate-500">Window Net</span>
            <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-lg {{ $poolUp ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
              @if($poolUp)
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l6 6h-4v10h-4V10H6z"/></svg>
              @else
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20l-6-6h4V4h4v10h4z"/></svg>
              @endif
              {{ $fmtL($winNet) }} L
            </span>
          </div>
        </div>
      </div>
      @endif
    </aside>

    {{-- Main --}}
    <main class="space-y-6 px-4 md:px-0">

      {{-- Header --}}
      <div class="relative overflow-hidden rounded-2xl transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-lg">
        <div class="absolute inset-0 opacity-80"
             style="background:
               radial-gradient(1200px 300px at 10% -20%, rgba(99,102,241,.25), transparent 60%),
               radial-gradient(900px 300px at 110% 120%, rgba(56,189,248,.25), transparent 60%),
               linear-gradient(90deg, #0b1220, #0e1528);"></div>
        <div class="relative px-5 py-6 sm:px-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-white">
              <div class="text-[11px] uppercase tracking-wide text-white/70">
                Overview • {{ $activeDepotName === 'All Depots' ? 'All Depots' : 'Depot' }}
              </div>
              <h1 class="text-xl font-semibold flex items-center gap-2">
                <span class="inline-flex items-center justify-center h-7 px-3 rounded-full bg-white/10 text-xs font-medium tracking-wide backdrop-blur-sm">
                  {{ $activeDepotName }}
                </span>
                <span class="text-sm text-white/60 hidden sm:inline">Dashboard</span>
              </h1>
            </div>
            <span class="px-3 py-1 rounded-xl bg-white/10 text-white/80 text-xs ring-1 ring-white/20 whitespace-nowrap backdrop-blur-sm">
              {{ $winLabel }}
            </span>
          </div>
        </div>
      </div>

      {{-- KPIs --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- STOCK – hero card --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-sky-200/80 p-4 flex flex-col transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4 5h16v4H4zM4 11h16v8H4z"/>
              </svg>
            </span>
            <div class="leading-tight">
              <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-700">Stock</div>
              <div class="text-[11px] text-gray-400">IN − OUT ± ADJ</div>
            </div>
          </div>

          <div class="mt-3">
            <div class="text-3xl font-semibold text-gray-900 leading-none">
              {{ $fmtL($stock_window) }} <span class="text-sm text-gray-500">L</span>
            </div>

            @if($stockLabel)
              <div class="mt-1 text-[11px] text-sky-700 font-medium flex items-center gap-1">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-500 motion-safe:animate-pulse"></span>
                {{ $stockLabel }}
              </div>
            @endif
          </div>
        </div>

        {{-- OFFLOADS --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-4 flex flex-col transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7 10l5-5 5 5H7zM5 12h14v7H5z"/>
              </svg>
            </span>
            <div class="leading-tight">
              <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-700">Offloads</div>
              <div class="text-[11px] text-gray-400">Inbound to clients</div>
            </div>
          </div>

          <div class="mt-3">
            <div class="text-2xl font-semibold text-gray-900 leading-none">
              {{ $fmtL($period_offloads) }} <span class="text-sm text-gray-500">L</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500">
              {{ $winChip }}
            </div>
          </div>
        </div>

        {{-- LOADS --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-4 flex flex-col transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-700">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 14l-5 5-5-5h10zM19 12H5V5h14z"/>
              </svg>
            </span>
            <div class="leading-tight">
              <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-700">Loads</div>
              <div class="text-[11px] text-gray-400">Outbound from clients</div>
            </div>
          </div>

          <div class="mt-3">
            <div class="text-2xl font-semibold text-gray-900 leading-none">
              {{ $fmtL($period_loads) }} <span class="text-sm text-gray-500">L</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500">
              {{ $winChip }}
            </div>
          </div>
        </div>

        {{-- PROFIT --}}
        @if($isAdmin || $isAccounts)
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-amber-200 p-4 flex flex-col transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 4l6 6h-4v10h-4V10H6z"/>
              </svg>
            </span>
            <div class="leading-tight">
              <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-700">Profit</div>
              <div class="text-[11px] text-gray-400">
                @if($ppl > 0)
                  {{ $ccy }} {{ number_format($ppl,3) }} / L
                @else
                  <span class="text-amber-600">Set margin</span>
                @endif
              </div>
            </div>
          </div>

          <div class="mt-3">
            <div class="text-xl font-semibold {{ $p_amt>0?'text-emerald-700':'text-gray-900' }} leading-none">
              {{ $fmtM($p_amt,$ccy) }}
            </div>
            <div class="mt-1 text-[11px] text-gray-500">
              {{ $profitLabel }}
            </div>

            <div class="mt-2 flex items-center gap-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px]
                {{ $profitUp ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                  @if($profitUp)
                    <path d="M12 4l6 6h-4v10h-4V10H6z"/>
                  @else
                    <path d="M12 20l-6-6h4V4h4v10h4z"/>
                  @endif
                </svg>
                {{ $profitUp?'+':'' }}{{ number_format($p_pct,1) }}%
              </span>

              <span class="text-[10px] text-gray-500">
                {{ $profitUp?'+':'' }}{{ $fmtM($p_delta,$ccy) }} vs prev month
              </span>
            </div>
          </div>
        </div>
        @endif
      </div>

      {{-- Clients — Stock & Activity --}}
      <div class="rounded-2xl p-[1px] bg-gradient-to-br from-slate-200/60 via-white to-slate-100/60">
        <div class="rounded-2xl bg-white/90 backdrop-blur shadow-sm ring-1 ring-white/60 overflow-hidden">
          <div class="px-4 py-3 border-b border-white/60 flex items-center justify-between">
            <div class="text-sm font-semibold text-gray-800">
              Clients — Stock & Activity <span class="text-gray-400">({{ $winLabel }})</span>
            </div>
            <a href="{{ route('depot.clients.index') }}" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">View All</a>
          </div>

          {{-- Desktop --}}
          <div class="hidden md:block">
            <ul class="divide-y divide-gray-100/70">
              @forelse($clientSnapshot as $row)
                @php
                  $name  = $row['name'] ?? '—';
                  $code  = $row['code'] ?? '';
                  $in    = (float)($row['litres_in'] ?? 0);
                  $out   = (float)($row['litres_out'] ?? 0);
                  $adj   = (float)($row['litres_adj'] ?? 0);
                  $stock = (float)($row['stock_net'] ?? 0);
                  $paid  = (float)($row['paid'] ?? 0);
                  $bal   = (float)($row['balance'] ?? 0);
                  $initials = strtoupper(collect(explode(' ', $name))->map(fn($s)=>mb_substr($s,0,1))->take(2)->implode(''));
                  $cap  = max(1, $in + max(0,$adj) + 1);
                  $pct  = max(0, min(100, round(($cap>0? ($stock / $cap) : 0) * 100)));
                @endphp
                <li class="px-4 py-3 hover:bg-gradient-to-r hover:from-slate-50/60 hover:to-white transition-colors">
                  <div class="flex items-center gap-4">
                    <div class="h-10 w-10 rounded-xl text-white grid place-content-center font-semibold
                                bg-gradient-to-br from-indigo-600 to-sky-500 shadow-sm">
                      {{ $initials ?: 'C' }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex flex-wrap items-center gap-x-3">
                        <a href="{{ route('depot.clients.show', $row['id']) }}" class="font-medium text-gray-900 hover:underline truncate">
                          {{ $name }}
                        </a>
                        @if($code)
                          <span class="text-[11px] text-gray-500 font-mono px-2 py-0.5 rounded-lg bg-gray-100">{{ $code }}</span>
                        @endif
                      </div>
                      <div class="mt-2 grid grid-cols-12 gap-3 text-[12px] text-gray-700">
                        <div class="col-span-5">
                          <div class="flex items-center justify-between">
                            <span class="text-gray-500">Available</span>
                            <span class="font-medium text-gray-900">{{ $fmtL($stock) }} L</span>
                          </div>
                          <div class="mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-2 bg-emerald-400/90 transition-all duration-300" style="width: {{ $pct }}%"></div>
                          </div>
                        </div>
                        <div class="col-span-7 grid grid-cols-3 gap-3">
                          <div>
                            <div class="text-gray-500">IN</div>
                            <div class="font-medium">{{ $fmtL($in) }} L</div>
                          </div>
                          <div>
                            <div class="text-gray-500">OUT</div>
                            <div class="font-medium">{{ $fmtL($out) }} L</div>
                          </div>
                          <div>
                            <div class="text-gray-500">ADJ</div>
                            <div class="font-medium">{{ $fmtL($adj) }} L</div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="text-[11px] text-gray-500">Outstanding</div>
                      <div class="font-semibold {{ $bal>0?'text-amber-700':'text-emerald-700' }}">{{ $fmtM($bal,$ccy) }}</div>
                      <div class="text-[11px] text-gray-400 mt-0.5">Paid: {{ $fmtM($paid,$ccy) }}</div>
                    </div>
                  </div>
                </li>
              @empty
                <li class="px-4 py-10 text-center text-gray-500">No client activity in this window.</li>
              @endforelse
            </ul>
          </div>

          {{-- Mobile --}}
          <div class="md:hidden divide-y divide-gray-100/70">
            @forelse($clientSnapshot as $row)
              @php
                $name  = $row['name'] ?? '—';
                $code  = $row['code'] ?? '';
                $in    = (float)($row['litres_in'] ?? 0);
                $out   = (float)($row['litres_out'] ?? 0);
                $adj   = (float)($row['litres_adj'] ?? 0);
                $stock = (float)($row['stock_net'] ?? 0);
                $paid  = (float)($row['paid'] ?? 0);
                $bal   = (float)($row['balance'] ?? 0);
              @endphp
              <a href="{{ route('depot.clients.show', $row['id']) }}" class="block p-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between">
                  <div class="font-semibold text-gray-900">{{ $name }}</div>
                  @if($code)
                    <span class="text-[11px] text-gray-500 font-mono">{{ $code }}</span>
                  @endif
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-[12px]">
                  <div>
                    <div class="text-gray-400">Available</div>
                    <div class="font-semibold text-gray-900">{{ $fmtL($stock) }} L</div>
                  </div>
                  <div>
                    <div class="text-gray-400">Outstanding</div>
                    <div class="font-medium {{ $bal>0?'text-amber-700':'text-emerald-700' }}">{{ $fmtM($bal,$ccy) }}</div>
                  </div>
                  <div>
                    <div class="text-gray-400">IN</div>
                    <div class="font-medium text-gray-900">{{ $fmtL($in) }} L</div>
                  </div>
                  <div>
                    <div class="text-gray-400">OUT</div>
                    <div class="font-medium text-gray-900">{{ $fmtL($out) }} L</div>
                  </div>
                </div>
              </a>
            @empty
              <div class="p-6 text-center text-gray-500">No client activity in this window.</div>
            @endforelse
          </div>
        </div>
      </div>

    </main>
  </div>
</div>
@endsection