{{-- resources/views/vendor/depot-stock/dips/index.blade.php --}}
@extends('depot-stock::layouts.app')

@section('title','Tank Dips')

@section('content')
@php
    use Illuminate\Support\Carbon;

    $last = $lastDip ?? null;
    if ($last && !($last->date instanceof Carbon)) {
        try { $last->date = Carbon::parse($last->date); } catch (\Throwable $e) {}
    }

    $book       = (float)($bookBalance20 ?? 0);
    $lastVol    = (float)($last?->volume_20 ?? 0);
    $levelPct   = $book > 0 ? max(0, min(100, ($lastVol / $book) * 100)) : 0;
    $recordsTotal = method_exists($dips, 'total') ? $dips->total() : $dips->count();
@endphp

<div class="max-w-7xl mx-auto space-y-6">

  {{-- HEADER --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold text-gray-900">Tank Dips</h1>
      <p class="mt-1 text-sm text-gray-500">
        Record daily dips, auto-convert to @20&nbsp;°C, and compare with book stock per tank.
      </p>
    </div>

    <div class="flex items-center gap-2">
      <button type="button"
              id="btnDipRefresh"
              class="hidden md:inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:border-gray-300 hover:bg-gray-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 19A9 9 0 0 0 19 5" />
        </svg>
        Refresh
      </button>

      <button type="button"
              id="btnNewDip"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition active:scale-[0.98] hover:bg-indigo-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New DIP
      </button>
    </div>
  </div>

  {{-- TOP ROW: TANK FILTER + KPIs + BARREL --}}
  <div class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    {{-- LEFT: tank filter + KPIs --}}
    <div class="space-y-4">
      {{-- Tank filter chips --}}
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('depot.dips.index', array_merge(request()->except('tank','page'), ['tank'=>null])) }}"
           class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium
                  {{ request('tank') ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-900 bg-gray-900 text-white shadow-sm' }}">
          <span class="h-2 w-2 rounded-full {{ request('tank') ? 'bg-gray-300' : 'bg-white' }}"></span>
          All tanks
        </a>
        @foreach($tanks as $t)
          <a href="{{ route('depot.dips.index', array_merge(request()->except('page'), ['tank'=>$t->id])) }}"
             class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium
                    {{ (string)request('tank')===(string)$t->id ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm' : 'border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            <span class="h-2 w-2 rounded-full" style="background-color:#{{ substr(md5($t->id),0,6) }}"></span>
            {{ $t->depot->name }} — {{ $t->product->name }} (T{{ $t->id }})
          </a>
        @endforeach
      </div>

      {{-- KPIs --}}
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-100 bg-white/90 p-4 shadow-sm">
          <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Book balance @20°</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900" id="kpiBook">
            {{ number_format($book, 0) }}
            <span class="text-sm font-normal text-gray-400">L</span>
          </p>
          <p class="mt-1 text-[11px] text-gray-400">Based on all movements for the selected tank.</p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white/90 p-4 shadow-sm">
          <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Last recorded dip</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900" id="lastDipDateText">
            {{ $last?->date?->format('Y-m-d') ?? '—' }}
          </p>
          <p class="mt-1 text-[11px] text-gray-400" id="lastDipVolText">
            @if($last)
              Last volume @20°: {{ number_format($lastVol, 0) }} L
            @else
              No dips recorded yet.
            @endif
          </p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white/90 p-4 shadow-sm">
          <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Records this month</p>
          <p id="kpiThisMonth" class="mt-2 text-2xl font-semibold text-gray-900">
            {{ (int)($countThisMonth ?? 0) }}
          </p>
          <p class="mt-1 text-[11px] text-gray-400">How disciplined you’ve been with daily dips.</p>
        </div>
      </div>
    </div>

{{-- RIGHT: barrel visual --}}
    <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 p-4 sm:p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Live tank snapshot</p>
        <p class="mt-1 text-sm text-slate-800">
            Last dip vs book balance (visual). Changes automatically with tank &amp; date filters.
        </p>
        </div>
        <span class="rounded-full bg-slate-800/80 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-100">
        Visual only
        </span>
    </div>

    <div class="mt-4 flex items-center gap-6">
        {{-- Cylindrical barrel --}}
        <div class="relative mx-auto flex h-44 w-40 items-center justify-center">
        <div class="relative h-40 w-32">
            {{-- Top ellipse (rim) --}}
            <div class="absolute inset-x-2 top-0 h-6 rounded-full border border-slate-400 bg-gradient-to-b from-slate-100 via-slate-200 to-slate-400 shadow-md shadow-black/20"></div>

            {{-- Top inner hole (like refinery graphic) --}}
            <div class="absolute inset-x-8 top-2.5 h-3 rounded-full bg-slate-500/40"></div>
            <div class="absolute inset-x-10 top-3 h-2 rounded-full bg-slate-900/70"></div>

            {{-- Barrel body (outer shell, with overflow for liquid) --}}
            <div class="absolute inset-x-2 top-3 bottom-3 overflow-hidden rounded-[26px] border border-slate-300 bg-gradient-to-b from-slate-300 via-slate-200 to-slate-300">
            {{-- Liquid fill --}}
            <div id="tankLiquid"
                class="absolute inset-x-0 bottom-0 rounded-t-[26px] bg-gradient-to-t from-cyan-500 via-sky-400 to-indigo-400 transition-[height] duration-700 ease-out"
                style="height:0%"
                data-level="{{ $levelPct }}">
            </div>
            </div>

            {{-- Bottom ellipse --}}
            <div class="absolute inset-x-2 bottom-0 h-6 rounded-full border border-slate-400 bg-gradient-to-t from-slate-600 via-slate-500 to-slate-300 shadow-inner"></div>

            {{-- Floor shadow --}}
            <div class="absolute -bottom-4 left-1/2 h-4 w-28 -translate-x-1/2 rounded-full bg-black/30 blur-md"></div>
        </div>
        </div>

        {{-- Legend --}}
        <div class="space-y-2 text-xs text-slate-800">
        <div class="flex items-center justify-between gap-3">
            <span class="text-slate-600">Fill vs book</span>
            <span id="tankFillPctText" class="font-semibold">
            {{ $book > 0 ? number_format($levelPct, 1).'%' : '—' }}
            </span>
        </div>
        <div class="flex items-center justify-between gap-3">
            <span class="text-slate-500">Book @20°</span>
            <span id="tankBookText">{{ number_format($book, 0) }} L</span>
        </div>
        <div class="flex items-center justify-between gap-3">
            <span class="text-slate-500">Last dip @20°</span>
            <span id="tankLastDipText">
            {{ $last ? number_format($lastVol, 0).' L' : '—' }}
            </span>
        </div>
        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-300">
            <div class="h-full rounded-full bg-cyan-500 transition-all duration-700"
                id="tankProgress"
                style="width:0%"
                data-level="{{ $levelPct }}">
            </div>
        </div>
        </div>
    </div>
    </div>
  </div>

  {{-- RANGE & MONTH FILTERS --}}
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    {{-- Tabs --}}
    <div class="inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
      @php $tab = request('range','all'); @endphp
      @foreach(['all'=>'All time','month'=>'This month','week'=>'This week'] as $value => $label)
        <a href="{{ route('depot.dips.index', array_merge(request()->except('page'), ['range'=>$value])) }}"
           class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-medium
                  {{ $tab===$value ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
          {{ $label }}
        </a>
      @endforeach
    </div>

    {{-- Month picker --}}
    <div class="flex items-center gap-2">
      <form method="GET" class="flex items-center gap-2">
        @foreach(request()->except(['month','page']) as $k => $v)
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <input type="month"
               name="month"
               value="{{ request('month') }}"
               class="w-40 rounded-lg border-gray-300 bg-white text-xs focus:border-indigo-500 focus:ring-indigo-500">
        <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
          Go
        </button>
      </form>
      <a href="{{ route('depot.dips.index') }}"
         class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
        Reset
      </a>
    </div>
  </div>

  {{-- HISTORY TABLE --}}
  <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
      <div>
        <h2 class="text-sm font-semibold text-gray-900">Dip history</h2>
        <p class="text-[11px] text-gray-500">
          {{ $recordsTotal }} record{{ $recordsTotal === 1 ? '' : 's' }} in view.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('depot.dips.export', array_merge(
                ['depot' => request('tank') ?: 'all'],
                request()->only(['tank','range','month'])
             )) }}"
           class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16M4 9h16M9 14h11M9 19h11M4 14h.01M4 19h.01"/>
          </svg>
          Export CSV
        </a>
      </div>
    </div>

    @if($dips->isEmpty())
      <div id="dipsEmptyState" class="p-10 text-center text-sm text-gray-500">
        No dips yet for this view — click
        <span class="font-semibold text-gray-800">New DIP</span>
        to record the first one.
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-4 py-2">Date</th>
            <th class="px-4 py-2">Tank</th>
            <th class="px-4 py-2 text-right">Dip (cm)</th>
            <th class="px-4 py-2 text-right">Observed (L)</th>
            <th class="px-4 py-2 text-right">@20°C (L)</th>
            <th class="px-4 py-2 text-right">Book @20° (L)</th>
            <th class="px-4 py-2 text-right">Variance</th>
            <th class="px-4 py-2 text-right"></th>
          </tr>
          </thead>
          <tbody id="dipsTbody" class="divide-y divide-gray-100 bg-white">
          @foreach($dips as $dip)
            @php
                $d       = $dip->date instanceof Carbon ? $dip->date : Carbon::parse($dip->date);
                $book20  = (float)($dip->book_volume_20 ?? 0);
                $v20     = (float)($dip->volume_20 ?? 0);
                $variance = $v20 - $book20;
            @endphp
            <tr class="transition-colors hover:bg-gray-50/70">
              <td class="whitespace-nowrap px-4 py-2 text-gray-800">{{ $d->format('Y-m-d') }}</td>
              <td class="whitespace-nowrap px-4 py-2 text-gray-700">
                {{ $dip->tank->depot->name }} — {{ $dip->tank->product->name }} (T{{ $dip->tank_id }})
              </td>
              <td class="px-4 py-2 text-right text-gray-700">
                {{ number_format($dip->dip_height ?? 0, 2) }}
              </td>
              <td class="px-4 py-2 text-right text-gray-700">
                {{ number_format($dip->observed_volume ?? 0, 0) }}
              </td>
              <td class="px-4 py-2 text-right text-gray-800">
                {{ number_format($v20, 0) }}
              </td>
              <td class="px-4 py-2 text-right text-gray-700">
                {{ $book20 ? number_format($book20, 0) : '—' }}
              </td>
              <td class="px-4 py-2 text-right">
                @if($book20)
                  @php $cls = $variance >= 0 ? 'text-emerald-600' : 'text-rose-600'; @endphp
                  <span class="font-medium {{ $cls }}">
                    {{ $variance >= 0 ? '+' : '' }}{{ number_format($variance, 0) }} L
                  </span>
                @else
                  <span class="text-gray-400">—</span>
                @endif
              </td>
              <td class="whitespace-nowrap px-4 py-2 text-right">
                <a href="{{ route('depot.dips.show', $dip) }}"
                   class="text-xs text-indigo-600 hover:text-indigo-700 hover:underline">
                  View
                </a>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      @if(method_exists($dips, 'links'))
        <div class="border-t border-gray-100 px-5 py-3">
          {{ $dips->withQueryString()->links() }}
        </div>
      @endif
    @endif
  </div>
</div>

{{-- MODAL: NEW DIP --}}
<div id="dipModal" class="fixed inset-0 z-[130] hidden">
  <button type="button" class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-close-dip></button>

  <div class="absolute inset-0 flex items-start justify-center p-4 md:p-8 overflow-y-auto">
    <div class="w-full max-w-xl bg-white/95 rounded-2xl shadow-2xl border border-gray-100 mt-10 animate-[slideUp_0.18s_ease-out]">
      <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50/80 rounded-t-2xl">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">New tank dip</div>
          <div class="text-sm text-gray-700">We auto-calculate volumes, but you can still overwrite them.</div>
        </div>
        <button type="button" class="text-gray-500 hover:text-gray-800" data-close-dip>✕</button>
      </div>

      <form id="dipForm"
            method="POST"
            action="{{ route('depot.dips.store', ['depot' => request()->route('depot')?->id ?? 'global']) }}"
            class="p-5 space-y-4 text-sm">
        @csrf

        <div>
          <label class="text-[11px] uppercase tracking-wide text-gray-500">Tank</label>
          <select name="tank_id"
                  id="tank_id"
                  class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            <option value="">Select tank…</option>
            @foreach($tanks as $tank)
              <option value="{{ $tank->id }}">
                {{ $tank->depot->name }} — {{ $tank->product->name }} (T{{ $tank->id }})
              </option>
            @endforeach
          </select>
          <p id="err_tank_id" class="mt-1 text-[11px] text-rose-600 hidden"></p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="text-[11px] uppercase tracking-wide text-gray-500">Date</label>
            <input type="date"
                   name="date"
                   id="date"
                   value="{{ now()->toDateString() }}"
                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            <p id="err_date" class="mt-1 text-[11px] text-rose-600 hidden"></p>
          </div>
          <div>
            <label class="text-[11px] uppercase tracking-wide text-gray-500">Dip height (cm)</label>
            <input type="number"
                   step="0.01"
                   min="0"
                   name="dip_height"
                   id="dip_height"
                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            <p id="err_dip_height" class="mt-1 text-[11px] text-rose-600 hidden"></p>
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="text-[11px] uppercase tracking-wide text-gray-500">Temperature (°C)</label>
            <input type="number"
                   step="0.1"
                   name="temperature"
                   id="temperature"
                   value="25"
                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            <p id="err_temperature" class="mt-1 text-[11px] text-rose-600 hidden"></p>
          </div>
          <div>
            <label class="text-[11px] uppercase tracking-wide text-gray-500">Density (kg/L)</label>
            <input type="number"
                   step="0.0001"
                   name="density"
                   id="density"
                   value="0.835"
                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            <p id="err_density" class="mt-1 text-[11px] text-rose-600 hidden"></p>
          </div>
        </div>

        {{-- Auto-calculated but editable volumes --}}
        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/70 p-3">
          <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              Volumes (auto-filled, you can edit)
            </p>
            <button type="button"
                    id="btnRecalcVolumes"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-[11px] text-gray-700 hover:bg-gray-100">
              Recalculate
            </button>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="text-[11px] uppercase tracking-wide text-gray-500">Observed volume (L)</label>
              <input type="number"
                     step="0.001"
                     min="0"
                     name="observed_volume"
                     id="observed_volume"
                     class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                     placeholder="Will auto-fill if left blank">
              <p id="err_observed_volume" class="mt-1 text-[11px] text-rose-600 hidden"></p>
            </div>
            <div>
              <label class="text-[11px] uppercase tracking-wide text-gray-500">Volume @20°C (L)</label>
              <input type="number"
                     step="0.001"
                     min="0"
                     name="volume_20"
                     id="volume_20"
                     class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                     placeholder="Will auto-fill if left blank">
              <p id="err_volume_20" class="mt-1 text-[11px] text-rose-600 hidden"></p>
            </div>
          </div>
        </div>

        <div>
          <label class="text-[11px] uppercase tracking-wide text-gray-500">Note (optional)</label>
          <textarea name="note"
                    id="note"
                    rows="2"
                    class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>

        {{-- LIVE PREVIEW --}}
        <div class="grid gap-3 md:grid-cols-3">
          <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-3">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Observed (L)</p>
            <p id="preview_observed" class="mt-2 text-lg font-semibold text-gray-900">—</p>
          </div>
          <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-3">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">@20°C (L)</p>
            <p id="preview_at20" class="mt-2 text-lg font-semibold text-gray-900">—</p>
          </div>
          <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-3">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Variance vs book</p>
            <p id="preview_variance" class="mt-2 text-lg font-semibold text-gray-900">—</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 mt-2">
          <button type="button"
                  class="rounded-lg bg-gray-100 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200"
                  data-close-dip>
            Cancel
          </button>
          <button id="dipSaveBtn"
                  type="submit"
                  class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-5 py-2 text-xs font-medium text-white shadow hover:bg-indigo-700 active:scale-[0.98] transition">
            Save DIP
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('styles')
<style>
@keyframes slideUp {
  0%   { transform: translateY(16px); opacity: 0; }
  100% { transform: translateY(0);    opacity: 1; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // -------- Barrel animation --------
  const tankLiquid   = document.getElementById('tankLiquid');
  const tankProgress = document.getElementById('tankProgress');
  if (tankLiquid) {
    const level = parseFloat(tankLiquid.dataset.level || '0');
    requestAnimationFrame(() => {
      tankLiquid.style.height = level + '%';
      if (tankProgress) tankProgress.style.width = level + '%';
    });
  }

  // -------- Simple toast helper --------
  function showToast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = 'fixed top-4 right-4 z-[160] rounded-lg px-4 py-2 text-xs font-medium text-white shadow-lg ' +
      (type === 'success' ? 'bg-emerald-600' : 'bg-rose-600');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => {
      el.classList.add('opacity-0', 'translate-y-1', 'transition');
      setTimeout(() => el.remove(), 250);
    }, 2600);
  }

  // -------- Modal open/close --------
  const dipModal   = document.getElementById('dipModal');
  const btnNewDip  = document.getElementById('btnNewDip');
  const closeBtns  = dipModal ? dipModal.querySelectorAll('[data-close-dip]') : [];
  const dipForm    = document.getElementById('dipForm');
  const dipSaveBtn = document.getElementById('dipSaveBtn');
  const tbody      = document.getElementById('dipsTbody');
  const emptyState = document.getElementById('dipsEmptyState');
  const kpiMonth   = document.getElementById('kpiThisMonth');
  const bookBalance = {{ (float)($bookBalance20 ?? 0) }};

  function openModal() {
    if (!dipModal) return;
    dipModal.classList.remove('hidden');
  }
  function closeModal() {
    if (!dipModal) return;
    dipModal.classList.add('hidden');
  }

  btnNewDip?.addEventListener('click', openModal);
  closeBtns.forEach(btn => btn.addEventListener('click', closeModal));

  // Refresh button just reloads
  document.getElementById('btnDipRefresh')?.addEventListener('click', () => window.location.reload());

  // -------- Live preview + auto-fill of inputs --------
  const previewObserved = document.getElementById('preview_observed');
  const previewAt20     = document.getElementById('preview_at20');
  const previewVariance = document.getElementById('preview_variance');

  const inputObserved = document.getElementById('observed_volume');
  const inputAt20     = document.getElementById('volume_20');
  const btnRecalc     = document.getElementById('btnRecalcVolumes');

  function approxAt20(observed, temp) {
    const a = 0.0008; // very rough thermal expansion
    return observed / (1 + a * (temp - 20));
  }

  function updatePreview(forceWriteInputs = true) {
    const h = parseFloat(document.getElementById('dip_height')?.value || '0');
    const t = parseFloat(document.getElementById('temperature')?.value || '20');

    // per-cm factor from depot policies (default_dip_litres_per_cm)
    const perCm = {{ (float)($defaultLitresPerCm ?? 350) }};
    const obs   = h > 0 ? h * perCm : 0;
    const at20  = obs > 0 ? approxAt20(obs, t) : 0;

    const fmt = v => v ? Intl.NumberFormat().format(Math.round(v)) + ' L' : '—';

    if (previewObserved) previewObserved.textContent = fmt(obs);
    if (previewAt20)     previewAt20.textContent     = fmt(at20);
    if (previewVariance) previewVariance.textContent = bookBalance > 0
      ? ((at20 - bookBalance) >= 0 ? '+' : '') +
        Intl.NumberFormat().format(Math.round(at20 - bookBalance)) + ' L'
      : '—';

    // Push into real inputs (so form submits auto values) unless user explicitly edits
    if (forceWriteInputs) {
      if (inputObserved && (inputObserved.value === '' || inputObserved.dataset.autofill === '1')) {
        inputObserved.value = obs ? obs.toFixed(3) : '';
        inputObserved.dataset.autofill = '1';
      }
      if (inputAt20 && (inputAt20.value === '' || inputAt20.dataset.autofill === '1')) {
        inputAt20.value = at20 ? at20.toFixed(3) : '';
        inputAt20.dataset.autofill = '1';
      }
    }
  }

  // When user manually types in the volume fields, stop overwriting them
  if (inputObserved) {
    inputObserved.addEventListener('input', () => {
      inputObserved.dataset.autofill = '0';
    });
  }
  if (inputAt20) {
    inputAt20.addEventListener('input', () => {
      inputAt20.dataset.autofill = '0';
    });
  }

  // Recalculate button: force overwrite even if user had changed them before
  if (btnRecalc) {
    btnRecalc.addEventListener('click', () => {
      if (inputObserved) inputObserved.dataset.autofill = '1';
      if (inputAt20)     inputAt20.dataset.autofill = '1';
      updatePreview(true);
    });
  }

  ['dip_height','temperature'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    ['input','change','keyup'].forEach(ev => el.addEventListener(ev, () => updatePreview(false)));
  });
  updatePreview(true);

  // -------- Validation helpers --------
  function clearErrors() {
    if (!dipForm) return;
    dipForm.querySelectorAll('[id^="err_"]').forEach(el => {
      el.textContent = '';
      el.classList.add('hidden');
    });
  }
  function showErrors(errors) {
    Object.entries(errors || {}).forEach(([field, msgs]) => {
      const el = document.getElementById('err_' + field);
      if (el) {
        el.textContent = (msgs && msgs[0]) || 'Invalid';
        el.classList.remove('hidden');
      }
    });
  }
  ['tank_id','date','dip_height','temperature','density','observed_volume','volume_20','note'].forEach(id => {
    const el = document.getElementById(id);
    el?.addEventListener('input', () => {
      const err = document.getElementById('err_' + id);
      if (err) { err.textContent = ''; err.classList.add('hidden'); }
    });
  });

  // -------- AJAX submit --------
  if (dipForm) {
    dipForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors();
      if (!dipSaveBtn) return;

      dipSaveBtn.disabled = true;
      dipSaveBtn.textContent = 'Saving…';

      try {
        const formData = new FormData(dipForm);
        const res = await fetch(dipForm.action, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': dipForm.querySelector('input[name=_token]').value
          },
          body: formData,
        });

        if (res.status === 422) {
          const data = await res.json().catch(() => ({}));
          showErrors(data.errors || {});
          showToast('Please fix the highlighted fields.', 'error');
          dipSaveBtn.disabled = false;
          dipSaveBtn.textContent = 'Save DIP';
          return;
        }

        if (res.status === 419 || res.status === 401) {
          showToast('Your session expired. Refresh and try again.', 'error');
          dipSaveBtn.disabled = false;
          dipSaveBtn.textContent = 'Save DIP';
          return;
        }

        if (!res.ok) {
          let msg = 'Something went wrong';
          try {
            const data = await res.json();
            if (data && data.message) msg = data.message;
          } catch {
            const text = await res.text();
            if (text) msg = text.slice(0, 180);
          }
          showToast(msg, 'error');
          dipSaveBtn.disabled = false;
          dipSaveBtn.textContent = 'Save DIP';
          return;
        }

        const data = await res.json();
        const d = data.dip || null;

        // Remove empty state if present
        if (emptyState) emptyState.remove();

        if (tbody && d) {
          const tr = document.createElement('tr');
          const fmtInt = v => Number(v || 0).toLocaleString();
          const dipDate = (d.date || '').toString().slice(0, 10);

          tr.className = 'hover:bg-gray-50/70 transition-colors';
          tr.innerHTML = `
            <td class="px-4 py-2 whitespace-nowrap text-gray-800">${dipDate}</td>
            <td class="px-4 py-2 whitespace-nowrap text-gray-700">
              ${d.tank_label || ('T' + d.tank_id)}
            </td>
            <td class="px-4 py-2 text-right text-gray-700">
              ${Number(d.dip_height || 0).toFixed(2)}
            </td>
            <td class="px-4 py-2 text-right text-gray-700">
              ${fmtInt(d.observed_volume)}
            </td>
            <td class="px-4 py-2 text-right text-gray-800">
              ${fmtInt(d.volume_20)}
            </td>
            <td class="px-4 py-2 text-right text-gray-700">—</td>
            <td class="px-4 py-2 text-right text-gray-400">—</td>
            <td class="px-4 py-2 whitespace-nowrap text-right text-xs text-indigo-600">
              <span class="opacity-60">Just added</span>
            </td>
          `;
          tbody.insertBefore(tr, tbody.firstChild);
        }

        // If dip is in this month, bump KPI
        if (kpiMonth && d && d.date) {
          try {
            const now = new Date();
            const dd  = new Date(d.date);
            if (dd.getFullYear() === now.getFullYear() && dd.getMonth() === now.getMonth()) {
              const current = parseInt(kpiMonth.textContent || '0', 10) || 0;
              kpiMonth.textContent = current + 1;
            }
          } catch {}
        }

        showToast(data.msg || 'DIP recorded successfully.');
        dipForm.reset();
        // reset autofill flags so next time we overwrite again
        if (inputObserved) inputObserved.dataset.autofill = '1';
        if (inputAt20)     inputAt20.dataset.autofill = '1';
        updatePreview(true);
        dipSaveBtn.disabled = false;
        dipSaveBtn.textContent = 'Save DIP';
        closeModal();
      } catch (err) {
        console.error(err);
        showToast('Network error — please check your connection.', 'error');
        dipSaveBtn.disabled = false;
        dipSaveBtn.textContent = 'Save DIP';
      }
    });
  }
});
</script>
@endpush