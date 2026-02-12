{{-- resources/views/vendor/depot-stock/operations/daily-dips.blade.php --}}
@extends('depot-stock::operations.layout')

@include('depot-stock::operations.partials.depot-pool-adjust-modal')

@section('ops-content')
@php
    use Illuminate\Support\Carbon;

    $forDate         = $date instanceof Carbon ? $date : Carbon::parse($date ?? now());
    $selectedDepotId = request('depot', $depotId ?? 'all');
    $selectedTankId  = request('tank');

    $totalTanks = $tanks->count();

    // Counts
    $lockedCount = $daysByTank->where('status', 'locked')->count();

    $closingCapturedCount = $daysByTank->filter(function ($d) {
        return $d->closing_actual_l_20 !== null;
    })->count();

    $startedCount    = $daysByTank->count();
    $notStartedCount = max(0, $totalTanks - $startedCount);

    // Determine current tank
    $currentTank = $selectedTankId
        ? $tanks->firstWhere('id', (int) $selectedTankId)
        : $tanks->first();

    $currentDay = $currentTank
        ? ($daysByTank[$currentTank->id] ?? null)
        : null;

    // Existing dips for this tank/day
    $openingDip = $currentDay?->dips?->firstWhere('type', 'opening');
    $closingDip = $currentDay?->dips?->firstWhere('type', 'closing');

    // Formatter
    $formatLitres = function ($v) {
        if ($v === null) return '—';
        return number_format((float)$v, 0) . ' L';
    };

    // Movement summary for current tank (offloads / loads / net)
    /** @var \Illuminate\Support\Collection|null $movementByTank */
    $movementByTank  = $movementByTank ?? collect();
    $movementCurrent = $currentTank
        ? ($movementByTank[$currentTank->id] ?? null)
        : null;

    $movOff  = $movementCurrent['offloads_l'] ?? null;
    $movLoad = $movementCurrent['loads_l']    ?? null;
    $movAdj  = $movementCurrent['adj_l_20']   ?? null;
    $movNet  = $movementCurrent['net_l']      ?? null;

    // Pre-generate URLs for JS
    $openingUrl = $currentTank
        ? route('depot.operations.daily-dips.store-opening', [
            'depot' => $currentTank->depot_id,
            'date'  => $forDate->toDateString(),
        ])
        : '';

    $closingUrl = $currentTank
        ? route('depot.operations.daily-dips.store-closing', [
            'depot' => $currentTank->depot_id,
            'date'  => $forDate->toDateString(),
        ])
        : '';

    $lockUrl = $currentTank
        ? route('depot.operations.daily-dips.lock', [
            'depot' => $currentTank->depot_id,
            'date'  => $forDate->toDateString(),
        ])
        : '';

    // PATCH: lock flag for disabling actions
    $isLocked = (bool) ($currentDay && $currentDay->status === 'locked');

    // --- VARIANCE ADJUSTMENT BUTTON LOGIC ---
    $showVarianceAdjustBtn = false;
    $varianceAdjustedBy = null;
    $varianceTolerance = 50; // litres, adjust as needed
    $maxDaysOld = config('depot-stock.pool_adjust_max_days', 5);
    if ($currentDay && $currentDay->status === 'locked' && isset($currentDay->variance_l_20)) {
        $absVariance = abs($currentDay->variance_l_20);
        $daysOld = \Illuminate\Support\Carbon::parse($forDate)->diffInDays(\Illuminate\Support\Carbon::today());
        // Check if depot pool adjustment already exists for this tank-day
        $adjustEntry = \Optima\DepotStock\Models\DepotPoolEntry::where('depot_id', $currentTank->depot_id)
            ->where('product_id', $currentTank->product_id)
            ->where('ref_type', \Optima\DepotStock\Models\DepotPoolEntry::REF_ALLOWANCE_CORR)
            ->where('ref_id', $currentTank->id)
            ->whereDate('date', $forDate->toDateString())
            ->first();
        if ($adjustEntry) {
            $varianceAdjustedBy = $adjustEntry->user?->name ?? 'Unknown';
        }
        $showVarianceAdjustBtn = $absVariance > $varianceTolerance && $daysOld <= $maxDaysOld && $currentDay->variance_l_20 != 0 && !$adjustEntry;
    }
