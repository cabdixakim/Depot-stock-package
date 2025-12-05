@extends('depot-stock::layouts.portal')

@section('title', ($client->name ?? 'Client').' — Movements')

@section('content')
@php
    $from = $from ?? null;
    $to   = $to ?? null;

    $fmtL = fn($v) => number_format((float)$v, 1, '.', ',');

    // Build query string for export links (preserve filters)
    $exportQuery   = http_build_query(array_filter([
        'from' => $from,
        'to'   => $to,
    ]));
    $baseExportUrl = route('portal.movements.export') . ($exportQuery ? ('?'.$exportQuery) : '');
@endphp

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
  <div class="mx-auto max-w-6xl px-4 py-6 md:py-8 space-y-5">

    {{-- TOP BAR: Heading + filters --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
          Fuel Movements
        </div>
        <h1 class="mt-1 text-xl font-semibold text-slate-50">
          {{ $client->name ?? 'Client' }} — Offloads & Loads
        </h1>
        <p class="mt-1 text-xs text-slate-400">
          View and export your OFFLOAD (IN) and LOAD (OUT) history.
        </p>
      </div>

      {{-- Filters --}}
      <form method="GET" class="flex flex-wrap items-center gap-2 text-xs">
        <div class="flex items-center gap-1">
          <span class="text-slate-400">From</span>
          <input type="date" name="from" value="{{ $from }}"
                 class="rounded-lg border border-slate-700 bg-slate-900/70 px-2 py-1 text-xs text-slate-100 focus:border-sky-400 focus:ring-0">
        </div>
        <div class="flex items-center gap-1">
          <span class="text-slate-400">To</span>
          <input type="date" name="to" value="{{ $to }}"
                 class="rounded-lg border border-slate-700 bg-slate-900/70 px-2 py-1 text-xs text-slate-100 focus:border-sky-400 focus:ring-0">
        </div>
        <button class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-medium text-white hover:bg-sky-500">
          Apply
        </button>
        <a href="{{ route('portal.movements') }}"
           class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-1 text-xs text-slate-200 hover:bg-slate-800/80">
          Reset
        </a>
      </form>
    </div>

    {{-- EXPORT: pill-style buttons --}}
    <div class="flex justify-end mt-1 mb-3">
      <div class="inline-flex items-center gap-1 rounded-full border border-emerald-500/40 bg-slate-900/80 px-2.5 py-1.5 text-[11px]">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/15">
          <svg class="h-3 w-3 text-emerald-300" viewBox="0 0 24 24" fill="currentColor">
            <path d="M5 20h14v-2H5v2zm7-2l5-6h-3V4h-4v8H7l5 6z"/>
          </svg>
        </span>
        <span class="uppercase tracking-wide text-emerald-200/80 mr-1">Export</span>

        <a href="{{ $baseExportUrl . ($exportQuery ? '&' : '?') . 'format=csv' }}"
           class="inline-flex items-center gap-1 rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-[11px] font-medium text-slate-100 hover:bg-slate-800">
          CSV
        </a>

        <a href="{{ $baseExportUrl . ($exportQuery ? '&' : '?') . 'format=xls' }}"
           class="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-medium text-white shadow-sm hover:bg-emerald-700">
          Excel
        </a>
      </div>
    </div>

    {{-- MAIN GRID: Offloads + Loads --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      {{-- OFFLOADS --}}
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800/80">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-300/90">
              Offloads (IN)
            </div>
            <p class="text-[11px] text-slate-400">
              Latest deliveries into your account.
            </p>
          </div>

          <div class="text-right">
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-950/60 px-3 py-1 text-[11px]">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
              <span class="uppercase tracking-wide text-emerald-300/90">Total</span>
              <span class="font-semibold text-emerald-100">
                {{ $fmtL($totalOffloadsLitres ?? 0) }} L
              </span>
            </div>
            <div class="mt-1 text-[10px] text-slate-500">
              Page {{ $offloads->currentPage() }} / {{ $offloads->lastPage() }}
            </div>
          </div>
        </div>

        <div class="divide-y divide-slate-800/80">
          @forelse($offloads as $o)
            @php
              $litres   = $o->delivered_20_l
                          ?? $o->delivered_20
                          ?? $o->loaded_20_l
                          ?? $o->volume_20_l
                          ?? 0;

              $truck    = $o->truck_plate ?? $o->truck ?? null;
              $trailer  = $o->trailer_plate ?? $o->trailer ?? null;

              $temp     = $o->temperature_c ?? null;
              $density  = $o->density_kg_l ?? null;
              $hasTempDen = !is_null($temp) || !is_null($density);
            @endphp

            <details class="group">
              <summary class="px-4 py-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-slate-800/70 list-none">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-slate-50 truncate">
                    {{ $o->reference ?? 'Offload #'.$o->id }}
                  </div>
                  <div class="mt-0.5 text-[11px] text-slate-400 flex flex-wrap items-center gap-1">
                    <span>{{ optional($o->date)->format('M d, Y') ?? $o->date }}</span>
                    @if(optional($o->tank)->depot)
                      <span>· {{ $o->tank->depot->name }}</span>
                    @endif
                    @if(optional($o->tank)->product)
                      <span>· {{ $o->tank->product->name }}</span>
                    @endif
                  </div>
                </div>
                <div class="shrink-0 text-right">
                  <div class="text-sm font-semibold text-emerald-300">
                    {{ $fmtL($litres) }} L
                  </div>
                  <div class="flex items-center justify-end gap-1 text-[11px] text-emerald-200/80">
                    <span class="group-open:hidden inline-flex items-center gap-1">
                      <span>See details</span>
                      <svg class="h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08-1.06z" clip-rule="evenodd" />
                      </svg>
                    </span>
                    <span class="hidden group-open:inline-flex items-center gap-1">
                      <span>Hide details</span>
                      <svg class="h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.83l-3.71 3.94a.75.75 0 01-1.08-1.04l4.25-4.5a.75.75 0 01-1.08-1.06z" clip-rule="evenodd" />
                      </svg>
                    </span>
                  </div>
                </div>
              </summary>

              <div class="px-4 pb-3 pt-2 bg-slate-900/90 text-[11px] text-slate-300 flex flex-wrap gap-2">
                @if($truck || $trailer)
                  <span class="inline-flex items-center gap-2 rounded-full bg-slate-800/80 px-3 py-0.5">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-700/80">
                      <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 7h11v9H3V7zm12 2h3l3 3v4h-6V9zM6 18a1.5 1.5 0 103 0H6zm9 0a1.5 1.5 0 103 0h-3z"/>
                      </svg>
                    </span>
                    <span class="flex flex-wrap gap-2">
                      <span>Truck: <span class="font-semibold text-slate-100">{{ $truck ?? '—' }}</span></span>
                      @if($trailer)
                        <span>· Trailer: <span class="font-semibold text-slate-100">{{ $trailer }}</span></span>
                      @endif
                    </span>
                  </span>
                @endif

                @if($hasTempDen)
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2 py-0.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    <span>
                      Temp: {{ !is_null($temp) ? $temp.' °C' : '—' }}
                      · Density: {{ !is_null($density) ? $density.' kg/L' : '—' }}
                    </span>
                  </span>
                @endif

                <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2 py-0.5">
                  <span class="h-1.5 w-1.5 rounded-full bg-sky-300"></span>
                  <span>Ref: {{ $o->reference ?? '—' }}</span>
                </span>

                @if($o->note)
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2 py-0.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                    <span class="truncate max-w-[16rem]">
                      {{ $o->note }}
                    </span>
                  </span>
                @endif
              </div>
            </details>
          @empty
            <div class="px-4 py-6 text-center text-sm text-slate-400">
              No offloads found for this window.
            </div>
          @endforelse
        </div>

        <div class="px-4 py-3 border-t border-slate-800/80">
          {{ $offloads->links() }}
        </div>
      </div>

      {{-- LOADS --}}
      <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-md overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800/80">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-rose-300/90">
              Loads (OUT)
            </div>
            <p class="text-[11px] text-slate-400">
              Latest withdrawals from your stock.
            </p>
          </div>

          <div class="text-right">
            <div class="inline-flex items-center gap-2 rounded-full border border-rose-500/40 bg-rose-950/60 px-3 py-1 text-[11px]">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-400"></span>
              <span class="uppercase tracking-wide text-rose-300/90">Total</span>
              <span class="font-semibold text-rose-100">
                {{ $fmtL($totalLoadsLitres ?? 0) }} L
              </span>
            </div>
            <div class="mt-1 text-[10px] text-slate-500">
              Page {{ $loads->currentPage() }} / {{ $loads->lastPage() }}
            </div>
          </div>
        </div>

        <div class="divide-y divide-slate-800/80">
          @forelse($loads as $l)
            @php
              $litres   = $l->loaded_20_l
                          ?? $l->delivered_20_l
                          ?? $l->volume_20_l
                          ?? 0;

              $truck    = $l->truck_plate ?? $l->truck ?? null;
              $trailer  = $l->trailer_plate ?? $l->trailer ?? null;

              $temp     = $l->temperature_c ?? null;
              $density  = $l->density_kg_l ?? null;
              $hasTempDen = !is_null($temp) || !is_null($density);
            @endphp

            <details class="group">
              <summary class="px-4 py-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-slate-800/70 list-none">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-slate-50 truncate">
                    {{ $l->reference ?? 'Load #'.$l->id }}
                  </div>
                  <div class="mt-0.5 text-[11px] text-slate-400 flex flex-wrap items-center gap-1">
                    <span>{{ optional($l->date)->format('M d, Y') ?? $l->date }}</span>
                    @if(optional($l->tank)->depot)
                      <span>· {{ $l->tank->depot->name }}</span>
                    @endif
                    @if(optional($l->tank)->product)
                      <span>· {{ $l->tank->product->name }}</span>
                    @endif
                  </div>
                </div>
                <div class="shrink-0 text-right">
                  <div class="text-sm font-semibold text-rose-300">
                    {{ $fmtL($litres) }} L
                  </div>
                  <div class="flex items-center justify-end gap-1 text-[11px] text-rose-200/80">
                    <span class="group-open:hidden inline-flex items-center gap-1">
                      <span>See details</span>
                      <svg class="h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08-1.06z" clip-rule="evenodd" />
                      </svg>
                    </span>
                    <span class="hidden group-open:inline-flex items-center gap-1">
                      <span>Hide details</span>
                      <svg class="h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.83l-3.71 3.94a.75.75 0 01-1.08-1.04l4.25-4.5a.75.75 0 01-1.08-1.06z" clip-rule="evenodd" />
                      </svg>
                    </span>
                  </div>
                </div>
              </summary>

              <div class="px-4 pb-3 pt-2 bg-slate-900/90 text-[11px] text-slate-300 flex flex-wrap gap-2">

                @if($truck || $trailer)
                  <span class="inline-flex items-center gap-2 rounded-full bg-slate-800/80 px-3 py-0.5">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-700/80">
                      <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 7h11v9H3V7zm12 2h3l3 3v4h-6V9zM6 18a1.5 1.5 0 103 0H6zm9 0a1.5 1.5 0 103 0h-3z"/>
                      </svg>
                    </span>
                    <span class="flex flex-wrap gap-2">
                      <span>Truck: <span class="font-semibold text-slate-100">{{ $truck ?? '—' }}</span></span>
                      @if($trailer)
                        <span>· Trailer: <span class="font-semibold text-slate-100">{{ $trailer }}</span></span>
                      @endif
                    </span>
                  </span>
                @endif

                @if($hasTempDen)
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2 py-0.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    <span>
                      Temp: {{ !is_null($temp) ? $temp.' °C' : '—' }}
                      · Density: {{ !is_null($density) ? $density.' kg/L' : '—' }}
                    </span>
                  </span>
                @endif

                <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2 py-0.5">
                  <span class="h-1.5 w-1.5 rounded-full bg-sky-300"></span>
                  <span>Ref: {{ $l->reference ?? '—' }}</span>
                </span>

                @if($l->note)
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2 py-0.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                    <span class="truncate max-w-[16rem]">
                      {{ $l->note }}
                    </span>
                  </span>
                @endif
              </div>
            </details>
          @empty
            <div class="px-4 py-6 text-center text-sm text-slate-400">
              No loads found for this window.
            </div>
          @endforelse
        </div>

        <div class="px-4 py-3 border-t border-slate-800/80">
          {{ $loads->links() }}
        </div>
      </div>

    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // no dropdown any more, leaving stack in case you add interactions later
</script>
@endpush