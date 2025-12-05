{{-- resources/views/vendor/depot-stock/operations/dashboard.blade.php --}}
@extends('depot-stock::operations.layout')

@section('title', 'Depot operations')

@section('ops-content')
@php
    $today = now()->toDateString();

    $activeDepotId = session('depot.active_id');
    $activeDepot   = $activeDepotId
        ? \Optima\DepotStock\Models\Depot::find($activeDepotId)
        : null;
    $activeDepotName = $activeDepot?->name ?? 'All depots';

    $fmtInt = fn($v) => number_format((int) ($v ?? 0));
@endphp

<div class="space-y-6">

    {{-- TOP BAR --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 rounded-full bg-slate-900/5 px-2.5 py-1 text-[11px] text-gray-500">
                <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span class="uppercase tracking-[0.18em]">Depot operations</span>
            </div>
            <h1 class="text-xl md:text-2xl font-semibold text-gray-900">
                Control room
                <span class="text-sm font-normal text-gray-500">
                    · {{ $activeDepotName }}
                </span>
            </h1>
            <p class="text-sm text-gray-500 max-w-xl">
                Quick overview of daily dips, variance, and client movement for frontline operators.
            </p>
        </div>

        {{-- QUICK ACTIONS --}}
        <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
            <a href="{{ route('depot.operations.daily-dips') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-xs font-medium text-white shadow-sm hover:bg-black">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-lg bg-white/10">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M8 3v3M16 3v3M4.5 9h15M7 12h10M9 16h6M7 21h10a2 2 0 0 0 2-2V7.5A2.5 2.5 0 0 0 16.5 5h-9A2.5 2.5 0 0 0 5 7.5V19a2 2 0 0 0 2 2Z"/>
                    </svg>
                </span>
                <span>Open today’s dips</span>
            </a>

            <a href="{{ route('depot.operations.dips-history') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-lg bg-gray-900/5">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M5 4h14M7 8h10M9 12h6M11 16h2M6 20h12"/>
                    </svg>
                </span>
                <span>Dips history</span>
            </a>
        </div>
    </div>

    {{-- SUMMARY STRIP --}}
    <div class="grid gap-4 md:grid-cols-4">

        {{-- Tanks reconciled today --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="absolute -right-5 -top-5 h-16 w-16 rounded-full bg-emerald-100/70"></div>
            <div class="relative p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Tanks reconciled today
                    </p>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span>Opening + closing</span>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-semibold text-gray-900">
                    {{ $fmtInt($tanksReconciledToday ?? 0) }}
                </p>
                <p class="mt-1 text-[11px] text-gray-400">
                    Count of tank/day records with both opening and closing dips for {{ $today }}.
                </p>
            </div>
        </div>

        {{-- Variance alerts --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-rose-100/70"></div>
            <div class="relative p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Variance alerts
                    </p>
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-medium text-rose-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        <span>> {{ $varianceTolerancePct ?? 0.3 }}%</span>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-semibold text-gray-900">
                    {{ $fmtInt($varianceAlertsToday ?? 0) }}
                </p>
                <p class="mt-1 text-[11px] text-gray-400">
                    Tank/day recon rows today with variance outside tolerance.
                </p>
            </div>
        </div>

        {{-- Dips captured today --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="absolute -right-7 -top-7 h-16 w-16 rounded-full bg-sky-100/70"></div>
            <div class="relative p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Dips captured today
                    </p>
                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-medium text-sky-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                        <span>Recon rows</span>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-semibold text-gray-900">
                    {{ $fmtInt($dipsCapturedToday ?? 0) }}
                </p>
                <p class="mt-1 text-[11px] text-gray-400">
                    Number of tank/day recon records saved for {{ $today }}.
                </p>
            </div>
        </div>

        {{-- Operator activity --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-amber-100/70"></div>
            <div class="relative p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Operator activity
                    </p>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <span>Today</span>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-semibold text-gray-900">
                    {{ $fmtInt($operatorsToday ?? 0) }}
                </p>
                <p class="mt-1 text-[11px] text-gray-400">
                    Distinct users who created recon records today.
                </p>
            </div>
        </div>
    </div>

    {{-- 2-COLUMN LAYOUT --}}
    <div class="grid gap-5 lg:grid-cols-3">

        {{-- LEFT: TODAY SNAPSHOT --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Today strip --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Today’s dip sequence
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            Visual layout for how today moves from opening dips → offloads / loads → closing dips.
                        </p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-[11px] text-gray-600">
                        <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span>{{ \Illuminate\Support\Carbon::parse($today)->format('M d, Y') }}</span>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3 text-[11px]">
                    <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/60 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                            Step 1 · Opening dips
                        </p>
                        <p class="mt-1 text-[11px] text-emerald-900">
                            Operators capture morning dip levels for all tanks in use.
                        </p>
                    </div>
                    <div class="rounded-xl border border-dashed border-sky-200 bg-sky-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                            Step 2 · Movements
                        </p>
                        <p class="mt-1 text-[11px] text-sky-900">
                            Offloads (IN) and loads (OUT) during the day update expected stock.
                        </p>
                    </div>
                    <div class="rounded-xl border border-dashed border-amber-200 bg-amber-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                            Step 3 · Closing dips &amp; lock
                        </p>
                        <p class="mt-1 text-[11px] text-amber-900">
                            Evening dips + variance check; day can then be locked by a checker.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Recent days panel (now real data) --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Recent recon days
                    </p>
                    <a href="{{ route('depot.operations.dips-history') }}"
                       class="text-[11px] font-medium text-gray-500 hover:text-gray-900">
                        Open full history →
                    </a>
                </div>

                <div class="mt-3 space-y-2 text-[11px]">
                    @forelse($recentDays ?? [] as $day)
                        @php
                            $dateLabel = $day->date instanceof \Illuminate\Support\Carbon
                                ? $day->date->format('M d, Y')
                                : (string) $day->date;

                            $tank      = $day->tank;
                            $depot     = $tank?->depot;
                            $product   = $tank?->product;

                            $status    = $day->status ?? null;
                            $variance  = (float) ($day->variance_pct ?? 0);

                            // Status chip colours
                            if (!is_null($status)) {
                                $statusUpper = strtoupper($status);
                            } else {
                                $statusUpper = null;
                            }

                            $chipBg   = 'bg-gray-50';
                            $chipText = 'text-gray-700';
                            $chipLabel= $statusUpper ?: 'DRAFT';

                            if (abs($variance) > ($varianceTolerancePct ?? 0.3)) {
                                $chipBg = 'bg-rose-50';
                                $chipText = 'text-rose-700';
                                $chipLabel = 'VAR '.number_format($variance, 2).'%';
                            } elseif ($status === 'locked' || $status === 'balanced') {
                                $chipBg = 'bg-emerald-50';
                                $chipText = 'text-emerald-700';
                                $chipLabel = 'OK';
                            } elseif ($status === 'draft') {
                                $chipBg = 'bg-amber-50';
                                $chipText = 'text-amber-700';
                                $chipLabel = 'DRAFT';
                            }
                        @endphp

                        <div class="flex items-center justify-between rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-8 w-8 rounded-lg bg-gray-900/5 grid place-content-center text-[11px] font-semibold text-gray-700">
                                    {{ $tank ? 'T'.$tank->id : '—' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[11px] font-medium text-gray-800 truncate">
                                        {{ $dateLabel }}
                                        @if($depot)
                                            · {{ $depot->name }}
                                        @endif
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[10px] text-gray-500">
                                        @if($product)
                                            <span class="inline-flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                                                <span>{{ $product->name }}</span>
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                            <span>Variance: {{ number_format($variance, 2) }}%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-1">
                                <span class="inline-flex items-center justify-center min-w-[2.4rem] h-5 rounded-full px-2 text-[10px] font-semibold {{ $chipBg }} {{ $chipText }}">
                                    {{ $chipLabel }}
                                </span>
                                <span class="text-[10px] text-gray-400">
                                    {{ $day->createdBy?->name ? 'By '.$day->createdBy->name : 'Recorder pending' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-4 text-[11px] text-gray-500">
                            No recon days recorded yet. Once you save dips, they’ll appear here.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- RIGHT: SHORTCUTS / LEGEND --}}
        <div class="space-y-4">

            {{-- Shortcuts --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    Quick links
                </p>

                <div class="mt-3 space-y-2 text-[12px]">
                    <a href="{{ route('depot.operations.daily-dips') }}"
                       class="group flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 hover:border-gray-200 hover:bg-gray-100/90">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                                🌅
                            </span>
                            <div>
                                <div class="text-[12px] font-medium text-gray-900 group-hover:text-gray-950">
                                    Daily dips
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    Capture &amp; review today’s opening and closing readings.
                                </div>
                            </div>
                        </div>
                        <span class="text-gray-400 group-hover:text-gray-600 text-xs">→</span>
                    </a>

                    <a href="{{ route('depot.operations.dips-history') }}"
                       class="group flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 hover:border-gray-200 hover:bg-gray-100/90">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                                📊
                            </span>
                            <div>
                                <div class="text-[12px] font-medium text-gray-900 group-hover:text-gray-950">
                                    Dips history
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    Filter dips by date, depot, tank &amp; variance in one grid.
                                </div>
                            </div>
                        </div>
                        <span class="text-gray-400 group-hover:text-gray-600 text-xs">→</span>
                    </a>

                    <a href="{{ route('depot.operations.clients.index') }}"
                       class="group flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 hover:border-gray-200 hover:bg-gray-100/90">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                                👥
                            </span>
                            <div>
                                <div class="text-[12px] font-medium text-gray-900 group-hover:text-gray-950">
                                    Operations clients
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    Client list &amp; operational details from the ops side.
                                </div>
                            </div>
                        </div>
                        <span class="text-gray-400 group-hover:text-gray-600 text-xs">→</span>
                    </a>
                </div>
            </div>

            {{-- Status legend --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    Status legend (for grids)
                </p>
                <div class="mt-3 space-y-2 text-[11px] text-gray-600">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-5 min-w-[2.2rem] items-center justify-center rounded-full bg-emerald-50 text-[10px] font-semibold text-emerald-700">
                            OK
                        </span>
                        <span>Balanced day — variance within tolerance and day locked.</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-5 min-w-[2.2rem] items-center justify-center rounded-full bg-amber-50 text-[10px] font-semibold text-amber-700">
                            DRAFT
                        </span>
                        <span>Opening or closing dips missing — day still editable.</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-5 min-w-[2.2rem] items-center justify-center rounded-full bg-rose-50 text-[10px] font-semibold text-rose-700">
                            VAR
                        </span>
                        <span>Variance above tolerance — investigate shortfall or gain.</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
@endsection