@endphp

<div class="space-y-6">
    {{-- HEADER ROW: TITLE + FILTER --}}
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">        <div>
            <h1 class="text-lg font-semibold text-gray-900">
                Daily dips
            </h1>
            <p class="mt-0.5 text-xs text-gray-500">
                {{ $forDate->toDateString() }}
            </p>
        </div>

        {{-- Filters – perfectly aligned row --}}
        <form
            id="dipsFilterForm"
            method="GET"
            class="flex flex-wrap items-center justify-end gap-2"
        >
            @foreach(request()->except(['date','depot']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach

            {{-- Date --}}
            <div class="flex items-center gap-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    Date
                </span>
                <input
                    type="date"
                    name="date"
                    value="{{ $forDate->toDateString() }}"
                    class="w-[140px] rounded-lg border border-gray-200 bg-white/90 px-2 py-1.5 text-xs text-gray-800
                           focus:outline-none focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500"
                >
            </div>

            {{-- Depot --}}
            <div class="flex items-center gap-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    Depot
                </span>
                <div class="relative overflow-hidden rounded-lg border border-gray-200 bg-white/90">
                    <select
                        name="depot"
                        class="w-[170px] appearance-none bg-transparent pl-3 pr-7 py-1.5 text-xs text-gray-800
                               focus:outline-none focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500"
                    >
                        <option value="all" {{ $selectedDepotId === 'all' ? 'selected' : '' }}>
                            All depots
                        </option>
                        @foreach($depots as $depot)
                            <option value="{{ $depot->id }}"
                                {{ (string)$selectedDepotId === (string)$depot->id ? 'selected' : '' }}>
                                {{ $depot->name }}
                            </option>
                        @endforeach
                    </select>
                    {{-- custom chevron, clean on the right --}}
                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                                  clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Go --}}
            <button
                class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm
                       hover:bg-black active:scale-[0.97] transition"
            >
                Go
            </button>

            {{-- Today --}}
            <button
                type="button"
                id="btnResetToToday"
                title="Jump to today"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700
                       hover:bg-gray-50 active:scale-[0.97] transition"
            >
                Today
            </button>
        </form>
    </div>

    {{-- TOP STATS --}}
    <div class="grid gap-4 md:grid-cols-4">
        {{-- Tanks in view --}}
        <div class="relative overflow-hidden rounded-2xl bg-white/90 backdrop-blur border border-gray-100 p-4 shadow-sm">
            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-indigo-50"></div>
            <div class="relative">
                <p class="text-[11px] uppercase font-semibold text-gray-500">Tanks in view</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $totalTanks }}</p>
                <p class="text-[11px] mt-1 text-gray-400">Current filters applied.</p>
            </div>
        </div>

        {{-- Locked --}}
        <div class="relative overflow-hidden rounded-2xl bg-white/90 backdrop-blur border border-gray-100 p-4 shadow-sm">
            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-emerald-50"></div>
            <div class="relative">
                <p class="text-[11px] uppercase font-semibold text-gray-500">Locked</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $lockedCount }}</p>
                <p class="text-[11px] mt-1 text-gray-400">Fully reconciled days.</p>
            </div>
        </div>

        {{-- Closing captured --}}
        <div class="relative overflow-hidden rounded-2xl bg-white/90 backdrop-blur border border-gray-100 p-4 shadow-sm">
            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-sky-50"></div>
            <div class="relative">
                <p class="text-[11px] uppercase font-semibold text-gray-500">Closing captured</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $closingCapturedCount }}</p>
                <p class="text-[11px] mt-1 text-gray-400">Evening dips saved.</p>
            </div>
        </div>

        {{-- Not started --}}
        <div class="relative overflow-hidden rounded-2xl bg-white/90 backdrop-blur border border-gray-100 p-4 shadow-sm">
            <div class="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-rose-50"></div>
            <div class="relative">
                <p class="text-[11px] uppercase font-semibold text-gray-500">Not started</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $notStartedCount }}</p>
                <p class="text-[11px] mt-1 text-gray-400">Tanks with no dips.</p>
            </div>
        </div>
    </div>

    {{-- MAIN AREA: TANKS SIDEBAR + WORKSHEET --}}
    <div class="flex gap-4 min-h-[calc(100vh-10rem)]">

        {{-- SIDEBAR --}}
        <aside
            class="hidden md:block w-64 shrink-0 rounded-2xl bg-white/60 backdrop-blur border border-gray-100 shadow-sm sticky top-24 self-start"
        >
            <div class="border-b border-gray-100 px-4 py-3">
                <p class="text-[11px] uppercase font-semibold text-gray-500">
                    Tanks ({{ $forDate->toDateString() }})
                </p>
                <p class="text-[11px] text-gray-400">Pick a tank to work on dips.</p>
            </div>

            <ul class="divide-y divide-gray-100 text-xs">
                @foreach($tanks as $tank)
                    @php
                        $day = $daysByTank[$tank->id] ?? null;

                        if (! $day) {
                            $statusText  = 'Not started';
                            $statusClass = 'text-gray-400';
                            $dotClass    = 'bg-gray-300';
                        } elseif ($day->status === 'locked') {
                            $statusText  = 'Locked';
                            $statusClass = 'text-emerald-600';
                            $dotClass    = 'bg-emerald-500';
                        } elseif ($day->closing_actual_l_20 !== null) {
                            $statusText  = 'Closing saved';
                            $statusClass = 'text-indigo-600';
                            $dotClass    = 'bg-indigo-500';
                        } elseif ($day->opening_l_20 !== null) {
                            $statusText  = 'Opening saved';
                            $statusClass = 'text-sky-600';
                            $dotClass    = 'bg-sky-500';
                        } else {
                            $statusText  = 'In progress';
                            $statusClass = 'text-gray-600';
                            $dotClass    = 'bg-amber-500';
                        }

                        $isActiveTank = $currentTank && $currentTank->id === $tank->id;
                    @endphp

                    <li>
                        <a
                            href="{{ route('depot.operations.daily-dips', array_merge(request()->except('tank'), ['tank' => $tank->id])) }}"
                            class="relative block px-4 py-3 transition
                                {{ $isActiveTank
                                    ? 'bg-emerald-50 text-gray-900 shadow-sm border-l-4 border-emerald-500'
                                    : 'hover:bg-white/80 text-gray-800 border-l-4 border-transparent' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-semibold uppercase tracking-wide">
                                        {{ $tank->depot->name }} — {{ $tank->product->name }}
                                    </p>
                                    <p class="truncate text-[11px] text-gray-500">
                                        Tank T{{ $tank->id }}
                                    </p>
                                </div>
                                <span class="inline-flex h-2.5 w-2.5 flex-shrink-0 rounded-full {{ $dotClass }}"></span>
                            </div>
                            <p class="mt-1 text-[11px] {{ $isActiveTank ? 'text-gray-600' : $statusClass }}">
                                {{ $statusText }}
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        {{-- WORKSHEET --}}
        <section class="flex-1 space-y-4">

            @if(! $currentTank)
                <div class="h-52 flex items-center justify-center bg-white rounded-2xl border border-dashed border-gray-200 text-sm text-gray-500">
                    No tanks available for this filter.
                </div>
            @else

                {{-- TANK HEADER + STATUS + LOCK --}}
                <div class="bg-white/95 rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">
                                {{ $currentTank->depot->name }} — {{ $currentTank->product->name }}
                                <span class="text-gray-500">(T{{ $currentTank->id }})</span>
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">
                                Opening then closing dips for {{ $forDate->toDateString() }}.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-[11px]">
                            @php
                                $statusLabel = 'Not started';
                                $statusColor = 'bg-gray-100 text-gray-700';

                                if ($currentDay) {
                                    if ($currentDay->status === 'locked') {
                                        $statusLabel = 'Locked';
                                        $statusColor = 'bg-emerald-50 text-emerald-700';
                                    } elseif ($currentDay->closing_actual_l_20 !== null) {
                                        $statusLabel = 'Closing saved';
                                        $statusColor = 'bg-indigo-50 text-indigo-700';
                                    } elseif ($currentDay->opening_l_20 !== null) {
                                        $statusLabel = 'Opening saved';
                                        $statusColor = 'bg-sky-50 text-sky-700';
                                    } else {
                                        $statusLabel = 'In progress';
                                        $statusColor = 'bg-gray-100 text-gray-700';
                                    }
                                }

                                $canLock = $currentDay
                                    && $currentDay->status !== 'locked'
                                    && $currentDay->opening_l_20 !== null
                                    && $currentDay->closing_actual_l_20 !== null;
                            @endphp

                            <span class="inline-flex items-center rounded-full px-3 py-1 border {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span -->

                            @if($canLock)
                                <button
                                    id="btnLockDay"
                                    data-lock-url="{{ $lockUrl }}"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-600 text-white px-3 py-1 font-medium shadow-sm hover:bg-emerald-700 active:scale-[0.97]"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-white/80"></span>
                                    Lock day
                                </button>
                            @elseif($currentDay && $currentDay->status === 'locked')
                                <span class="text-[11px] text-gray-400">
                                    Day is locked – dips read-only.
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- VARIANCE ADJUSTMENT INFO/BUTTON (above cards) --}}
                @if($currentDay && $currentDay->status === 'locked' && isset($currentDay->variance_l_20))
                  @if(isset($adjustEntry) && $adjustEntry)
                    <div class="mt-6 mb-4 rounded-xl border border-blue-200 bg-blue-50/80 p-4 flex items-center gap-4">
                      <div class="flex-1">
                        <div class="text-xs font-semibold text-blue-700 flex items-center gap-2">
                          <svg class="inline h-4 w-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                          Depot pool adjusted:
                          <span class="{{ $adjustEntry->volume_20_l >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-bold">
                            {{ $adjustEntry->volume_20_l >= 0 ? '+' : '' }}{{ number_format($adjustEntry->volume_20_l, 0) }} L
                          </span>
                        </div>
                        <div class="text-xs text-blue-600 mt-1">
                          <span class="font-semibold">By:</span>
                          <span class="text-blue-900 font-medium">{{ $varianceAdjustedBy }}</span>
                          <span class="ml-2 text-blue-500">on {{ optional($adjustEntry->created_at)->format('Y-m-d H:i') }}</span>
                        </div>
                      </div>
                    </div>
                  @elseif(abs($currentDay->variance_l_20) > $varianceTolerance && $daysOld <= $maxDaysOld && $currentDay->variance_l_20 != 0 && !isset($adjustEntry))
                    <div class="mt-6 mb-4 rounded-xl border border-blue-200 bg-blue-50/80 p-4 flex items-center gap-4">
                      <div class="flex-1">
                        <div class="text-xs font-semibold text-blue-700 flex items-center gap-2">
                          <svg class="inline h-4 w-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                          Variance after locking:
                          <span class="{{ $currentDay->variance_l_20 >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-bold">
                            {{ $currentDay->variance_l_20 >= 0 ? '+' : '' }}{{ number_format($currentDay->variance_l_20, 0) }} L
                          </span>
                        </div>
                        <div class="text-xs text-blue-600 mt-1">
                          <span class="font-semibold">No adjustment yet.</span>
                          <span class="ml-2 text-blue-500">(within {{ $maxDaysOld }} days)</span>
                        </div>
                      </div>
                      <button
                        type="button"
                        class="ml-auto px-4 py-2 rounded-full bg-blue-600 text-white text-xs font-semibold shadow-sm hover:bg-blue-700 active:scale-[0.97] transition"
                        onclick="openDepotPoolAdjustModal({{ $currentDay->variance_l_20 }}, {
                          depot_id: {{ $currentTank->depot_id }},
                          tank_id: {{ $currentTank->id }},
                          product_id: {{ $currentTank->product_id }},
                          date: '{{ $forDate->toDateString() }}',
                          variance_l_20: {{ $currentDay->variance_l_20 }}
                        })"
                      >
                        Adjust Depot Pool
                      </button>
                    </div>
                  @endif
                @endif

                {{-- OPENING / CLOSING WIZARD CARDS --}}
                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Opening --}}
                    <div class="bg-white/95 rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-col justify-between">
                        <div>
                            <p class="text-[11px] uppercase text-gray-500 font-semibold">Step 1 · Opening dip</p>
                            <p class="text-xs text-gray-400">Morning stock in litres.</p>

                            <p class="mt-3 text-3xl font-semibold text-gray-900">
                                {{ $formatLitres($currentDay?->opening_l_20) }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400">
                                Opening @20°C
                            </p>
                        </div>

                        @php
                            $hasOpening = $currentDay && $currentDay->opening_l_20 !== null;
                        @endphp

                        <button
                            id="btnOpenOpeningDip"
                            data-dip-mode="{{ $hasOpening ? 'edit' : 'create' }}"
                            data-volume-observed="{{ $openingDip->volume_observed_l ?? '' }}"
                            data-volume-20="{{ $openingDip->volume_20_l ?? ($currentDay->opening_l_20 ?? '') }}"
                            data-dip-height="{{ $openingDip->dip_height_cm ?? '' }}"
                            data-temperature="{{ $openingDip->temperature_c ?? '' }}"
                            data-density="{{ $openingDip->density_kg_l ?? '' }}"
                            data-note="{{ $openingDip->note ?? '' }}"
                            @if($isLocked) disabled aria-disabled="true" @endif
                            class="mt-4 inline-flex items-center justify-center rounded-full bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 active:scale-[0.97]
                                   @if($isLocked) opacity-40 grayscale cursor-not-allowed active:scale-100 @endif"
                        >
                            {{ $hasOpening ? 'Edit opening dip' : 'Record opening dip' }}
                        </button>
                    </div>

                    {{-- Closing --}}
                    <div class="bg-white/95 rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-col justify-between">
                        <div>
                            <p class="text-[11px] uppercase text-gray-500 font-semibold">Step 2 · Closing dip</p>
                            <p class="text-xs text-gray-400">Evening stock in litres.</p>

                            <p class="mt-3 text-3xl font-semibold text-gray-900">
                                {{ $formatLitres($currentDay?->closing_actual_l_20) }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400">
                                Closing @20°C
                            </p>
                        </div>

                        @php
                            $hasClosing = $currentDay && $currentDay->closing_actual_l_20 !== null;
                        @endphp

                        <button
                            id="btnOpenClosingDip"
                            data-dip-mode="{{ $hasClosing ? 'edit' : 'create' }}"
                            data-volume-observed="{{ $closingDip->volume_observed_l ?? '' }}"
                            data-volume-20="{{ $closingDip->volume_20_l ?? ($currentDay->closing_actual_l_20 ?? '') }}"
                            data-dip-height="{{ $closingDip->dip_height_cm ?? '' }}"
                            data-temperature="{{ $closingDip->temperature_c ?? '' }}"
                            data-density="{{ $closingDip->density_kg_l ?? '' }}"
                            data-note="{{ $closingDip->note ?? '' }}"
                            @if($isLocked) disabled aria-disabled="true" @endif
                            class="mt-4 inline-flex items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 active:scale-[0.97]
                                   @if($isLocked) opacity-40 grayscale cursor-not-allowed active:scale-100 @endif"
                        >
                            {{ $hasClosing ? 'Edit closing dip' : 'Record closing dip' }}
                        </button>
                    </div>
                </div>

                {{-- SUMMARY CARDS: EXPECTED + VARIANCE --}}
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-gray-100 bg-white/95 p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Opening @20°
                        </p>
                        <div class="mt-2 flex flex-col gap-1">
                            @php
                                // Recorded opening for the day
                                $openingBalance = $currentDay?->opening_l_20 ?? null;
                                // Expected opening for the day (from backend, net sum up to prev day)
                                $expectedOpening = $movementCurrent['expected_opening_l_20'] ?? null;
                                // Variance between recorded and expected opening
                                $openingVariance = ($openingBalance !== null && $expectedOpening !== null)
                                    ? ($openingBalance - $expectedOpening)
                                    : null;
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-semibold text-gray-900">
                                    {{ $formatLitres($openingBalance) }}
                                </span>
                                <span class="text-xs text-gray-400">Recorded</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-semibold text-indigo-700">
                                    {{ $formatLitres($expectedOpening) }}
                                </span>
                                <span class="text-xs text-gray-400">Expected</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-semibold {{ $openingVariance !== null && $openingVariance >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    @if($openingVariance !== null)
                                        {{ $openingVariance >= 0 ? '+' : '' }}{{ number_format($openingVariance, 0) }} L
                                    @else
                                        —
                                    @endif
                                </span>
                                <span class="text-xs text-gray-400">Variance</span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            <span class="font-medium">Prev closing:</span>
                            {{ isset($movementCurrent['opening_balance_prev_closing_l_20']) ? number_format($movementCurrent['opening_balance_prev_closing_l_20'], 0) . ' L' : '—' }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-white/95 p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Expected closing @20°
                        </p>
                        <p class="mt-2 text-xl font-semibold text-gray-900">
                            @php
                                // Show calculation for expected closing: opening + offloads - loads + adjustments
                                $expectedClosing = null;
                                if ($currentDay && isset($movementCurrent['offloads_l'], $movementCurrent['loads_l'], $movementCurrent['adj_l_20'])) {
                                    $expectedClosing = ($currentDay->opening_l_20 ?? 0)
                                        + ($movementCurrent['offloads_l'] ?? 0)
                                        - ($movementCurrent['loads_l'] ?? 0)
                                        + ($movementCurrent['adj_l_20'] ?? 0);
                                }
                            @endphp
                            {{ $formatLitres($expectedClosing) }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-white/95 p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Actual closing &amp; variance
                        </p>
                        <p class="mt-2 text-xl font-semibold text-gray-900">
                            {{ $formatLitres($currentDay?->closing_actual_l_20) }}
                        </p>
                        <p class="mt-1 text-[11px] text-gray-400">
                            @if($currentDay && $currentDay->variance_l_20 !== null)
                                <span class="{{ $currentDay->variance_l_20 >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $currentDay->variance_l_20 >= 0 ? '+' : '' }}
                                    {{ number_format((float)$currentDay->variance_l_20, 0) }} L
                                    ({{ number_format((float)$currentDay->variance_pct, 2) }}%)
                                </span>
                            @else
                                Variance —
                            @endif
                        </p>
                    </div>
                </div>

                {{-- MOVEMENTS SUMMARY (structure kept) --}}
                <div class="rounded-2xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-xs font-semibold text-gray-700">
                            Movements summary
                        </p>
                        <p class="text-[11px] text-gray-400">
                            Offloads &amp; loads for this tank and date
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="rounded-xl bg-gray-50/80 px-4 py-3">
                            <p class="text-[11px] uppercase text-gray-500 font-semibold">Offloads</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                @if($movOff !== null)
                                    {{ number_format($movOff, 0) }} L
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50/80 px-4 py-3">
                            <p class="text-[11px] uppercase text-gray-500 font-semibold">Loads</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                @if($movLoad !== null)
                                    {{ number_format($movLoad, 0) }} L
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50/80 px-4 py-3">
                            <p class="text-[11px] uppercase text-gray-500 font-semibold">Adjustments</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                @if($movAdj !== null && $movAdj != 0)
                                    <span class="{{ $movAdj > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $movAdj > 0 ? '+' : '' }}{{ number_format($movAdj, 0) }} L
                                    </span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50/80 px-4 py-3">
                            <p class="text-[11px] uppercase text-gray-500 font-semibold">Net</p>
                            <p class="mt-2 text-lg font-semibold {{ $movNet >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                @if($movNet !== null)
                                    {{ $movNet >= 0 ? '+' : '' }}{{ number_format($movNet, 0) }} L
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

            @endif
        </section>
    </div>
</div>

{{-- MODAL --}}
<div id="dipWizardModal"
     class="fixed inset-0 z-60 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-2xl border border-white/60 bg-white/95 shadow-2xl">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 id="dipModalTitle" class="text-base font-semibold text-gray-900">
                Record dip
            </h2>
            <p id="dipModalSubtitle" class="mt-1 text-xs text-gray-500">
                Enter dip details.
            </p>
        </div>

        <div class="px-5 py-4">
            <form id="dipWizardForm" method="POST" class="space-y-4">
                @csrf
                {{-- required by controller --}}
                <input type="hidden" name="tank_id" value="{{ $currentTank->id ?? '' }}">
                <input type="hidden" name="date" value="{{ $forDate->toDateString() }}">
                {{-- for JS only: opening/closing --}}
                <input type="hidden" id="dip_kind_input" name="kind" value="opening">

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium text-gray-600">Observed volume (L)</label>
                        <input type="number" step="0.01" name="volume_observed_l"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/60">
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600">Volume @ 20°C (L)</label>
                        <input type="number" step="0.01" name="volume_20_l"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/60">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600">Dip height (cm)</label>
                        <input type="number" step="0.01" name="dip_height_cm"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/60">
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600">Temperature (°C)</label>
                        <input type="number" step="0.01" name="temperature_c"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/60">
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600">Density (kg/L)</label>
                        <input type="number" step="0.0001" name="density_kg_l"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/60">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-600">Note (optional)</label>
                    <textarea name="note" rows="2"
                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/60"></textarea>
                </div>

                {{-- inline validation errors --}}
                <div id="dipWizardErrors"
                     class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-dip-modal-close
                            class="px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>

                    <button id="dipWizardSubmitBtn"
                            class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-black disabled:opacity-60">
                        Save dip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- LIGHT CONFIRM MODAL (PATCH) --}}
<div id="lockConfirmModal" class="fixed inset-0 z-60 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-2xl border border-white/60 bg-white/95 shadow-2xl">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16Zm3.78-9.03a.75.75 0 00-1.06-1.06L9.25 11.38 7.28 9.41a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.06 0l4-4Z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900">Lock this day?</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        You will not be able to edit dips afterwards.
                    </p>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 flex items-center justify-end gap-2">
            <button type="button" data-lock-confirm-cancel
                    class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 active:scale-[0.98] transition">
                Cancel
            </button>

            <button type="button" data-lock-confirm-ok
                    class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 active:scale-[0.98] transition">
                Yes, lock day
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal      = document.getElementById('dipWizardModal');
    const form       = document.getElementById('dipWizardForm');
    const submitBtn  = document.getElementById('dipWizardSubmitBtn');
    const kindInput  = document.getElementById('dip_kind_input');
    const titleEl    = document.getElementById('dipModalTitle');
    const subtitleEl = document.getElementById('dipModalSubtitle');
    const errorsEl   = document.getElementById('dipWizardErrors');

    const btnOpening = document.getElementById('btnOpenOpeningDip');
    const btnClosing = document.getElementById('btnOpenClosingDip');
    const lockBtn    = document.getElementById('btnLockDay');

    const openingUrl = "{{ $openingUrl }}";
    const closingUrl = "{{ $closingUrl }}";
    const lockUrl    = "{{ $lockUrl }}";
    const currentTankId = "{{ $currentTank->id ?? '' }}";

    // PATCH: server-known lock state
    const isLocked = {{ $isLocked ? 'true' : 'false' }};

    // Filter: reset date back to real today and submit
    const filterForm = document.getElementById('dipsFilterForm');
    const dateInput  = filterForm?.querySelector('input[name="date"]');
    const resetBtn   = document.getElementById('btnResetToToday');
    const today      = "{{ \Illuminate\Support\Carbon::today()->toDateString() }}";

    resetBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        if (dateInput) dateInput.value = today;
        filterForm?.submit();
    });

    function fillFormFromDataset(btn) {
        const map = {
            'volume_observed_l': btn.dataset.volumeObserved || '',
            'volume_20_l':       btn.dataset.volume20       || '',
            'dip_height_cm':     btn.dataset.dipHeight      || '',
            'temperature_c':     btn.dataset.temperature    || '',
            'density_kg_l':      btn.dataset.density        || '',
            'note':              btn.dataset.note           || '',
        };

        Object.keys(map).forEach(name => {
            const field = form.querySelector('[name="' + name + '"]');
            if (!field) return;
            field.value = map[name];
        });
    }

    function clearFormFields() {
        ['volume_observed_l','volume_20_l','dip_height_cm','temperature_c','density_kg_l','note']
            .forEach(name => {
                const field = form.querySelector('[name="' + name + '"]');
                if (field) field.value = '';
            });
    }

    function openModal(kind, mode, sourceBtn) {
        kindInput.value = kind;

        errorsEl.classList.add('hidden');
        errorsEl.textContent = '';

        if (mode === 'edit' && sourceBtn) {
            fillFormFromDataset(sourceBtn);
        } else {
            clearFormFields();
        }

        if (kind === 'opening') {
            titleEl.textContent = mode === 'edit'
                ? 'Edit opening dip'
                : 'Record opening dip';
            subtitleEl.textContent = 'Step 1 of 2 · Morning stock.';
            form.action = openingUrl;
        } else {
            titleEl.textContent = mode === 'edit'
                ? 'Edit closing dip'
                : 'Record closing dip';
            subtitleEl.textContent = 'Step 2 of 2 · Evening stock.';
            form.action = closingUrl;
        }

        submitBtn.textContent = 'Save dip';
        submitBtn.disabled = false;

        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    btnOpening?.addEventListener('click', (e) => {
        e.preventDefault();
        // PATCH: hard stop if locked (extra safety)
        if (isLocked) return;

        const mode = btnOpening.dataset.dipMode || 'create';
        openModal('opening', mode, btnOpening);
    });

    btnClosing?.addEventListener('click', (e) => {
        e.preventDefault();
        // PATCH: hard stop if locked (extra safety)
        if (isLocked) return;

        const mode = btnClosing.dataset.dipMode || 'create';
        openModal('closing', mode, btnClosing);
    });

    modal.querySelectorAll('[data-dip-modal-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal();
        });
    });

    // AJAX submit
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorsEl.classList.add('hidden');
        errorsEl.textContent = '';

        // PATCH: extra safety (should never happen because buttons disabled)
        if (isLocked) {
            errorsEl.textContent = 'Day is locked. Dips are read-only.';
            errorsEl.classList.remove('hidden');
            return;
        }

        if (!form.action) {
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';

        try {
            const resp = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.querySelector('input[name=_token]').value,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });

            if (!resp.ok) {
                let msg = 'Could not save dip. Please check your inputs.';
                try {
                    const data = await resp.json();
                    if (data && data.errors) {
                        msg = Object.values(data.errors).flat().join(' ');
                    }
                } catch (e2) {}

                errorsEl.textContent = msg;
                errorsEl.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save dip';
                return;
            }

            const data = await resp.json();
            if (data && data.ok) {
                window.location.reload();
            } else {
                errorsEl.textContent = 'Could not save dip. Please try again.';
                errorsEl.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save dip';
            }
        } catch (err) {
            errorsEl.textContent = 'Network error. Please try again.';
            errorsEl.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save dip';
        }
    });

    // Lock day (PATCH: light confirm modal instead of browser confirm)
    const lockConfirmModal = document.getElementById('lockConfirmModal');
    const lockConfirmCancel = lockConfirmModal?.querySelector('[data-lock-confirm-cancel]');
    const lockConfirmOk = lockConfirmModal?.querySelector('[data-lock-confirm-ok]');

    function openLockConfirm() {
        lockConfirmModal?.classList.remove('hidden');
    }
    function closeLockConfirm() {
        lockConfirmModal?.classList.add('hidden');
    }

    lockConfirmCancel?.addEventListener('click', (e) => {
        e.preventDefault();
        closeLockConfirm();
    });

    // click outside closes
    lockConfirmModal?.addEventListener('click', (e) => {
        if (e.target === lockConfirmModal) closeLockConfirm();
    });

    // ESC closes
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lockConfirmModal && !lockConfirmModal.classList.contains('hidden')) {
            closeLockConfirm();
        }
    });

    lockBtn?.addEventListener('click', async (e) => {
        e.preventDefault();
        const url = lockUrl;
        if (!url || !currentTankId) return;

        openLockConfirm();

        // bind one-time OK handler per open
        const handler = async (ev) => {
            ev.preventDefault();
            lockConfirmOk?.removeEventListener('click', handler);
            closeLockConfirm();

            lockBtn.disabled = true;
            lockBtn.textContent = 'Locking…';

            try {
                const formData = new FormData();
                formData.append('tank_id', currentTankId);

                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            || form.querySelector('input[name=_token]').value,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (!resp.ok) {
                    lockBtn.disabled = false;
                    lockBtn.textContent = 'Lock day';
                    return;
                }

                const data = await resp.json();
                if (data && data.ok) {
                    window.location.reload();
                } else {
                    lockBtn.disabled = false;
                    lockBtn.textContent = 'Lock day';
                }
            } catch (err) {
                lockBtn.disabled = false;
                lockBtn.textContent = 'Lock day';
            }
        };

        lockConfirmOk?.addEventListener('click', handler);
    });
});
</script>
@endpush